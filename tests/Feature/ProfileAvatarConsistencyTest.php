<?php

namespace Tests\Feature;

use App\Http\Resources\Api\V1\EventTableResource;
use App\Http\Resources\Api\V1\MemoryResource;
use App\Models\DeviceLock;
use App\Models\Event;
use App\Models\EventTable;
use App\Models\Memory;
use App\Models\MemoryComment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfileAvatarConsistencyTest extends TestCase
{
  use RefreshDatabase;

  protected function setUp(): void
  {
    parent::setUp();
    $this->seed();
    Storage::fake('public');
  }

  public function test_avatar_and_cover_are_distinct_and_propagate_to_feed_and_comments(): void
  {
    // 1. Check in Andres JH
    $this->postJson('/api/v1/checkin', [
      'eventCode' => 'XV-NATALIA',
      'tableCode' => 'Mesa 01 · Principal',
      'guestName' => 'Andres JH',
      'deviceId' => 'dev-andres-unique-1',
      'actorKey' => 'actor-andres-unique-1',
    ])->assertStatus(201);

    $lock = DeviceLock::where('guest_name', 'Andres JH')->firstOrFail();

    // 2. Upload distinct avatar and cover
    $avatarFile = UploadedFile::fake()->image('andres_avatar.jpg', 500, 500);
    $coverFile = UploadedFile::fake()->image('andres_cover.jpg', 1200, 400);

    $profileRes = $this->withHeaders([
      'X-Device-Id' => 'dev-andres-unique-1',
      'X-Actor-Key' => 'actor-andres-unique-1',
    ])->post('/api/v1/profile', [
      'avatar' => $avatarFile,
      'cover' => $coverFile,
      'bio' => 'Programador y amigo de la familia',
      'location' => 'Maracaibo, Venezuela',
    ]);

    $profileRes->assertStatus(200);

    $avatarUrl = $profileRes->json('data.avatarUrl');
    $coverUrl = $profileRes->json('data.coverUrl');

    $this->assertNotEmpty($avatarUrl);
    $this->assertNotEmpty($coverUrl);
    $this->assertNotEquals($avatarUrl, $coverUrl, 'Avatar and cover URLs must be strictly different.');
    $this->assertStringContainsString('/avatar', $avatarUrl);
    $this->assertStringContainsString('/cover', $coverUrl);
    $this->assertStringContainsString('?v=', $avatarUrl, 'Avatar URL should have cache-busting timestamp version.');

    // 3. Publish memory by Andres JH
    $photoFile = UploadedFile::fake()->image('recuerdo.jpg');
    $publishRes = $this->withHeaders([
      'X-Device-Id' => 'dev-andres-unique-1',
      'X-Actor-Key' => 'actor-andres-unique-1',
    ])->postJson('/api/v1/memories', [
      'eventCode' => 'XV-NATALIA',
      'guestName' => 'Andres JH',
      'tableCode' => 'Mesa 01 · Principal',
      'ownerDeviceId' => 'dev-andres-unique-1',
      'ownerActorKey' => 'actor-andres-unique-1',
      'caption' => 'Con la quinceañera!',
      'files' => [$photoFile],
    ]);

    $publishRes->assertStatus(201);
    $memoryId = $publishRes->json('data.id');

    // 4. Feed memory resource must have avatarUrl populated
    $feedRes = $this->getJson('/api/v1/memories?eventCode=XV-NATALIA');
    $feedRes->assertStatus(200);

    $feedItems = $feedRes->json('data');
    $this->assertNotEmpty($feedItems);
    $targetMemory = collect($feedItems)->firstWhere('guestName', 'Andres JH');
    $this->assertNotNull($targetMemory, 'Memory published by Andres JH should be in feed.');
    $this->assertEquals($avatarUrl, $targetMemory['avatarUrl'], 'Feed card author must have the resolved avatar URL, not initials.');

    // 5. Test comment with avatar: Another guest creates a memory, Andres JH comments on it
    $this->postJson('/api/v1/checkin', [
      'eventCode' => 'XV-NATALIA',
      'tableCode' => 'Mesa 02 · Orquídea',
      'guestName' => 'Sofia Perez',
      'deviceId' => 'dev-sofia-1',
      'actorKey' => 'actor-sofia-1',
    ])->assertStatus(201);

    $sofiaPhoto = UploadedFile::fake()->image('sofia_photo.jpg');
    $sofiaMemoryRes = $this->withHeaders([
      'X-Device-Id' => 'dev-sofia-1',
      'X-Actor-Key' => 'actor-sofia-1',
    ])->postJson('/api/v1/memories', [
      'eventCode' => 'XV-NATALIA',
      'guestName' => 'Sofia Perez',
      'tableCode' => 'Mesa 02 · Orquídea',
      'ownerDeviceId' => 'dev-sofia-1',
      'ownerActorKey' => 'actor-sofia-1',
      'caption' => 'Bailando!',
      'files' => [$sofiaPhoto],
    ]);
    $sofiaMemoryId = $sofiaMemoryRes->json('data.id');

    // Andres JH comments on Sofia's memory
    $commentRes = $this->withHeaders([
      'X-Device-Id' => 'dev-andres-unique-1',
      'X-Actor-Key' => 'actor-andres-unique-1',
    ])->postJson("/api/v1/memories/{$sofiaMemoryId}/comments", [
      'author' => 'Andres JH',
      'table' => 'Mesa 01 · Principal',
      'text' => 'Que buen recuerdo!',
    ]);

    $commentRes->assertStatus(201);
    $commentAvatarUrl = $commentRes->json('data.avatarUrl');
    $this->assertEquals($avatarUrl, $commentAvatarUrl, 'Comment by Andres JH must render his avatarUrl.');

    // 6. Check Table Resource: guests in table must contain avatarUrl
    $table = EventTable::where('event_id', $lock->event_id)
      ->where('code', 'Mesa 01 · Principal')
      ->with('deviceLocks')
      ->firstOrFail();
    $tableResource = (new EventTableResource($table))->toArray(request());

    $this->assertArrayHasKey('guests', $tableResource);
    $tableGuest = collect($tableResource['guests'])->firstWhere('guestName', 'Andres JH');
    $this->assertNotNull($tableGuest);
    $this->assertEquals($avatarUrl, $tableGuest['avatarUrl'], 'Table guest list must include avatarUrl for participant list.');
  }
}
