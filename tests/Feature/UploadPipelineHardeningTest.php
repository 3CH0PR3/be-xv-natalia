<?php

namespace Tests\Feature;

use App\Events\MemoryCreated;
use App\Events\MemoryDeleted;
use App\Events\MemoryUpdated;
use App\Events\UploadOperationUpdated;
use App\Jobs\ProcessUploadOperationFileJob;
use App\Models\Event;
use App\Models\Memory;
use App\Models\MemoryMediaItem;
use App\Models\UploadOperation;
use App\Models\UploadOperationFile;
use App\Support\Media\FfmpegVideoProcessor;
use App\Support\Storage\UploadOperationHelper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Event as EventFacade;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class UploadPipelineHardeningTest extends TestCase
{
  use RefreshDatabase;

  private array $headers;

  protected function setUp(): void
  {
    parent::setUp();
    $this->seed();
    Storage::fake('public');
    Storage::fake('local');

    $this->postJson('/api/v1/checkin', [
      'eventCode' => 'XV-NATALIA',
      'tableCode' => 'Mesa 01 · Principal',
      'guestName' => 'Pruebas Pipeline',
      'deviceId' => 'dev_pipeline_hardening',
      'actorKey' => 'mesa-01-principal__pruebas-pipeline',
    ])->assertStatus(201);

    $this->headers = [
      'X-Device-Id' => 'dev_pipeline_hardening',
      'X-Actor-Key' => 'mesa-01-principal__pruebas-pipeline',
    ];
  }

  public function test_chunks_are_stored_as_isolated_part_files_and_cleaned_up_after_worker_assembly(): void
  {
    Queue::fake();
    EventFacade::fake([UploadOperationUpdated::class, MemoryCreated::class, MemoryUpdated::class]);

    $chunk0 = str_repeat('0', 2048);
    $chunk1 = str_repeat('1', 2048);
    $chunk2 = str_repeat('2', 1024);
    $totalBytes = strlen($chunk0 . $chunk1 . $chunk2);

    $this->postJson('/api/v1/upload-operations', [
      'operationKey' => 'uop-part-isolation',
      'caption' => 'Aislamiento de fragmentos .part',
      'files' => [
        [
          'fileKey' => 'vid-part',
          'originalName' => 'prueba_partes.mp4',
          'mimeType' => 'video/mp4',
          'byteSize' => $totalBytes,
          'chunkSize' => 2048,
          'totalChunks' => 3,
          'sortOrder' => 0,
        ],
      ],
    ], $this->headers)->assertStatus(201);

    $sendBinary = function (int $chunkIndex, string $bytes) {
      return $this->call(
        'POST',
        '/api/v1/upload-operations/uop-part-isolation/files/vid-part/chunk-stream',
        [],
        [],
        [],
        [
          'HTTP_X_DEVICE_ID' => $this->headers['X-Device-Id'],
          'HTTP_X_ACTOR_KEY' => $this->headers['X-Actor-Key'],
          'HTTP_X_CHUNK_INDEX' => (string) $chunkIndex,
          'CONTENT_TYPE' => 'application/octet-stream',
          'HTTP_ACCEPT' => 'application/json',
        ],
        $bytes
      );
    };

    // Out-of-order upload: chunk 2 arrives before chunk 0 & 1
    $sendBinary(2, $chunk2)->assertStatus(200);

    $helper = app(UploadOperationHelper::class);
    $op = UploadOperation::where('operation_key', 'uop-part-isolation')->with('event')->firstOrFail();
    $file = $op->files()->firstOrFail();

    Storage::disk('public')->assertExists($helper->chunkPartPathFor($op->event, $op, $file, 2));
    Storage::disk('public')->assertMissing($helper->stagingPathFor($op->event, $op, $file));

    $sendBinary(0, $chunk0)->assertStatus(200);
    $sendBinary(1, $chunk1)->assertStatus(200);

    Storage::disk('public')->assertExists($helper->chunkPartPathFor($op->event, $op, $file, 0));
    Storage::disk('public')->assertExists($helper->chunkPartPathFor($op->event, $op, $file, 1));
    Storage::disk('public')->assertExists($helper->chunkPartPathFor($op->event, $op, $file, 2));

    Queue::assertPushed(ProcessUploadOperationFileJob::class);

    // Run job directly to verify ordered concatenation & .part directory cleanup
    app()->call([new ProcessUploadOperationFileJob($file->id), 'handle']);

    $file->refresh();
    $this->assertSame(UploadOperationFile::STATUS_COMPLETED, $file->status);
    Storage::disk('public')->assertExists($file->final_path);
    $this->assertSame(
      hash('sha256', $chunk0 . $chunk1 . $chunk2),
      hash('sha256', Storage::disk('public')->get($file->final_path))
    );
    $this->assertFalse(Storage::disk('public')->directoryExists($helper->chunkDirFor($op->event, $op, $file)));
    Storage::disk('public')->assertMissing($helper->stagingPathFor($op->event, $op, $file));
  }

  public function test_final_path_for_caps_long_unicode_filenames_within_varchar_255(): void
  {
    $helper = app(UploadOperationHelper::class);
    $event = Event::byCode('XV-NATALIA')->firstOrFail();

    $operation = new UploadOperation([
      'memory_external_id' => 'xv-nat-1795643185541-' . str_repeat('a', 30),
    ]);
    $file = new UploadOperationFile([
      'file_key' => 'm-35-' . str_repeat('k', 80),
      'original_name' => 'Casi nadie conoce este trucazo de las DevTools 💣🎶 Puedes sobrescribir el CSS y JS de una página web completa para que no se pierdan los cambios al recargar en producción 2026.m2ts',
      'mime_type' => 'video/mp2t',
      'media_type' => 'video',
    ]);

    $path = $helper->finalPathFor($event, $operation, $file);
    $this->assertLessThanOrEqual(255, strlen($path));
    $this->assertStringEndsWith('.m2ts', $path);
  }

  public function test_queue_config_uses_dedicated_redis_db_and_retry_after_above_heavy_timeout(): void
  {
    $this->assertArrayHasKey('queue', config('database.redis'));
    $this->assertSame((string) env('REDIS_QUEUE_DB', '3'), (string) config('database.redis.queue.database'));
    $this->assertGreaterThan(600, (int) config('queue.connections.redis.retry_after'));
  }

  public function test_terminal_completed_with_errors_operation_reports_100_percent_progress(): void
  {
    $op = new UploadOperation([
      'status' => UploadOperation::STATUS_COMPLETED_WITH_ERRORS,
      'total_files' => 47,
      'completed_files' => 46,
      'failed_files' => 1,
      'total_bytes' => 4292068736,
      'uploaded_bytes' => 4292068736,
    ]);

    $this->assertSame(100, $op->progressPercent());
    $this->assertSame(100, $op->progress_percent);
  }

  public function test_memory_broadcast_payload_with_100_media_items_stays_safely_below_reverb_limit(): void
  {
    EventFacade::fake([MemoryCreated::class, MemoryUpdated::class]);

    $event = Event::byCode('XV-NATALIA')->firstOrFail();
    $memory = Memory::create([
      'external_id' => 'xv-nat-100-items-test',
      'event_id' => $event->id,
      'guest_name' => 'Pruebas 100',
      'table_code' => 'Mesa 01 · Principal',
      'owner_device_id' => $this->headers['X-Device-Id'],
      'owner_actor_key' => $this->headers['X-Actor-Key'],
      'media_type' => 'video',
      'status' => Memory::STATUS_APPROVED,
      'timestamp_ms' => 1795643185541,
    ]);

    for ($i = 0; $i < 100; $i++) {
      MemoryMediaItem::create([
        'memory_id' => $memory->id,
        'external_id' => "item-{$i}",
        'media_type' => 'video',
        'disk' => 'public',
        'file_path' => "memories/XV-NATALIA/xv-nat-100-items-test/video-largo-evento-quinceanera-clip-numero-{$i}-item-{$i}.mp4",
        'poster_path' => "memories/XV-NATALIA/xv-nat-100-items-test/posters/item-{$i}.jpg",
        'mime_type' => 'video/mp4',
        'byte_size' => 1048576,
        'sort_order' => $i,
      ]);
    }

    app(UploadOperationHelper::class)->broadcastMemoryChange($memory, 'http://127.0.0.1:3000', true);

    EventFacade::assertDispatched(MemoryCreated::class, function (MemoryCreated $ev): bool {
      $encoded = json_encode($ev->memory, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
      $this->assertNotFalse($encoded);
      $this->assertCount(100, $ev->memory['mediaItems']);
      $this->assertLessThan(524288, strlen($encoded));

      return true;
    });
  }

  public function test_ffmpeg_transcodes_avi_to_mp4_and_extracts_fallback_poster_when_enabled(): void
  {
    config()->set('xv.ffmpeg_enabled', true);
    config()->set('xv.ffmpeg_bin', 'ffmpeg');
    config()->set('xv.ffprobe_bin', 'ffprobe');
    EventFacade::fake([UploadOperationUpdated::class, MemoryCreated::class, MemoryUpdated::class]);

    Process::fake(function (PendingProcess $process) {
      $cmd = is_array($process->command) ? $process->command : [$process->command];
      $bin = (string) ($cmd[0] ?? '');

      if (in_array('-version', $cmd, true)) {
        return Process::result(output: 'ffmpeg version 9.1');
      }

      if (str_contains($bin, 'ffprobe') || in_array('-show_streams', $cmd, true)) {
        return Process::result(output: json_encode([
          'streams' => [
            ['codec_type' => 'video', 'codec_name' => 'msmpeg4v3', 'width' => 1280, 'height' => 720],
            ['codec_type' => 'audio', 'codec_name' => 'mp3'],
          ],
          'format' => ['duration' => '4.2'],
        ]));
      }

      $outputPath = (string) end($cmd);
      $outDir = dirname($outputPath);
      if (!is_dir($outDir)) {
        @mkdir($outDir, 0775, true);
      }

      if (str_ends_with($outputPath, '.jpg')) {
        file_put_contents($outputPath, "\xFF\xD8\xFF" . str_repeat('J', 400));
      } else {
        file_put_contents($outputPath, str_repeat('M', 4096));
      }

      return Process::result(output: 'ok');
    });

    $this->postJson('/api/v1/upload-operations', [
      'operationKey' => 'uop-ffmpeg-avi',
      'caption' => 'Video AVI convertido en servidor',
      'files' => [
        [
          'fileKey' => 'vid-avi',
          'originalName' => 'infotip_clip.avi',
          'mimeType' => 'video/x-msvideo',
          'byteSize' => 1024,
          'chunkSize' => 16777216,
          'totalChunks' => 1,
          'sortOrder' => 0,
        ],
      ],
    ], $this->headers)->assertStatus(201);

    $this->call(
      'POST',
      '/api/v1/upload-operations/uop-ffmpeg-avi/files/vid-avi/chunk-stream',
      [],
      [],
      [],
      [
        'HTTP_X_DEVICE_ID' => $this->headers['X-Device-Id'],
        'HTTP_X_ACTOR_KEY' => $this->headers['X-Actor-Key'],
        'HTTP_X_CHUNK_INDEX' => '0',
        'CONTENT_TYPE' => 'application/octet-stream',
        'HTTP_ACCEPT' => 'application/json',
      ],
      str_repeat('A', 1024)
    )->assertStatus(200);

    $file = UploadOperationFile::with('mediaItem')->where('file_key', 'vid-avi')->firstOrFail();
    $this->assertSame(UploadOperationFile::STATUS_COMPLETED, $file->status);
    $this->assertStringEndsWith('.mp4', (string) $file->final_path);
    $this->assertSame('video/mp4', $file->mime_type);
    $this->assertSame(4096, (int) $file->byte_size);
    $this->assertNotEmpty($file->poster_path);
    $this->assertStringEndsWith('.jpg', (string) $file->poster_path);

    Storage::disk('public')->assertExists($file->final_path);
    Storage::disk('public')->assertExists($file->poster_path);

    $this->assertNotNull($file->mediaItem);
    $this->assertSame($file->final_path, $file->mediaItem->file_path);
    $this->assertSame($file->poster_path, $file->mediaItem->poster_path);
    $this->assertSame('video/mp4', $file->mediaItem->mime_type);
    $this->assertSame(4096, (int) $file->mediaItem->byte_size);
  }

  public function test_ffmpeg_extracts_fallback_poster_for_mp4_without_poster(): void
  {
    config()->set('xv.ffmpeg_enabled', true);
    config()->set('xv.ffmpeg_bin', 'ffmpeg');
    config()->set('xv.ffprobe_bin', 'ffprobe');
    EventFacade::fake([UploadOperationUpdated::class, MemoryCreated::class, MemoryUpdated::class]);

    Process::fake(function (PendingProcess $process) {
      $cmd = is_array($process->command) ? $process->command : [$process->command];
      $bin = (string) ($cmd[0] ?? '');

      if (in_array('-version', $cmd, true)) {
        return Process::result(output: 'ffmpeg version 9.1');
      }

      if (str_contains($bin, 'ffprobe') || in_array('-show_streams', $cmd, true)) {
        return Process::result(output: json_encode([
          'streams' => [
            ['codec_type' => 'video', 'codec_name' => 'h264', 'width' => 1920, 'height' => 1080],
          ],
          'format' => ['duration' => '10.0'],
        ]));
      }

      $outputPath = (string) end($cmd);
      $outDir = dirname($outputPath);
      if (!is_dir($outDir)) {
        @mkdir($outDir, 0775, true);
      }
      file_put_contents($outputPath, "\xFF\xD8\xFF" . str_repeat('P', 300));

      return Process::result(output: 'ok');
    });

    $this->postJson('/api/v1/upload-operations', [
      'operationKey' => 'uop-ffmpeg-mp4-poster',
      'caption' => 'MP4 sin portada cliente',
      'files' => [
        [
          'fileKey' => 'vid-mp4',
          'originalName' => 'clip_sin_poster.mp4',
          'mimeType' => 'video/mp4',
          'byteSize' => 2048,
          'chunkSize' => 16777216,
          'totalChunks' => 1,
          'sortOrder' => 0,
        ],
      ],
    ], $this->headers)->assertStatus(201);

    $this->call(
      'POST',
      '/api/v1/upload-operations/uop-ffmpeg-mp4-poster/files/vid-mp4/chunk-stream',
      [],
      [],
      [],
      [
        'HTTP_X_DEVICE_ID' => $this->headers['X-Device-Id'],
        'HTTP_X_ACTOR_KEY' => $this->headers['X-Actor-Key'],
        'HTTP_X_CHUNK_INDEX' => '0',
        'CONTENT_TYPE' => 'application/octet-stream',
        'HTTP_ACCEPT' => 'application/json',
      ],
      str_repeat('V', 2048)
    )->assertStatus(200);

    $file = UploadOperationFile::with('mediaItem')->where('file_key', 'vid-mp4')->firstOrFail();
    $this->assertSame(UploadOperationFile::STATUS_COMPLETED, $file->status);
    $this->assertNotEmpty($file->poster_path);
    $this->assertStringEndsWith('.jpg', (string) $file->poster_path);
    Storage::disk('public')->assertExists($file->poster_path);
    $this->assertSame($file->poster_path, $file->mediaItem?->poster_path);
  }

  public function test_missing_part_chunk_during_worker_assembly_throws_clear_error(): void
  {
    Queue::fake();
    EventFacade::fake([UploadOperationUpdated::class, MemoryCreated::class, MemoryUpdated::class]);

    $this->postJson('/api/v1/upload-operations', [
      'operationKey' => 'uop-missing-part',
      'caption' => 'Falta un fragmento .part',
      'files' => [
        [
          'fileKey' => 'vid-gap',
          'originalName' => 'hueco.mp4',
          'mimeType' => 'video/mp4',
          'byteSize' => 4096,
          'chunkSize' => 2048,
          'totalChunks' => 2,
          'sortOrder' => 0,
        ],
      ],
    ], $this->headers)->assertStatus(201);

    $op = UploadOperation::where('operation_key', 'uop-missing-part')->with('event')->firstOrFail();
    $file = $op->files()->firstOrFail();
    $helper = app(UploadOperationHelper::class);

    // Only chunk 0 exists on disk; simulate received_chunks listing both 0 and 1
    Storage::disk('public')->put($helper->chunkPartPathFor($op->event, $op, $file, 0), str_repeat('A', 2048));
    $file->update(['received_chunks' => [0, 1]]);

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('Falta el fragmento #1');

    app()->call([new ProcessUploadOperationFileJob($file->id), 'handle']);
  }

  public function test_upload_operation_broadcast_payload_for_100_files_stays_below_reverb_limit(): void
  {
    EventFacade::fake([UploadOperationUpdated::class]);

    $files = [];
    for ($i = 1; $i <= 100; $i++) {
      $files[] = [
        'fileKey' => "clip-{$i}",
        'originalName' => "video_evento_completo_quinceanera_clip_{$i}.mp4",
        'mimeType' => 'video/mp4',
        'byteSize' => 2048,
        'chunkSize' => 16777216,
        'totalChunks' => 1,
        'sortOrder' => $i - 1,
      ];
    }

    $this->postJson('/api/v1/upload-operations', [
      'operationKey' => 'uop-100-files-broadcast',
      'caption' => 'Lote de 100 archivos',
      'files' => $files,
    ], $this->headers)->assertStatus(201);

    EventFacade::assertDispatched(UploadOperationUpdated::class, function (UploadOperationUpdated $ev): bool {
      $encoded = json_encode($ev->operation, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
      $this->assertNotFalse($encoded);
      $this->assertLessThan(524288, strlen($encoded));

      return true;
    });
  }

  public function test_deleting_memory_removes_media_files_empty_directories_and_upload_operations(): void
  {
    EventFacade::fake([UploadOperationUpdated::class, MemoryCreated::class, MemoryUpdated::class, MemoryDeleted::class]);

    $this->postJson('/api/v1/upload-operations', [
      'operationKey' => 'uop-delete-cleanup',
      'memoryExternalId' => 'xv-nat-delete-cleanup-1',
      'caption' => 'Video con portada para eliminar',
      'files' => [
        [
          'fileKey' => 'vid-del',
          'originalName' => 'clip_para_borrar.mp4',
          'mimeType' => 'video/mp4',
          'byteSize' => 2048,
          'chunkSize' => 16777216,
          'totalChunks' => 1,
          'sortOrder' => 0,
        ],
      ],
    ], $this->headers)->assertStatus(201);

    $this->call(
      'POST',
      '/api/v1/upload-operations/uop-delete-cleanup/files/vid-del/chunk-stream',
      [],
      [],
      [],
      [
        'HTTP_X_DEVICE_ID' => $this->headers['X-Device-Id'],
        'HTTP_X_ACTOR_KEY' => $this->headers['X-Actor-Key'],
        'HTTP_X_CHUNK_INDEX' => '0',
        'CONTENT_TYPE' => 'application/octet-stream',
        'HTTP_ACCEPT' => 'application/json',
      ],
      str_repeat('V', 2048)
    )->assertStatus(200);

    $poster = UploadedFile::fake()->image('poster.jpg', 320, 180);
    $this->post('/api/v1/upload-operations/uop-delete-cleanup/files/vid-del/poster', [
      'poster' => $poster,
    ], $this->headers)->assertStatus(200);

    $memory = Memory::with('mediaItems')->where('external_id', 'xv-nat-delete-cleanup-1')->firstOrFail();
    $mediaItem = $memory->mediaItems->firstOrFail();
    $memoryDir = 'memories/XV-NATALIA/xv-nat-delete-cleanup-1';
    $postersDir = "{$memoryDir}/posters";

    Storage::disk('public')->assertExists($mediaItem->file_path);
    Storage::disk('public')->assertExists($mediaItem->poster_path);
    $this->assertTrue(Storage::disk('public')->directoryExists($postersDir));
    $this->assertTrue(Storage::disk('public')->directoryExists($memoryDir));
    $this->assertSame(1, UploadOperation::where('operation_key', 'uop-delete-cleanup')->count());
    $this->assertSame(1, UploadOperationFile::where('file_key', 'vid-del')->count());

    $this->deleteJson('/api/v1/memories/xv-nat-delete-cleanup-1', [], $this->headers)
      ->assertStatus(200)
      ->assertJsonPath('data.deleted', true);

    $this->assertDatabaseMissing('memories', ['external_id' => 'xv-nat-delete-cleanup-1']);
    $this->assertDatabaseMissing('memory_media_items', ['id' => $mediaItem->id]);
    $this->assertDatabaseMissing('upload_operations', ['operation_key' => 'uop-delete-cleanup']);
    $this->assertDatabaseMissing('upload_operation_files', ['file_key' => 'vid-del']);

    Storage::disk('public')->assertMissing($mediaItem->file_path);
    Storage::disk('public')->assertMissing($mediaItem->poster_path);
    $this->assertFalse(Storage::disk('public')->directoryExists($postersDir));
    $this->assertFalse(Storage::disk('public')->directoryExists($memoryDir));
  }

  public function test_deleting_last_media_item_and_purging_event_prune_directories_and_upload_operations(): void
  {
    EventFacade::fake([UploadOperationUpdated::class, MemoryCreated::class, MemoryUpdated::class, MemoryDeleted::class]);

    // 1) Delete last media item -> cascades parent memory delete + directory & upload operation cleanup
    $this->postJson('/api/v1/upload-operations', [
      'operationKey' => 'uop-last-item-del',
      'memoryExternalId' => 'xv-nat-last-item-del',
      'caption' => 'Video unico',
      'files' => [
        [
          'fileKey' => 'vid-single',
          'originalName' => 'unico.mp4',
          'mimeType' => 'video/mp4',
          'byteSize' => 1024,
          'chunkSize' => 16777216,
          'totalChunks' => 1,
          'sortOrder' => 0,
        ],
      ],
    ], $this->headers)->assertStatus(201);

    $this->call(
      'POST',
      '/api/v1/upload-operations/uop-last-item-del/files/vid-single/chunk-stream',
      [],
      [],
      [],
      [
        'HTTP_X_DEVICE_ID' => $this->headers['X-Device-Id'],
        'HTTP_X_ACTOR_KEY' => $this->headers['X-Actor-Key'],
        'HTTP_X_CHUNK_INDEX' => '0',
        'CONTENT_TYPE' => 'application/octet-stream',
        'HTTP_ACCEPT' => 'application/json',
      ],
      str_repeat('S', 1024)
    )->assertStatus(200);

    $this->post('/api/v1/upload-operations/uop-last-item-del/files/vid-single/poster', [
      'poster' => UploadedFile::fake()->image('poster_single.jpg', 320, 180),
    ], $this->headers)->assertStatus(200);

    $this->deleteJson('/api/v1/memories/xv-nat-last-item-del/media/vid-single', [], $this->headers)
      ->assertStatus(200);

    $this->assertDatabaseMissing('memories', ['external_id' => 'xv-nat-last-item-del']);
    $this->assertDatabaseMissing('upload_operations', ['operation_key' => 'uop-last-item-del']);
    $this->assertFalse(Storage::disk('public')->directoryExists('memories/XV-NATALIA/xv-nat-last-item-del'));

    // 2) Purge event -> cleans remaining memories, upload_operations, and event subdirectories
    $this->postJson('/api/v1/upload-operations', [
      'operationKey' => 'uop-purge-clean',
      'memoryExternalId' => 'xv-nat-purge-clean',
      'caption' => 'Para purgar',
      'files' => [
        [
          'fileKey' => 'vid-purge',
          'originalName' => 'purge.mp4',
          'mimeType' => 'video/mp4',
          'byteSize' => 1024,
          'chunkSize' => 16777216,
          'totalChunks' => 1,
          'sortOrder' => 0,
        ],
      ],
    ], $this->headers)->assertStatus(201);

    $this->call(
      'POST',
      '/api/v1/upload-operations/uop-purge-clean/files/vid-purge/chunk-stream',
      [],
      [],
      [],
      [
        'HTTP_X_DEVICE_ID' => $this->headers['X-Device-Id'],
        'HTTP_X_ACTOR_KEY' => $this->headers['X-Actor-Key'],
        'HTTP_X_CHUNK_INDEX' => '0',
        'CONTENT_TYPE' => 'application/octet-stream',
        'HTTP_ACCEPT' => 'application/json',
      ],
      str_repeat('P', 1024)
    )->assertStatus(200);

    $this->withHeaders(['X-Host-Pin' => '1515'])
      ->postJson('/api/v1/moderation/purge')
      ->assertStatus(200)
      ->assertJsonPath('data.purged', true);

    $this->assertSame(0, Memory::count());
    $this->assertSame(0, UploadOperation::count());
    $this->assertSame(0, UploadOperationFile::count());
    $this->assertFalse(Storage::disk('public')->directoryExists('memories/XV-NATALIA/xv-nat-purge-clean'));
    $this->assertFalse(Storage::disk('public')->directoryExists('memories/XV-NATALIA'));
  }

  public function test_ffmpeg_binary_resolution_handles_bare_commands_and_missing_explicit_paths(): void
  {
    $processor = app(FfmpegVideoProcessor::class);

    config()->set('xv.ffmpeg_bin', 'ffmpeg');
    config()->set('xv.ffprobe_bin', 'ffprobe');
    $this->assertSame('ffmpeg', $processor->resolveFfmpegBinary());
    $this->assertSame('ffprobe', $processor->resolveFfprobeBinary());

    config()->set('xv.ffmpeg_bin', 'C:/nonexistent/path/to/ffmpeg.exe');
    $resolved = $processor->resolveFfmpegBinary();
    $this->assertNotEmpty($resolved);
    $this->assertNotSame('C:/nonexistent/path/to/ffmpeg.exe', $resolved);
  }
}
