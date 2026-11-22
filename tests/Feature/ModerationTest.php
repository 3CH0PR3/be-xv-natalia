<?php

namespace Tests\Feature;

use App\Models\Event;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ModerationTest extends TestCase
{
  use RefreshDatabase;

  protected function setUp(): void
  {
    parent::setUp();
    $this->seed();
    Storage::fake('public');
  }

  public function test_verify_pin(): void
  {
    // Correct pin
    $this->postJson('/api/v1/moderation/verify-pin', ['pin' => '1515'])
      ->assertStatus(200)
      ->assertJsonPath('data.valid', true);

    // Wrong pin
    $this->postJson('/api/v1/moderation/verify-pin', ['pin' => '9999'])
      ->assertStatus(403);
  }

  public function test_moderation_metrics(): void
  {
    // Without header -> forbidden
    $this->getJson('/api/v1/moderation/metrics')->assertStatus(403);

    // With header -> ok
    $this->withHeaders(['X-Host-Pin' => '1515'])
      ->getJson('/api/v1/moderation/metrics')
      ->assertStatus(200)
      ->assertJsonStructure([
        'data' => [
          'totalMemories',
          'approvedMemories',
          'connectedGuests',
          'partyCelebrations',
        ],
      ]);
  }

  public function test_can_expel_guest(): void
  {
    $checkinPayload = [
      'eventCode' => 'XV-NATALIA',
      'tableCode' => 'Mesa 01 · Principal',
      'guestName' => 'Invitado Problematico',
      'deviceId' => 'dev-bad-1',
      'hwFingerprint' => 'v2_bad_device_fp',
    ];
    $this->postJson('/api/v1/checkin', $checkinPayload)->assertStatus(201);

    $expelPayload = [
      'eventCode' => 'XV-NATALIA',
      'tableId' => 'mesa-01',
      'deviceId' => 'dev-bad-1',
    ];

    $this->withHeaders(['X-Host-Pin' => '1515'])
      ->postJson('/api/v1/moderation/tables/expel', $expelPayload)
      ->assertStatus(200);

    $this->assertDatabaseHas('device_locks', [
      'device_id' => 'dev-bad-1',
      'event_table_id' => null,
    ]);
  }

  public function test_can_purge_event_data(): void
  {
    // Without header -> forbidden
    $this->postJson('/api/v1/moderation/purge')->assertStatus(403);

    // With header -> ok
    $res = $this->withHeaders(['X-Host-Pin' => '1515'])
      ->postJson('/api/v1/moderation/purge')
      ->assertStatus(200)
      ->assertJsonPath('data.purged', true);

    $event = Event::byCode('XV-NATALIA')->first();
    $this->assertGreaterThan(1, $event->epoch);
    $this->assertStringStartsWith('laravel-', $event->db_scope);
  }
}
