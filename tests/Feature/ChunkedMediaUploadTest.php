<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ChunkedMediaUploadTest extends TestCase
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
      'guestName' => 'Invitado Test',
      'deviceId' => 'dev_chunk_test',
      'actorKey' => 'mesa-01-principal__invitado-test',
    ])->assertStatus(201);

    $this->headers = [
      'X-Device-Id' => 'dev_chunk_test',
      'X-Actor-Key' => 'mesa-01-principal__invitado-test',
    ];
  }

  public function test_can_init_upload_chunks_and_publish_massive_video_memory(): void
  {
    $initResponse = $this->postJson('/api/v1/memories/uploads/init', [
      'filename' => 'entrada_quinceanera_30min.mp4',
      'mimeType' => 'video/mp4',
      'totalSize' => 1610612736,
      'totalChunks' => 2,
    ], $this->headers);

    $initResponse->assertStatus(201);
    $uploadKey = $initResponse->json('data.uploadKey');
    $this->assertNotEmpty($uploadKey);

    $part0Content = str_repeat('A', 2048);
    $part1Content = str_repeat('B', 1024);

    $chunk0 = UploadedFile::fake()->createWithContent('part0.mp4', $part0Content);
    $chunk1 = UploadedFile::fake()->createWithContent('part1.mp4', $part1Content);

    $this->post("/api/v1/memories/uploads/{$uploadKey}/chunk", [
      'chunkIndex' => 0,
      'chunk' => $chunk0,
    ], $this->headers)->assertStatus(200);

    $this->post("/api/v1/memories/uploads/{$uploadKey}/chunk", [
      'chunkIndex' => 1,
      'chunk' => $chunk1,
    ], $this->headers)->assertStatus(200);

    $poster = UploadedFile::fake()->image('poster.jpg', 320, 180);

    $publishResponse = $this->post('/api/v1/memories', [
      'id' => 'xv-nat-chunked-video-1',
      'eventCode' => 'XV-NATALIA',
      'guestName' => 'Invitado Test',
      'tableCode' => 'Mesa 01 · Principal',
      'caption' => 'Entrada completa de la quinceañera',
      'uploadedKeys' => [$uploadKey],
      'fileOrder' => ["upload:{$uploadKey}"],
      'posters' => [$poster],
    ], $this->headers);

    $publishResponse->assertStatus(201);
    $publishResponse->assertJsonPath('data.mediaType', 'video');
    $publishResponse->assertJsonPath('data.mediaItems.0.mediaType', 'video');

    $this->assertDatabaseHas('memories', [
      'external_id' => 'xv-nat-chunked-video-1',
      'media_type' => 'video',
    ]);

    $this->assertDatabaseHas('memory_media_items', [
      'byte_size' => 3072,
      'media_type' => 'video',
    ]);

    Storage::disk('local')->assertMissing("chunk_uploads/{$uploadKey}/meta.json");
  }

  public function test_init_rejects_forbidden_extensions_even_for_chunked_uploads(): void
  {
    $this->postJson('/api/v1/memories/uploads/init', [
      'filename' => 'animacion.gif',
      'mimeType' => 'image/gif',
      'totalSize' => 5242880,
      'totalChunks' => 1,
    ], $this->headers)->assertStatus(422);

    $this->postJson('/api/v1/memories/uploads/init', [
      'filename' => 'documento.pdf',
      'mimeType' => 'application/pdf',
      'totalSize' => 5242880,
      'totalChunks' => 1,
    ], $this->headers)->assertStatus(422);
  }
}
