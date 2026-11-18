<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventTable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TableAndCheckinTest extends TestCase
{
  use RefreshDatabase;

  protected function setUp(): void
  {
    parent::setUp();
    $this->seed();
  }

  public function test_can_list_tables(): void
  {
    $response = $this->getJson('/api/v1/tables');

    $response->assertStatus(200)
      ->assertJsonCount(6, 'data');
  }

  public function test_can_checkin_guest(): void
  {
    $payload = [
      'eventCode' => 'XV-NATALIA',
      'tableCode' => 'Mesa 01 · Principal',
      'guestName' => 'Carlos Mendoza',
      'deviceId' => 'device-12345',
      'hwFingerprint' => 'v2_abcd1234efgh5678',
      'candidateFingerprints' => ['v2_abcd1234efgh5678'],
    ];

    $response = $this->postJson('/api/v1/checkin', $payload);

    $response->assertStatus(201)
      ->assertJsonPath('data.guestName', 'Carlos Mendoza')
      ->assertJsonPath('data.deviceId', 'device-12345');

    $this->assertDatabaseHas('device_locks', [
      'device_id' => 'device-12345',
      'guest_name' => 'Carlos Mendoza',
    ]);

    $this->assertDatabaseHas('device_fingerprint_index', [
      'fingerprint' => 'v2_abcd1234efgh5678',
    ]);
  }

  public function test_cannot_register_duplicate_name_in_same_table(): void
  {
    $payload = [
      'eventCode' => 'XV-NATALIA',
      'tableCode' => 'Mesa 01 · Principal',
      'guestName' => 'Carlos Mendoza',
      'deviceId' => 'device-1',
      'hwFingerprint' => 'v2_fp1111111111',
    ];
    $this->postJson('/api/v1/checkin', $payload)->assertStatus(201);

    // Same name, different device on same table
    $payload2 = [
      'eventCode' => 'XV-NATALIA',
      'tableCode' => 'Mesa 01 · Principal',
      'guestName' => 'Carlos Mendoza',
      'deviceId' => 'device-2',
      'hwFingerprint' => 'v2_fp2222222222',
    ];
    $res = $this->postJson('/api/v1/checkin', $payload2);
    $res->assertStatus(409)
      ->assertJsonPath('code', 'NAME_ALREADY_TAKEN');
  }

  public function test_cannot_change_to_another_table(): void
  {
    $payload1 = [
      'eventCode' => 'XV-NATALIA',
      'tableCode' => 'Mesa 01 · Principal',
      'guestName' => 'Carlos Mendoza',
      'deviceId' => 'device-1',
      'hwFingerprint' => 'v2_fp1111111111',
    ];
    $this->postJson('/api/v1/checkin', $payload1)->assertStatus(201);

    // Attempt check-in to Mesa 02 with same device
    $payload2 = [
      'eventCode' => 'XV-NATALIA',
      'tableCode' => 'Mesa 02 · Orquídea',
      'guestName' => 'Carlos Mendoza',
      'deviceId' => 'device-1',
      'hwFingerprint' => 'v2_fp1111111111',
    ];
    $res = $this->postJson('/api/v1/checkin', $payload2);
    $res->assertStatus(409)
      ->assertJsonPath('code', 'DEVICE_LOCKED_OTHER_TABLE');
  }

  public function test_can_resolve_device_lock(): void
  {
    $payload = [
      'eventCode' => 'XV-NATALIA',
      'tableCode' => 'Mesa 01 · Principal',
      'guestName' => 'Sofia Garcia',
      'deviceId' => 'device-sofia',
      'hwFingerprint' => 'v2_sofia_fp_12345',
      'candidateFingerprints' => ['v2_sofia_fp_12345'],
    ];
    $this->postJson('/api/v1/checkin', $payload)->assertStatus(201);

    $resolvePayload = [
      'eventCode' => 'XV-NATALIA',
      'deviceId' => 'device-sofia',
      'candidateFingerprints' => ['v2_sofia_fp_12345'],
    ];
    $res = $this->postJson('/api/v1/device-locks/resolve', $resolvePayload);
    $res->assertStatus(200)
      ->assertJsonPath('data.lock.guestName', 'Sofia Garcia');
  }

  public function test_can_generate_table_qrs(): void
  {
    $response = $this->withHeaders([
      'X-Host-Pin' => '1515',
    ])->postJson('/api/v1/tables/generate-qrs', [
      'baseUrl' => 'http://192.168.80.10:3000',
    ]);

    $response->assertStatus(200)
      ->assertJsonCount(6, 'data')
      ->assertJsonPath('data.0.accessUrl', 'http://192.168.80.10:3000/acceso?mesa=Mesa+01+%C2%B7+Principal');

    $this->assertDatabaseHas('event_tables', [
      'slug' => 'mesa-01',
      'qr_image_path' => 'qrs/XV-NATALIA/qr-mesa-01.png',
    ]);
  }
}
