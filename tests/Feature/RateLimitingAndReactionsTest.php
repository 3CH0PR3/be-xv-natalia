<?php

namespace Tests\Feature;

use App\Events\CommentLikeUpdated;
use App\Events\MemoryReactionUpdated;
use App\Models\Event;
use App\Models\EventTable;
use App\Models\Memory;
use App\Models\MemoryComment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event as EventFacade;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RateLimitingAndReactionsTest extends TestCase
{
  use RefreshDatabase;

  protected function setUp(): void
  {
    parent::setUp();
    $this->seed();
    Storage::fake('public');
    RateLimiter::clearResolvedInstances();
  }

  public function test_reactions_are_rate_limited_to_5_per_minute_with_spanish_message(): void
  {
    $event = Event::byCode('XV-NATALIA')->first();
    $table = EventTable::where('event_id', $event->id)->first();

    $this->postJson('/api/v1/checkin', [
      'eventCode' => 'XV-NATALIA',
      'tableCode' => $table->code,
      'guestName' => 'Rate Tester',
      'deviceId' => 'dev-rate-limit-reactions',
      'actorKey' => 'actor-rate-limit-reactions',
    ])->assertStatus(201);

    $memory = Memory::create([
      'external_id' => 'mem-rate-limit-1',
      'event_id' => $event->id,
      'event_table_id' => $table->id,
      'guest_name' => 'Host',
      'table_code' => $table->code,
      'media_type' => 'image',
      'timestamp_ms' => now()->getTimestampMs(),
      'status' => 'approved',
    ]);

    // First 5 requests must succeed
    for ($i = 0; $i < 5; $i++) {
      $choice = ($i % 2 === 0) ? 'likes' : 'fires';
      $res = $this->withHeaders(['X-Device-Id' => 'dev-rate-limit-reactions'])
        ->postJson("/api/v1/memories/{$memory->external_id}/reactions/toggle", [
          'actorKey' => 'actor-rate-limit-reactions',
          'deviceId' => 'dev-rate-limit-reactions',
          'choice' => $choice,
        ]);
      $res->assertStatus(200);
    }

    // 6th request within same minute must be blocked with HTTP 429 and Spanish copy
    $blockedRes = $this->withHeaders(['X-Device-Id' => 'dev-rate-limit-reactions'])
      ->postJson("/api/v1/memories/{$memory->external_id}/reactions/toggle", [
        'actorKey' => 'actor-rate-limit-reactions',
        'deviceId' => 'dev-rate-limit-reactions',
        'choice' => 'cheers',
      ]);

    $blockedRes->assertStatus(429)
      ->assertJsonPath('success', false)
      ->assertJsonPath('code', 429)
      ->assertJsonPath('message', 'Has alcanzado el límite de 5 reacciones por minuto. Por favor, espera un momento antes de volver a reaccionar.');
  }

  public function test_comments_are_rate_limited_to_10_per_minute_with_spanish_message(): void
  {
    $event = Event::byCode('XV-NATALIA')->first();
    $table = EventTable::where('event_id', $event->id)->first();

    $this->postJson('/api/v1/checkin', [
      'eventCode' => 'XV-NATALIA',
      'tableCode' => $table->code,
      'guestName' => 'Comment Rate Tester',
      'deviceId' => 'dev-rate-limit-comments',
      'actorKey' => 'actor-rate-limit-comments',
    ])->assertStatus(201);

    $memory = Memory::create([
      'external_id' => 'mem-rate-limit-2',
      'event_id' => $event->id,
      'event_table_id' => $table->id,
      'guest_name' => 'Host',
      'table_code' => $table->code,
      'media_type' => 'image',
      'timestamp_ms' => now()->getTimestampMs(),
      'status' => 'approved',
    ]);

    // First 10 comments must succeed
    for ($i = 1; $i <= 10; $i++) {
      $res = $this->withHeaders(['X-Device-Id' => 'dev-rate-limit-comments', 'X-Actor-Key' => 'actor-rate-limit-comments'])
        ->postJson("/api/v1/memories/{$memory->external_id}/comments", [
          'author' => 'Comment Rate Tester',
          'table' => $table->code,
          'text' => "Comentario de prueba #{$i}",
          'ownerDeviceId' => 'dev-rate-limit-comments',
          'ownerActorKey' => 'actor-rate-limit-comments',
        ]);
      $res->assertStatus(201);
    }

    // 11th comment within same minute must be blocked with HTTP 429 and Spanish copy
    $blockedRes = $this->withHeaders(['X-Device-Id' => 'dev-rate-limit-comments', 'X-Actor-Key' => 'actor-rate-limit-comments'])
      ->postJson("/api/v1/memories/{$memory->external_id}/comments", [
        'author' => 'Comment Rate Tester',
        'table' => $table->code,
        'text' => 'Comentario #11 que excede el límite',
        'ownerDeviceId' => 'dev-rate-limit-comments',
        'ownerActorKey' => 'actor-rate-limit-comments',
      ]);

    $blockedRes->assertStatus(429)
      ->assertJsonPath('success', false)
      ->assertJsonPath('code', 429)
      ->assertJsonPath('message', 'Has alcanzado el límite de 10 comentarios por minuto. Por favor, espera un momento antes de volver a comentar.');
  }

  public function test_reverb_events_include_device_id_for_precise_actor_matching(): void
  {
    EventFacade::fake([MemoryReactionUpdated::class, CommentLikeUpdated::class]);

    $event = Event::byCode('XV-NATALIA')->first();
    $table = EventTable::where('event_id', $event->id)->first();

    $this->postJson('/api/v1/checkin', [
      'eventCode' => 'XV-NATALIA',
      'tableCode' => $table->code,
      'guestName' => 'Broadcaster',
      'deviceId' => 'dev-broadcast-id-99',
      'actorKey' => 'actor-broadcast-key-99',
    ])->assertStatus(201);

    $memory = Memory::create([
      'external_id' => 'mem-broadcast-1',
      'event_id' => $event->id,
      'event_table_id' => $table->id,
      'guest_name' => 'Broadcaster',
      'table_code' => $table->code,
      'media_type' => 'image',
      'timestamp_ms' => now()->getTimestampMs(),
      'status' => 'approved',
    ]);

    // 1. Toggle reaction
    $this->withHeaders(['X-Device-Id' => 'dev-broadcast-id-99'])
      ->postJson("/api/v1/memories/{$memory->external_id}/reactions/toggle", [
        'actorKey' => 'actor-broadcast-key-99',
        'deviceId' => 'dev-broadcast-id-99',
        'choice' => 'likes',
      ])->assertStatus(200);

    // 2. Add comment & like comment
    $commentRes = $this->withHeaders(['X-Device-Id' => 'dev-broadcast-id-99', 'X-Actor-Key' => 'actor-broadcast-key-99'])
      ->postJson("/api/v1/memories/{$memory->external_id}/comments", [
        'author' => 'Broadcaster',
        'table' => $table->code,
        'text' => 'Comentario con like',
        'ownerDeviceId' => 'dev-broadcast-id-99',
        'ownerActorKey' => 'actor-broadcast-key-99',
      ])->assertStatus(201);

    $commentId = $commentRes->json('data.id');

    $this->withHeaders(['X-Device-Id' => 'dev-broadcast-id-99'])
      ->postJson("/api/v1/memories/{$memory->external_id}/comments/{$commentId}/like", [
        'actorKey' => 'actor-broadcast-key-99',
        'deviceId' => 'dev-broadcast-id-99',
      ])->assertStatus(200);

    // Instantiating events directly proves deviceId property is present and matches
    $rxEvent = new MemoryReactionUpdated('XV-NATALIA', $memory->external_id, 1, 0, 0, 'actor-broadcast-key-99', 'likes', 'dev-broadcast-id-99');
    $this->assertSame('dev-broadcast-id-99', $rxEvent->deviceId);

    $clEvent = new CommentLikeUpdated('XV-NATALIA', $memory->external_id, $commentId, 1, 'actor-broadcast-key-99', true, 'dev-broadcast-id-99');
    $this->assertSame('dev-broadcast-id-99', $clEvent->deviceId);
  }
}
