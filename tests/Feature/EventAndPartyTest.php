<?php

namespace Tests\Feature;

use App\Models\Event;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventAndPartyTest extends TestCase
{
  use RefreshDatabase;

  protected function setUp(): void
  {
    parent::setUp();
    $this->seed();
  }

  public function test_can_get_event_state(): void
  {
    $response = $this->getJson('/api/v1/event');

    $response->assertStatus(200)
      ->assertJsonPath('data.eventCode', 'XV-NATALIA')
      ->assertJsonPath('data.honoreeName', 'Natalia Isabella');
  }

  public function test_can_toggle_party_celebrate(): void
  {
    $payload = [
      'eventCode' => 'XV-NATALIA',
      'actorKey' => 'test-actor-1',
      'deviceId' => 'dev-test-1',
    ];

    // Without active QR checkin -> 403
    $this->postJson('/api/v1/event/celebrate', $payload)->assertStatus(403);

    // Checkin guest to activate QR session
    $this->postJson('/api/v1/checkin', [
      'eventCode' => 'XV-NATALIA',
      'tableCode' => 'Mesa 01 · Principal',
      'guestName' => 'Invitado Uno',
      'deviceId' => 'dev-test-1',
      'actorKey' => 'test-actor-1',
    ])->assertStatus(201);

    // First celebrate -> count 1
    $res1 = $this->withHeaders(['X-Device-Id' => 'dev-test-1'])
      ->postJson('/api/v1/event/celebrate', $payload);
    $res1->assertStatus(200)
      ->assertJsonPath('data.count', 1)
      ->assertJsonPath('data.reacted', true);

    // Second celebrate by same actor -> toggle off -> count 0
    $res2 = $this->withHeaders(['X-Device-Id' => 'dev-test-1'])
      ->postJson('/api/v1/event/celebrate', $payload);
    $res2->assertStatus(200)
      ->assertJsonPath('data.count', 0)
      ->assertJsonPath('data.reacted', false);
  }
}
