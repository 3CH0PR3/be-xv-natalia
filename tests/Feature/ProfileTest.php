<?php

namespace Tests\Feature;

use App\Models\DeviceLock;
use App\Models\Event;
use App\Models\EventTable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfileTest extends TestCase
{
  use RefreshDatabase;

  protected function setUp(): void
  {
    parent::setUp();
    $this->seed();
    Storage::fake('public');
  }

  public function test_can_get_and_update_profile(): void
  {
    // 1. Check in guest
    $this->postJson('/api/v1/checkin', [
      'eventCode' => 'XV-NATALIA',
      'tableCode' => 'Mesa 01 · Principal',
      'guestName' => 'Mariana Torres',
      'deviceId' => 'dev-mariana-1',
      'actorKey' => 'actor-mariana-1',
    ])->assertStatus(201);

    // 2. Query my profile
    $res = $this->withHeaders([
      'X-Device-Id' => 'dev-mariana-1',
      'X-Actor-Key' => 'actor-mariana-1',
    ])->getJson('/api/v1/profile');

    $res->assertStatus(200)
      ->assertJsonPath('data.guestName', 'Mariana Torres')
      ->assertJsonPath('data.isOwnProfile', true);

    // 3. Update bio and location
    $updateRes = $this->withHeaders([
      'X-Device-Id' => 'dev-mariana-1',
      'X-Actor-Key' => 'actor-mariana-1',
    ])->postJson('/api/v1/profile', [
      'bio' => 'Amiga de Natalia desde primaria ✨',
      'location' => 'Caracas, Venezuela',
    ]);

    $updateRes->assertStatus(200)
      ->assertJsonPath('data.bio', 'Amiga de Natalia desde primaria ✨')
      ->assertJsonPath('data.location', 'Caracas, Venezuela');
  }

  public function test_can_upload_avatar_and_cover_image(): void
  {
    $this->postJson('/api/v1/checkin', [
      'eventCode' => 'XV-NATALIA',
      'tableCode' => 'Mesa 01 · Principal',
      'guestName' => 'Roberto Carlos',
      'deviceId' => 'dev-roberto-1',
      'actorKey' => 'actor-roberto-1',
    ])->assertStatus(201);

    $avatarFile = UploadedFile::fake()->image('avatar.jpg', 400, 400);
    $coverFile = UploadedFile::fake()->image('cover.png', 1200, 400);

    $uploadRes = $this->withHeaders([
      'X-Device-Id' => 'dev-roberto-1',
      'X-Actor-Key' => 'actor-roberto-1',
    ])->post('/api/v1/profile', [
      'avatar' => $avatarFile,
      'cover' => $coverFile,
      'bio' => 'Celebrando esta noche!',
      'location' => 'Valencia',
    ]);

    $uploadRes->assertStatus(200)
      ->assertJsonPath('data.guestName', 'Roberto Carlos')
      ->assertJsonPath('data.bio', 'Celebrando esta noche!');

    $avatarUrl = $uploadRes->json('data.avatarUrl');
    $coverUrl = $uploadRes->json('data.coverUrl');

    $this->assertNotEmpty($avatarUrl);
    $this->assertNotEmpty($coverUrl);
  }

  public function test_rejects_gif_and_pdf_profile_images(): void
  {
    $this->postJson('/api/v1/checkin', [
      'eventCode' => 'XV-NATALIA',
      'tableCode' => 'Mesa 01 · Principal',
      'guestName' => 'Elena Vasquez',
      'deviceId' => 'dev-elena-1',
      'actorKey' => 'actor-elena-1',
    ])->assertStatus(201);

    // Reject GIF
    $gifFile = UploadedFile::fake()->create('profile.gif', 500, 'image/gif');
    $this->withHeaders(['X-Device-Id' => 'dev-elena-1'])
      ->postJson('/api/v1/profile', ['avatar' => $gifFile])
      ->assertStatus(422)
      ->assertJsonValidationErrors(['avatar']);

    // Reject PDF
    $pdfFile = UploadedFile::fake()->create('document.pdf', 300, 'application/pdf');
    $this->withHeaders(['X-Device-Id' => 'dev-elena-1'])
      ->postJson('/api/v1/profile', ['cover' => $pdfFile])
      ->assertStatus(422)
      ->assertJsonValidationErrors(['cover']);
  }

  public function test_can_view_public_profile_of_another_guest(): void
  {
    // Checkin Guest 1
    $this->postJson('/api/v1/checkin', [
      'eventCode' => 'XV-NATALIA',
      'tableCode' => 'Mesa 01 · Principal',
      'guestName' => 'Andres Hernandez',
      'deviceId' => 'dev-andres-public',
      'actorKey' => 'actor-andres-public',
    ])->assertStatus(201);

    // Guest 1 publishes memory
    $photo = UploadedFile::fake()->image('foto1.jpg');
    $this->withHeaders(['X-Device-Id' => 'dev-andres-public'])
      ->postJson('/api/v1/memories', [
        'eventCode' => 'XV-NATALIA',
        'tableCode' => 'Mesa 01 · Principal',
        'guestName' => 'Andres Hernandez',
        'ownerDeviceId' => 'dev-andres-public',
        'ownerActorKey' => 'actor-andres-public',
        'caption' => 'Recuerdo de Andres',
        'files' => [$photo],
      ])->assertStatus(201);

    // Guest 2 views Guest 1's profile
    $res = $this->withHeaders([
      'X-Device-Id' => 'dev-other-viewer',
      'X-Actor-Key' => 'actor-other-viewer',
    ])->getJson('/api/v1/profile/dev-andres-public');

    $res->assertStatus(200)
      ->assertJsonPath('data.guestName', 'Andres Hernandez')
      ->assertJsonPath('data.isOwnProfile', false)
      ->assertJsonPath('data.stats.totalMemories', 1)
      ->assertJsonCount(1, 'data.memories');
  }
}
