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
      'deviceId' => 'device-sofia-browser',
      'hwFingerprint' => 'fphwv2_sofia_fp_12345',
      'candidateFingerprints' => ['fphwv2_sofia_fp_12345', 'fphwhw2_sofia_hardware_core'],
    ];
    $this->postJson('/api/v1/checkin', $payload)->assertStatus(201);

    // Cross-platform check: TWA opens with a completely different deviceId but same hardware candidates
    $resolveFromTwaPayload = [
      'eventCode' => 'XV-NATALIA',
      'deviceId' => 'device-sofia-twa-apk-new-id',
      'candidateFingerprints' => ['fphwhw2_sofia_hardware_core'],
    ];
    $res = $this->postJson('/api/v1/device-locks/resolve', $resolveFromTwaPayload);
    $res->assertStatus(200)
      ->assertJsonPath('data.lock.guestName', 'Sofia Garcia')
      ->assertJsonPath('data.restoredByFingerprint', true);
  }

  public function test_can_resolve_device_lock_via_hardware_summary(): void
  {
    $hardwareSummary = [
      'metrics' => 'os:android|scr:393x873|dpr:2750|depth:24:24|cores:8|mem:8|touch:5|tz:America/Caracas',
      'gpu' => 'v:arm|r:mali-g68 mc4|t:16384:16384:16384|u:16:32:1024:1024|p:23:23',
    ];

    $checkinPayload = [
      'eventCode' => 'XV-NATALIA',
      'tableCode' => 'Mesa 01 · Principal',
      'guestName' => 'Mateo Gomez',
      'deviceId' => 'device-mateo-browser',
      'hwFingerprint' => 'fphwv2_mateo_browser_fp',
      'candidateFingerprints' => ['fphwv2_mateo_browser_fp'],
      'hardwareSummary' => $hardwareSummary,
    ];
    $this->postJson('/api/v1/checkin', $checkinPayload)->assertStatus(201);

    // Multi-context test: APK opens on the same phone with isolated storage (new deviceId, no matching candidates)
    // but with identical hardware metrics & GPU WebGL profile
    $resolveFromApkPayload = [
      'eventCode' => 'XV-NATALIA',
      'deviceId' => 'device-mateo-apk-isolated-id',
      'candidateFingerprints' => ['fphwv2_mateo_apk_isolated_anchor'],
      'hardwareSummary' => $hardwareSummary,
    ];

    $res = $this->postJson('/api/v1/device-locks/resolve', $resolveFromApkPayload);
    $res->assertStatus(200)
      ->assertJsonPath('data.lock.guestName', 'Mateo Gomez')
      ->assertJsonPath('data.restoredByFingerprint', true);
  }

  public function test_can_generate_table_qrs(): void
  {
    $response = $this->withHeaders([
      'X-Host-Pin' => '1515',
    ])->postJson('/api/v1/tables/generate-qrs', [
      'baseUrl' => 'http://192.168.80.10:3000',
    ]);

    $response->assertStatus(200)
      ->assertJsonCount(6, 'data');
    $this->assertStringStartsWith('http://192.168.80.10:3000/acceso?mesa=mesa-01&token=qrt_', (string) $response->json('data.0.accessUrl'));

    $this->assertDatabaseHas('event_tables', [
      'slug' => 'mesa-01',
      'qr_image_path' => 'qrs/XV-NATALIA/qr-mesa-01.png',
    ]);
  }

  public function test_checkin_validates_strict_name_rules(): void
  {
    $basePayload = [
      'eventCode' => 'XV-NATALIA',
      'tableCode' => 'Mesa 01 · Principal',
      'deviceId' => 'device-test-names',
      'hwFingerprint' => 'v2_fp_names_test',
    ];

    // Single name (must be rejected)
    $this->postJson('/api/v1/checkin', array_merge($basePayload, ['guestName' => 'Carlos']))
      ->assertStatus(422)
      ->assertJsonValidationErrors('guestName');

    // Name with numbers (must be rejected)
    $this->postJson('/api/v1/checkin', array_merge($basePayload, ['guestName' => 'Carlos123 Mendoza']))
      ->assertStatus(422)
      ->assertJsonValidationErrors('guestName');

    // Name with emoji (must be rejected)
    $this->postJson('/api/v1/checkin', array_merge($basePayload, ['guestName' => 'Carlos Mendoza 🎉']))
      ->assertStatus(422)
      ->assertJsonValidationErrors('guestName');

    // Name with symbols/asterisks (must be rejected)
    $this->postJson('/api/v1/checkin', array_merge($basePayload, ['guestName' => 'Carlos * Mendoza']))
      ->assertStatus(422)
      ->assertJsonValidationErrors('guestName');

    // Valid full name with accents (must succeed)
    $this->postJson('/api/v1/checkin', array_merge($basePayload, ['guestName' => 'Andrés Peña Gómez']))
      ->assertStatus(201)
      ->assertJsonPath('data.guestName', 'Andrés Peña Gómez');
  }

  public function test_tables_updated_event_broadcasts_guests_list(): void
  {
    $tableData = [
      'id' => 'mesa-01',
      'number' => '01',
      'code' => 'Mesa 01 · Principal',
      'zone' => 'Tarima de Honor',
      'centerpiece' => 'Rosas blancas',
      'capacity' => 10,
      'guests' => [
        [
          'deviceId' => 'dev-1',
          'guestName' => 'Juan Perez',
          'joinedAt' => now()->toIso8601String(),
        ],
      ],
      'guestsConnected' => 1,
    ];

    $event = new \App\Events\TablesUpdated('XV-NATALIA', [$tableData]);
    $broadcastWith = $event->broadcastWith();

    $this->assertArrayHasKey('tables', $broadcastWith);
    $this->assertCount(1, $broadcastWith['tables']);
    $this->assertSame('mesa-01', $broadcastWith['tables'][0]['id']);
    $this->assertArrayHasKey('guests', $broadcastWith['tables'][0]);
    $this->assertCount(1, $broadcastWith['tables'][0]['guests']);
    $this->assertSame('Juan Perez', $broadcastWith['tables'][0]['guests'][0]['guestName']);
    $this->assertSame(1, $broadcastWith['tables'][0]['guestsConnected']);
  }
}
