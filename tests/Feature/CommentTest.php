<?php

namespace Tests\Feature;

use App\Models\Memory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CommentTest extends TestCase
{
  use RefreshDatabase;

  protected function setUp(): void
  {
    parent::setUp();
    $this->seed();
    Storage::fake('public');
  }

  public function test_can_create_update_and_like_comment(): void
  {
    // 1. Check in Ana
    $this->postJson('/api/v1/checkin', [
      'eventCode' => 'XV-NATALIA',
      'tableCode' => 'Mesa 01 · Principal',
      'guestName' => 'Ana',
      'deviceId' => 'dev-ana',
      'actorKey' => 'actor-ana',
    ])->assertStatus(201);

    $file = UploadedFile::fake()->image('photo.jpg');
    $memRes = $this->withHeaders(['X-Device-Id' => 'dev-ana'])
      ->postJson('/api/v1/memories', [
        'guestName' => 'Ana',
        'tableCode' => 'Mesa 01 · Principal',
        'ownerDeviceId' => 'dev-ana',
        'ownerActorKey' => 'actor-ana',
        'files' => [$file],
      ]);
    $memRes->assertStatus(201);
    $memoryId = $memRes->json('data.id');

    // 2. Check in Pedro
    $this->postJson('/api/v1/checkin', [
      'eventCode' => 'XV-NATALIA',
      'tableCode' => 'Mesa 02 · Orquídea',
      'guestName' => 'Pedro',
      'deviceId' => 'dev-pedro',
      'actorKey' => 'actor-pedro',
    ])->assertStatus(201);

    // Create comment without device lock header/active session -> verified working with dev-pedro
    $commentRes = $this->withHeaders(['X-Device-Id' => 'dev-pedro', 'X-Actor-Key' => 'actor-pedro'])
      ->postJson("/api/v1/memories/{$memoryId}/comments", [
        'author' => 'Pedro',
        'table' => 'Mesa 02 · Orquídea',
        'text' => 'Qué hermosa foto!',
        'ownerDeviceId' => 'dev-pedro',
        'ownerActorKey' => 'actor-pedro',
      ]);
    $commentRes->assertStatus(201)
      ->assertJsonPath('data.author', 'Pedro')
      ->assertJsonPath('data.text', 'Qué hermosa foto!');

    $commentId = $commentRes->json('data.id');

    // Check comments count in memory (Observer recount)
    $this->assertEquals(1, Memory::where('external_id', $memoryId)->first()->comments_count);

    // Update comment
    $updateRes = $this->withHeaders([
      'X-Device-Id' => 'dev-pedro',
      'X-Actor-Key' => 'actor-pedro',
    ])->patchJson("/api/v1/memories/{$memoryId}/comments/{$commentId}", [
      'text' => 'Comentario editado con más cariño!',
    ]);
    $updateRes->assertStatus(200)
      ->assertJsonPath('data.text', 'Comentario editado con más cariño!');

    // Check in liker
    $this->postJson('/api/v1/checkin', [
      'eventCode' => 'XV-NATALIA',
      'tableCode' => 'Mesa 03 · Magnolia',
      'guestName' => 'Liker Juan',
      'deviceId' => 'dev-liker',
      'actorKey' => 'actor-liker',
    ])->assertStatus(201);

    // Like comment
    $likeRes = $this->withHeaders(['X-Device-Id' => 'dev-liker'])
      ->postJson("/api/v1/memories/{$memoryId}/comments/{$commentId}/like", [
        'actorKey' => 'actor-liker',
        'deviceId' => 'dev-liker',
      ]);
    $likeRes->assertStatus(200)
      ->assertJsonPath('data.likes', 1)
      ->assertJsonPath('data.liked', true);

    // Delete comment
    $delRes = $this->withHeaders([
      'X-Device-Id' => 'dev-pedro',
      'X-Actor-Key' => 'actor-pedro',
    ])->deleteJson("/api/v1/memories/{$memoryId}/comments/{$commentId}");
    $delRes->assertStatus(200);

    // Check comments count updated automatically by observer
    $this->assertEquals(0, Memory::where('external_id', $memoryId)->first()->comments_count);
  }

  public function test_multiple_comments_consecutive_likes_and_unlikes_maintain_exact_count(): void
  {
    $this->postJson('/api/v1/checkin', [
      'eventCode' => 'XV-NATALIA',
      'tableCode' => 'Mesa 01 · Principal',
      'guestName' => 'Host',
      'deviceId' => 'dev-host',
      'actorKey' => 'actor-host',
    ])->assertStatus(201);

    $file = UploadedFile::fake()->image('test.jpg');
    $memRes = $this->withHeaders(['X-Device-Id' => 'dev-host'])
      ->postJson('/api/v1/memories', [
        'guestName' => 'Host',
        'tableCode' => 'Mesa 01 · Principal',
        'ownerDeviceId' => 'dev-host',
        'ownerActorKey' => 'actor-host',
        'files' => [$file],
      ]);
    $memoryId = $memRes->json('data.id');

    $commentIds = [];
    for ($i = 1; $i <= 4; $i++) {
      $cRes = $this->withHeaders(['X-Device-Id' => 'dev-host', 'X-Actor-Key' => 'actor-host'])
        ->postJson("/api/v1/memories/{$memoryId}/comments", [
          'author' => "Invitado {$i}",
          'table' => 'Mesa 01 · Principal',
          'text' => "Comentario número {$i}",
          'ownerDeviceId' => 'dev-host',
          'ownerActorKey' => 'actor-host',
        ]);
      $commentIds[] = $cRes->json('data.id');
    }

    // Liker 1 likes all 4 comments consecutively
    foreach ($commentIds as $cId) {
      $res = $this->withHeaders(['X-Device-Id' => 'dev-host'])
        ->postJson("/api/v1/memories/{$memoryId}/comments/{$cId}/like", [
          'actorKey' => 'actor-host',
          'deviceId' => 'dev-host',
        ]);
      $res->assertStatus(200)
        ->assertJsonPath('data.likes', 1)
        ->assertJsonPath('data.liked', true);
    }

    // Verify index returns exactly 1 like on all 4 comments
    $listRes = $this->withHeaders(['X-Device-Id' => 'dev-host'])
      ->getJson("/api/v1/memories/{$memoryId}/comments");
    $listRes->assertStatus(200);
    foreach ($listRes->json('data') as $commentItem) {
      $this->assertEquals(1, $commentItem['likes']);
      $this->assertTrue($commentItem['likedByMe']);
    }

    // Liker 1 unlikes comment 1
    $unlikeRes = $this->withHeaders(['X-Device-Id' => 'dev-host'])
      ->postJson("/api/v1/memories/{$memoryId}/comments/{$commentIds[0]}/like", [
        'actorKey' => 'actor-host',
        'deviceId' => 'dev-host',
      ]);
    $unlikeRes->assertStatus(200)
      ->assertJsonPath('data.likes', 0)
      ->assertJsonPath('data.liked', false);

    // Liker 2 checks in and likes comment 1 and comment 2
    $this->postJson('/api/v1/checkin', [
      'eventCode' => 'XV-NATALIA',
      'tableCode' => 'Mesa 02 · Orquídea',
      'guestName' => 'Invitado 2',
      'deviceId' => 'dev-user2',
      'actorKey' => 'actor-user2',
    ])->assertStatus(201);

    $like2Res = $this->withHeaders(['X-Device-Id' => 'dev-user2'])
      ->postJson("/api/v1/memories/{$memoryId}/comments/{$commentIds[0]}/like", [
        'actorKey' => 'actor-user2',
        'deviceId' => 'dev-user2',
      ]);
    $like2Res->assertStatus(200)
      ->assertJsonPath('data.likes', 1)
      ->assertJsonPath('data.liked', true);

    // Comment 2 was liked by host, now liked by user2 -> should be 2
    $likeComment2Res = $this->withHeaders(['X-Device-Id' => 'dev-user2'])
      ->postJson("/api/v1/memories/{$memoryId}/comments/{$commentIds[1]}/like", [
        'actorKey' => 'actor-user2',
        'deviceId' => 'dev-user2',
      ]);
    $likeComment2Res->assertStatus(200)
      ->assertJsonPath('data.likes', 2)
      ->assertJsonPath('data.liked', true);
  }
}
