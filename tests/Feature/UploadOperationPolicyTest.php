<?php

namespace Tests\Feature;

use App\Models\UploadOperationFile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class UploadOperationPolicyTest extends TestCase
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
      'guestName' => 'Policy Tester',
      'deviceId' => 'dev_policy_test',
      'actorKey' => 'mesa-01-principal__policy-tester',
    ])->assertStatus(201);

    $this->headers = [
      'X-Device-Id' => 'dev_policy_test',
      'X-Actor-Key' => 'mesa-01-principal__policy-tester',
    ];
  }

  public function test_create_operation_rejects_oversized_files_with_file_too_large_while_keeping_valid_files(): void
  {
    $oversizedBytes = 2 * 1024 * 1024 * 1024; // 2 GB

    $response = $this->postJson('/api/v1/upload-operations', [
      'caption' => 'Prueba de límites',
      'files' => [
        [
          'fileKey' => 'vid-2gb',
          'originalName' => 'video_1_hora.mp4',
          'mimeType' => 'video/mp4',
          'byteSize' => $oversizedBytes,
          'chunkSize' => 8 * 1024 * 1024,
          'totalChunks' => 256,
        ],
        [
          'fileKey' => 'img-ok',
          'originalName' => 'foto_valida.jpg',
          'mimeType' => 'image/jpeg',
          'byteSize' => 2 * 1024 * 1024,
          'chunkSize' => 2 * 1024 * 1024,
          'totalChunks' => 1,
        ],
      ],
    ], $this->headers);

    $response->assertStatus(201);
    $files = collect($response->json('data.files'));

    $failed = $files->firstWhere('fileKey', 'vid-2gb');
    $valid = $files->firstWhere('fileKey', 'img-ok');

    $this->assertSame(UploadOperationFile::STATUS_FAILED, $failed['status']);
    $this->assertSame('FILE_TOO_LARGE', $failed['errorCode']);
    $this->assertStringContainsString('700 MB', $failed['errorMessage']);

    $this->assertSame(UploadOperationFile::STATUS_QUEUED_UPLOAD, $valid['status']);
    $this->assertNull($valid['errorCode']);
  }
}
