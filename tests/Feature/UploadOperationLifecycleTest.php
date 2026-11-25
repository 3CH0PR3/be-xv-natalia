<?php

namespace Tests\Feature;

use App\Events\MemoryCreated;
use App\Events\MemoryUpdated;
use App\Events\UploadOperationUpdated;
use App\Jobs\ProcessUploadOperationFileJob;
use App\Models\Memory;
use App\Models\UploadOperation;
use App\Models\UploadOperationFile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class UploadOperationLifecycleTest extends TestCase
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
      'guestName' => 'Pruebas Movil',
      'deviceId' => 'dev_mobile_batch_test',
      'actorKey' => 'mesa-01-principal__pruebas-movil',
    ])->assertStatus(201);

    $this->headers = [
      'X-Device-Id' => 'dev_mobile_batch_test',
      'X-Actor-Key' => 'mesa-01-principal__pruebas-movil',
    ];
  }

  public function test_can_create_14_video_upload_operation_stream_chunks_assemble_into_album_and_recover_state(): void
  {
    Event::fake([
      UploadOperationUpdated::class,
      MemoryCreated::class,
      MemoryUpdated::class,
    ]);

    $files = [];
    for ($i = 1; $i <= 14; $i++) {
      $totalChunks = $i <= 2 ? 2 : 1;
      $byteSize = $i <= 2 ? 4096 : 1536;
      $files[] = [
        'fileKey' => "vid-{$i}",
        'originalName' => "video_entrada_{$i}.mp4",
        'mimeType' => 'video/mp4',
        'byteSize' => $byteSize,
        'chunkSize' => 2048,
        'totalChunks' => $totalChunks,
        'sortOrder' => $i - 1,
      ];
    }

    $initResponse = $this->postJson('/api/v1/upload-operations', [
      'operationKey' => 'uop-batch-14-videos',
      'guestName' => 'Pruebas Movil',
      'tableCode' => 'Mesa 01 · Principal',
      'momentTag' => 'Vals Principal',
      'caption' => 'Los 14 videos completos del vals',
      'files' => $files,
    ], $this->headers);

    $initResponse->assertStatus(201);
    $initResponse->assertJsonPath('data.status', 'queued');
    $initResponse->assertJsonPath('data.totalFiles', 14);
    $initResponse->assertJsonCount(14, 'data.files');

    $listBefore = $this->getJson('/api/v1/upload-operations', $this->headers);
    $listBefore->assertStatus(200);
    $listBefore->assertJsonCount(1, 'data');
    $listBefore->assertJsonPath('data.0.status', 'queued');

    for ($i = 1; $i <= 14; $i++) {
      $fileKey = "vid-{$i}";
      if ($i <= 2) {
        $chunk0 = UploadedFile::fake()->createWithContent('part0.bin', str_repeat('X', 2048));
        $chunk1 = UploadedFile::fake()->createWithContent('part1.bin', str_repeat('Y', 2048));

        $midRes = $this->post("/api/v1/upload-operations/uop-batch-14-videos/files/{$fileKey}/chunks", [
          'chunkIndex' => 0,
          'chunk' => $chunk0,
        ], $this->headers);
        $midRes->assertStatus(200);

        $doneRes = $this->post("/api/v1/upload-operations/uop-batch-14-videos/files/{$fileKey}/chunks", [
          'chunkIndex' => 1,
          'chunk' => $chunk1,
        ], $this->headers);
        $doneRes->assertStatus(200);
      } else {
        $single = UploadedFile::fake()->createWithContent('part0.bin', str_repeat('Z', 1536));
        $doneRes = $this->post("/api/v1/upload-operations/uop-batch-14-videos/files/{$fileKey}/chunks", [
          'chunkIndex' => 0,
          'chunk' => $single,
        ], $this->headers);
        $doneRes->assertStatus(200);
      }
    }

    $recovered = $this->getJson('/api/v1/upload-operations', $this->headers);
    $recovered->assertStatus(200);
    $recovered->assertJsonPath('data.0.status', 'completed');
    $recovered->assertJsonPath('data.0.completedFiles', 14);
    $recovered->assertJsonPath('data.0.failedFiles', 0);
    $recovered->assertJsonPath('data.0.progressPercent', 100);

    $memory = Memory::with('mediaItems')->first();
    $this->assertNotNull($memory);
    $this->assertEquals(14, $memory->mediaItems->count());
    $this->assertEquals('video', $memory->media_type);
    $this->assertEquals('Los 14 videos completos del vals', $memory->caption);

    foreach ($memory->mediaItems as $item) {
      Storage::disk('public')->assertExists($item->file_path);
    }

    Event::assertDispatched(UploadOperationUpdated::class);
    Event::assertDispatchedTimes(MemoryCreated::class, 1);
    Event::assertDispatchedTimes(MemoryUpdated::class, 13);
  }

  public function test_partial_failure_records_exact_errors_and_survives_recovery_query(): void
  {
    Event::fake([UploadOperationUpdated::class, MemoryCreated::class, MemoryUpdated::class]);

    $init = $this->postJson('/api/v1/upload-operations', [
      'operationKey' => 'uop-partial-fail',
      'caption' => 'Lote mixto con fallos',
      'files' => [
        [
          'fileKey' => 'ok-1',
          'originalName' => 'clip_bueno.mp4',
          'mimeType' => 'video/mp4',
          'byteSize' => 1024,
          'chunkSize' => 4194304,
          'totalChunks' => 1,
          'sortOrder' => 0,
        ],
        [
          'fileKey' => 'empty-2',
          'originalName' => 'clip_vacio.mp4',
          'mimeType' => 'video/mp4',
          'byteSize' => 0,
          'chunkSize' => 4194304,
          'totalChunks' => 1,
          'sortOrder' => 1,
        ],
        [
          'fileKey' => 'err-3',
          'originalName' => 'clip_restringido.mp4',
          'mimeType' => 'video/mp4',
          'byteSize' => 2048,
          'chunkSize' => 4194304,
          'totalChunks' => 1,
          'sortOrder' => 2,
        ],
      ],
    ], $this->headers);

    $init->assertStatus(201);
    $init->assertJsonPath('data.files.1.status', 'failed');
    $init->assertJsonPath('data.files.1.errorCode', 'EMPTY_FILE');

    $chunkOk = UploadedFile::fake()->createWithContent('ok.mp4', str_repeat('K', 1024));
    $this->post('/api/v1/upload-operations/uop-partial-fail/files/ok-1/chunks', [
      'chunkIndex' => 0,
      'chunk' => $chunkOk,
    ], $this->headers)->assertStatus(200);

    $failRes = $this->postJson('/api/v1/upload-operations/uop-partial-fail/files/err-3/fail', [
      'errorCode' => 'FILE_UNREADABLE',
      'errorMessage' => 'Permiso de lectura expirado en el navegador móvil.',
    ], $this->headers);
    $failRes->assertStatus(200);

    $recovered = $this->getJson('/api/v1/upload-operations/uop-partial-fail', $this->headers);
    $recovered->assertStatus(200);
    $recovered->assertJsonPath('data.status', 'completed_with_errors');
    $recovered->assertJsonPath('data.completedFiles', 1);
    $recovered->assertJsonPath('data.failedFiles', 2);
    $recovered->assertJsonPath('data.files.0.status', 'completed');
    $recovered->assertJsonPath('data.files.1.status', 'failed');
    $recovered->assertJsonPath('data.files.2.status', 'failed');
    $recovered->assertJsonPath('data.files.2.errorCode', 'FILE_UNREADABLE');
    $recovered->assertJsonPath('data.files.2.errorMessage', 'Permiso de lectura expirado en el navegador móvil.');
  }

  public function test_missing_chunk_during_job_execution_marks_file_failed_with_clear_error(): void
  {
    Event::fake([UploadOperationUpdated::class, MemoryCreated::class, MemoryUpdated::class]);

    $this->postJson('/api/v1/upload-operations', [
      'operationKey' => 'uop-missing-chunk',
      'caption' => 'Prueba fragmento faltante',
      'files' => [
        [
          'fileKey' => 'vid-missing',
          'originalName' => 'incompleto.mp4',
          'mimeType' => 'video/mp4',
          'byteSize' => 4096,
          'chunkSize' => 2048,
          'totalChunks' => 2,
          'sortOrder' => 0,
        ],
      ],
    ], $this->headers)->assertStatus(201);

    $file = UploadOperationFile::where('file_key', 'vid-missing')->firstOrFail();
    $job = new ProcessUploadOperationFileJob($file->id);

    try {
      app()->call([$job, 'handle']);
      $this->fail('Se esperaba excepción por fragmento faltante.');
    } catch (\RuntimeException $e) {
      $job->failed($e);
    }

    $op = UploadOperation::where('operation_key', 'uop-missing-chunk')->firstOrFail();
    $file->refresh();

    $this->assertEquals(UploadOperationFile::STATUS_FAILED, $file->status);
    $this->assertEquals('PROCESSING_FAILED', $file->error_code);
    $this->assertStringContainsString('Faltan fragmentos por recibir', (string) $file->error_message);
    $this->assertEquals(UploadOperation::STATUS_FAILED, $op->status);
  }

  public function test_can_attach_poster_and_dismiss_operation_without_deleting_memory(): void
  {
    Event::fake([UploadOperationUpdated::class, MemoryCreated::class, MemoryUpdated::class]);

    $this->postJson('/api/v1/upload-operations', [
      'operationKey' => 'uop-poster-dismiss',
      'caption' => 'Video con portada posterior',
      'files' => [
        [
          'fileKey' => 'vid-p',
          'originalName' => 'baile.mp4',
          'mimeType' => 'video/mp4',
          'byteSize' => 1024,
          'chunkSize' => 4194304,
          'totalChunks' => 1,
          'sortOrder' => 0,
        ],
      ],
    ], $this->headers)->assertStatus(201);

    $this->post('/api/v1/upload-operations/uop-poster-dismiss/files/vid-p/chunks', [
      'chunkIndex' => 0,
      'chunk' => UploadedFile::fake()->createWithContent('baile.mp4', str_repeat('V', 1024)),
    ], $this->headers)->assertStatus(200);

    $poster = UploadedFile::fake()->image('poster.jpg', 320, 180);
    $posterRes = $this->post('/api/v1/upload-operations/uop-poster-dismiss/files/vid-p/poster', [
      'poster' => $poster,
    ], $this->headers);
    $posterRes->assertStatus(200);
    $this->assertNotEmpty($posterRes->json('data.files.0.posterUrl'));

    $this->deleteJson('/api/v1/upload-operations/uop-poster-dismiss', [], $this->headers)
      ->assertStatus(200);

    $listAfter = $this->getJson('/api/v1/upload-operations', $this->headers);
    $listAfter->assertStatus(200);
    $listAfter->assertJsonCount(0, 'data');

    $this->assertEquals(1, Memory::count());
  }

  public function test_parallel_binary_chunk_stream_assembles_out_of_order_chunks_with_byte_integrity(): void
  {
    Event::fake([UploadOperationUpdated::class, MemoryCreated::class, MemoryUpdated::class]);

    $chunk0 = str_repeat('A', 2048);
    $chunk1 = str_repeat('B', 2048);
    $chunk2 = str_repeat('C', 1024);
    $expectedBytes = $chunk0 . $chunk1 . $chunk2;

    $this->postJson('/api/v1/upload-operations', [
      'operationKey' => 'uop-binary-oob',
      'caption' => 'Subida binaria paralela 4K',
      'files' => [
        [
          'fileKey' => 'vid-oob',
          'originalName' => 'vals_4k.mp4',
          'mimeType' => 'video/mp4',
          'byteSize' => strlen($expectedBytes),
          'chunkSize' => 2048,
          'totalChunks' => 3,
          'sortOrder' => 0,
        ],
      ],
    ], $this->headers)->assertStatus(201);

    $sendBinary = function (int $chunkIndex, string $bytes) {
      return $this->call(
        'POST',
        '/api/v1/upload-operations/uop-binary-oob/files/vid-oob/chunk-stream',
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

    $res2 = $sendBinary(2, $chunk2);
    $res2->assertStatus(200);
    $res2->assertJsonPath('data.chunkIndex', 2);

    $res0 = $sendBinary(0, $chunk0);
    $res0->assertStatus(200);
    $res0->assertJsonPath('data.chunkIndex', 0);

    $res1 = $sendBinary(1, $chunk1);
    $res1->assertStatus(200);
    $res1->assertJsonPath('data.chunkIndex', 1);
    $res1->assertJsonPath('data.operationModel.id', 'uop-binary-oob');
    $res1->assertJsonPath('data.operationModel.totalFiles', 1);
    $res1->assertJsonPath('data.operationModel.uploadedFiles', 1);

    $recovered = $this->getJson('/api/v1/upload-operations/uop-binary-oob', $this->headers);
    $recovered->assertStatus(200);
    $recovered->assertJsonPath('data.status', 'completed');
    $recovered->assertJsonPath('data.completedFiles', 1);

    $file = UploadOperationFile::where('file_key', 'vid-oob')->firstOrFail();
    Storage::disk('public')->assertExists($file->final_path);
    $storedBytes = Storage::disk('public')->get($file->final_path);
    $this->assertEquals(hash('sha256', $expectedBytes), hash('sha256', $storedBytes));
  }

  public function test_patch_file_meta_updates_compressed_video_dimensions_before_binary_upload(): void
  {
    Event::fake([UploadOperationUpdated::class, MemoryCreated::class, MemoryUpdated::class]);

    $this->postJson('/api/v1/upload-operations', [
      'operationKey' => 'uop-meta-compress',
      'caption' => 'Video comprimido HD cliente',
      'files' => [
        [
          'fileKey' => 'vid-hd',
          'originalName' => 'entrada_4k.mov',
          'mimeType' => 'video/quicktime',
          'byteSize' => 40 * 1024 * 1024,
          'chunkSize' => 16777216,
          'totalChunks' => 3,
          'sortOrder' => 0,
        ],
      ],
    ], $this->headers)->assertStatus(201);

    $compressedPayload = str_repeat('H', 3000);
    $patch = $this->patchJson('/api/v1/upload-operations/uop-meta-compress/files/vid-hd/meta', [
      'originalName' => 'entrada_hd.mp4',
      'mimeType' => 'video/mp4',
      'byteSize' => strlen($compressedPayload),
      'chunkSize' => 16777216,
      'totalChunks' => 1,
    ], $this->headers);

    $patch->assertStatus(200);
    $patch->assertJsonPath('data.files.0.originalName', 'entrada_hd.mp4');
    $patch->assertJsonPath('data.files.0.byteSize', 3000);
    $patch->assertJsonPath('data.files.0.totalChunks', 1);

    $this->call(
      'POST',
      '/api/v1/upload-operations/uop-meta-compress/files/vid-hd/chunk-stream',
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
      $compressedPayload
    )->assertStatus(200);

    $status = $this->getJson('/api/v1/upload-operations/uop-meta-compress', $this->headers);
    $status->assertStatus(200);
    $status->assertJsonPath('data.status', 'completed');
    $status->assertJsonPath('data.totalBytes', 3000);
  }

  public function test_compact_websocket_delta_broadcasts_stay_under_4kb_even_for_25_files(): void
  {
    Event::fake([UploadOperationUpdated::class, MemoryCreated::class, MemoryUpdated::class]);

    $files = [];
    for ($i = 1; $i <= 25; $i++) {
      $files[] = [
        'fileKey' => "clip-{$i}",
        'originalName' => "video_evento_completo_quinceanera_num_{$i}.mp4",
        'mimeType' => 'video/mp4',
        'byteSize' => 2048,
        'chunkSize' => 16777216,
        'totalChunks' => 1,
        'sortOrder' => $i - 1,
      ];
    }

    $this->postJson('/api/v1/upload-operations', [
      'operationKey' => 'uop-25-files-compact',
      'caption' => 'Lote grande de 25 videos',
      'files' => $files,
    ], $this->headers)->assertStatus(201);

    $payload = str_repeat('Z', 2048);
    $this->call(
      'POST',
      '/api/v1/upload-operations/uop-25-files-compact/files/clip-1/chunk-stream',
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
      $payload
    )->assertStatus(200);

    Event::assertDispatched(UploadOperationUpdated::class, function (UploadOperationUpdated $event): bool {
      $encoded = json_encode($event->operation, JSON_UNESCAPED_UNICODE);
      $this->assertNotFalse($encoded);
      $this->assertLessThan(4096, strlen($encoded), 'El payload de Reverb superó el límite compacto de 4 KB.');
      return true;
    });
  }
}
