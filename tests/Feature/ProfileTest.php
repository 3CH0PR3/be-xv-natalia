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

    // Guest 2 views Guest 1's profile by name slug
    $slugRes = $this->withHeaders([
      'X-Device-Id' => 'dev-other-viewer',
      'X-Actor-Key' => 'actor-other-viewer',
    ])->getJson('/api/v1/profile/andres-hernandez');

    $slugRes->assertStatus(200)
      ->assertJsonPath('data.guestName', 'Andres Hernandez')
      ->assertJsonPath('data.stats.totalMemories', 1);
  }

  public function test_profile_returns_404_for_unregistered_guest_or_missing_profile(): void
  {
    // 1. Consulta pública por slug 'invitado' sin registros previos debe dar 404
    $invitadoRes = $this->getJson('/api/v1/profile/invitado');
    $invitadoRes->assertStatus(404);

    // 2. Consulta pública por nombre de usuario sin fotos ni registro debe dar 404
    $namedRes = $this->getJson('/api/v1/profile/valeria-gomez');
    $namedRes->assertStatus(404);

    // 3. Consulta de perfil propio sin sesión activa debe dar 404
    $unauthedRes = $this->getJson('/api/v1/profile');
    $unauthedRes->assertStatus(404);

    // 4. Consulta de perfil propio con dispositivo no registrado en device_locks debe dar 404
    $unregisteredDeviceRes = $this->withHeaders([
      'X-Device-Id' => 'dev-random-unregistered-device-1234',
    ])->getJson('/api/v1/profile');
    $unregisteredDeviceRes->assertStatus(404);
  }

  public function test_profile_update_cascades_to_memories_and_comments(): void
  {
    // 1. Check in guest with initial name
    $this->postJson('/api/v1/checkin', [
      'eventCode' => 'XV-NATALIA',
      'tableCode' => 'Mesa 01 · Principal',
      'guestName' => 'Andres Original',
      'deviceId' => 'dev-andres-cascade',
      'actorKey' => 'actor-andres-cascade',
    ])->assertStatus(201);

    // 2. Publish a memory
    $photo = UploadedFile::fake()->image('recuerdo.jpg');
    $memRes = $this->withHeaders([
      'X-Device-Id' => 'dev-andres-cascade',
      'X-Actor-Key' => 'actor-andres-cascade',
    ])->postJson('/api/v1/memories', [
      'eventCode' => 'XV-NATALIA',
      'tableCode' => 'Mesa 01 · Principal',
      'guestName' => 'Andres Original',
      'ownerDeviceId' => 'dev-andres-cascade',
      'ownerActorKey' => 'actor-andres-cascade',
      'caption' => 'Recuerdo antes de editar perfil',
      'files' => [$photo],
    ]);
    $memRes->assertStatus(201);
    $memoryId = $memRes->json('data.id');

    // 3. Post a comment on the memory
    $this->withHeaders([
      'X-Device-Id' => 'dev-andres-cascade',
      'X-Actor-Key' => 'actor-andres-cascade',
    ])->postJson("/api/v1/memories/{$memoryId}/comments", [
      'author' => 'Andres Original',
      'table' => 'Mesa 01 · Principal',
      'text' => 'Comentario con nombre original',
      'ownerDeviceId' => 'dev-andres-cascade',
      'ownerActorKey' => 'actor-andres-cascade',
    ])->assertStatus(201);

    // 4. Update profile name and surname
    $updateRes = $this->withHeaders([
      'X-Device-Id' => 'dev-andres-cascade',
      'X-Actor-Key' => 'actor-andres-cascade',
    ])->postJson('/api/v1/profile', [
      'guestName' => 'Andres Hernandez',
      'bio' => 'Perfil actualizado con nuevo apellido',
    ]);
    $updateRes->assertStatus(200)
      ->assertJsonPath('data.guestName', 'Andres Hernandez');

    // 5. Query feed / memories list: author must be Andres Hernandez
    $feedRes = $this->withHeaders([
      'X-Device-Id' => 'dev-viewer',
    ])->getJson('/api/v1/memories');
    $feedRes->assertStatus(200);
    $feedItems = $feedRes->json('data');
    $updatedMem = collect($feedItems)->firstWhere('id', $memoryId);
    $this->assertNotNull($updatedMem);
    $this->assertEquals('Andres Hernandez', $updatedMem['guestName']);

    // 6. Query comments list: author must be Andres Hernandez
    $commentsRes = $this->withHeaders([
      'X-Device-Id' => 'dev-viewer',
    ])->getJson("/api/v1/memories/{$memoryId}/comments");
    $commentsRes->assertStatus(200);
    $comments = $commentsRes->json('data');
    $this->assertCount(1, $comments);
    $this->assertEquals('Andres Hernandez', $comments[0]['author']);
  }

  public function test_can_get_paginated_profile_reactions(): void
  {
    $this->postJson('/api/v1/checkin', [
      'eventCode' => 'XV-NATALIA',
      'tableCode' => 'Mesa 01 · Principal',
      'guestName' => 'Mariana Torres',
      'deviceId' => 'dev-mariana-reactions',
      'actorKey' => 'actor-mariana-reactions',
    ])->assertStatus(201);

    $photo = UploadedFile::fake()->image('recuerdo.jpg');
    $memRes = $this->withHeaders([
      'X-Device-Id' => 'dev-mariana-reactions',
      'X-Actor-Key' => 'actor-mariana-reactions',
    ])->postJson('/api/v1/memories', [
      'eventCode' => 'XV-NATALIA',
      'tableCode' => 'Mesa 01 · Principal',
      'guestName' => 'Mariana Torres',
      'ownerDeviceId' => 'dev-mariana-reactions',
      'ownerActorKey' => 'actor-mariana-reactions',
      'caption' => 'Recuerdo con reacciones',
      'files' => [$photo],
    ]);
    $memRes->assertStatus(201);
    $memoryId = $memRes->json('data.id');

    $this->postJson('/api/v1/checkin', [
      'eventCode' => 'XV-NATALIA',
      'tableCode' => 'Mesa 01 · Principal',
      'guestName' => 'Roberto Carlos',
      'deviceId' => 'dev-roberto-reactions',
      'actorKey' => 'actor-roberto-reactions',
    ])->assertStatus(201);

    $this->withHeaders([
      'X-Device-Id' => 'dev-roberto-reactions',
      'X-Actor-Key' => 'actor-roberto-reactions',
    ])->postJson("/api/v1/memories/{$memoryId}/reactions/toggle", [
      'choice' => 'likes',
      'actorKey' => 'actor-roberto-reactions',
      'deviceId' => 'dev-roberto-reactions',
    ])->assertStatus(200);

    $myReactionsRes = $this->withHeaders([
      'X-Device-Id' => 'dev-mariana-reactions',
      'X-Actor-Key' => 'actor-mariana-reactions',
    ])->getJson('/api/v1/profile/reactions?page=1&per_page=7');

    $myReactionsRes->assertStatus(200)
      ->assertJsonPath('data.data.0.choice', 'likes')
      ->assertJsonPath('data.data.0.author', 'Roberto Carlos')
      ->assertJsonPath('data.pagination.currentPage', 1)
      ->assertJsonPath('data.pagination.perPage', 7)
      ->assertJsonPath('data.pagination.total', 1)
      ->assertJsonPath('data.pagination.hasMore', false);

    $publicReactionsRes = $this->withHeaders([
      'X-Device-Id' => 'dev-roberto-reactions',
      'X-Actor-Key' => 'actor-roberto-reactions',
    ])->getJson('/api/v1/profile/dev-mariana-reactions/reactions?page=1&per_page=7');

    $publicReactionsRes->assertStatus(200)
      ->assertJsonPath('data.data.0.choice', 'likes')
      ->assertJsonPath('data.data.0.author', 'Roberto Carlos')
      ->assertJsonPath('data.pagination.currentPage', 1)
      ->assertJsonPath('data.pagination.perPage', 7)
      ->assertJsonPath('data.pagination.total', 1);
  }
}
