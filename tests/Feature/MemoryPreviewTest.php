<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MemoryPreviewTest extends TestCase
{
  use RefreshDatabase;

  protected function setUp(): void
  {
    parent::setUp();
    $this->seed();
    Storage::fake('public');
  }

  public function test_preview_endpoint_returns_clean_json_metadata(): void
  {
    $this->postJson('/api/v1/checkin', [
      'eventCode' => 'XV-NATALIA',
      'tableCode' => 'Mesa 01 · Principal',
      'guestName' => 'Carlos Mendoza',
      'deviceId' => 'dev-carlos',
      'actorKey' => 'actor-carlos',
    ])->assertStatus(201);

    $file = UploadedFile::fake()->image('preview.jpg');
    $memRes = $this->withHeaders(['X-Device-Id' => 'dev-carlos'])
      ->postJson('/api/v1/memories', [
        'guestName' => 'Carlos Mendoza',
        'tableCode' => 'Mesa 01 · Principal',
        'caption' => 'Momento inolvidable con la quinceañera',
        'files' => [$file],
      ]);
    $memRes->assertStatus(201);
    $externalId = $memRes->json('data.id');

    $response = $this->getJson("/api/v1/memories/{$externalId}/preview");

    $response->assertStatus(200);
    $response->assertHeader('Content-Type', 'application/json');
    $response->assertJson([
      'external_id' => $externalId,
      'title' => 'Recuerdo de Carlos Mendoza · 15 Años de Natalia Isabella',
      'description' => '"Momento inolvidable con la quinceañera"',
    ]);

    $this->assertNotEmpty($response->json('image_url'));
  }

  public function test_preview_endpoint_returns_404_for_non_existent_memory(): void
  {
    $response = $this->getJson('/api/v1/memories/xv-nat-non-existent/preview');
    $response->assertStatus(404);
  }
}
