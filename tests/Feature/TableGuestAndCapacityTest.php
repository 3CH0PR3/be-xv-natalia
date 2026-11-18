<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventTable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TableGuestAndCapacityTest extends TestCase
{
  use RefreshDatabase;

  protected function setUp(): void
  {
    parent::setUp();
    $this->seed();
  }

  public function test_all_tables_have_capacity_of_10(): void
  {
    $tables = EventTable::all();
    $this->assertCount(6, $tables);

    foreach ($tables as $table) {
      $this->assertSame(10, (int) $table->capacity, "Mesa {$table->code} debe tener capacidad de 10 personas.");
    }
  }

  public function test_can_expel_guest_via_table_guest_controller_with_host_pin_header(): void
  {
    // 1. Checkin
    $checkin = $this->postJson('/api/v1/checkin', [
      'eventCode' => 'XV-NATALIA',
      'tableCode' => 'Mesa 01 · Principal',
      'guestName' => 'Invitado Test Expulsion',
      'deviceId' => 'dev-expel-test-1',
      'hwFingerprint' => 'fp-expel-1',
    ]);
    $checkin->assertStatus(201)
      ->assertJsonPath('success', true);

    // 2. Expel via /tables/expel with header
    $expel = $this->withHeaders(['X-Host-Pin' => '1515'])
      ->postJson('/api/v1/tables/expel', [
        'eventCode' => 'XV-NATALIA',
        'tableId' => 'mesa-01',
        'deviceId' => 'dev-expel-test-1',
      ]);

    $expel->assertStatus(200)
      ->assertJsonPath('success', true)
      ->assertJsonStructure(['success', 'code', 'data']);

    $this->assertDatabaseMissing('device_locks', [
      'device_id' => 'dev-expel-test-1',
    ]);
  }

  public function test_can_expel_guest_via_table_guest_controller_with_host_pin_in_body(): void
  {
    // 1. Checkin
    $this->postJson('/api/v1/checkin', [
      'eventCode' => 'XV-NATALIA',
      'tableCode' => 'Mesa 02 · Orquídea',
      'guestName' => 'Invitado Dos',
      'deviceId' => 'dev-expel-test-2',
      'hwFingerprint' => 'fp-expel-2',
    ])->assertStatus(201);

    // 2. Expel via /tables/mesa-02/expel with pin in body
    $expel = $this->postJson('/api/v1/tables/mesa-02/expel', [
      'pin' => '1515',
      'deviceId' => 'dev-expel-test-2',
    ]);

    $expel->assertStatus(200)
      ->assertJsonPath('success', true);

    $this->assertDatabaseMissing('device_locks', [
      'device_id' => 'dev-expel-test-2',
    ]);
  }

  public function test_can_expel_guest_via_device_lock_controller(): void
  {
    $this->postJson('/api/v1/checkin', [
      'eventCode' => 'XV-NATALIA',
      'tableCode' => 'Mesa 03 · Magnolia',
      'guestName' => 'Invitado Tres',
      'deviceId' => 'dev-expel-test-3',
      'hwFingerprint' => 'fp-expel-3',
    ])->assertStatus(201);

    $expel = $this->withHeaders(['X-Host-Pin' => '1515'])
      ->postJson('/api/v1/device-locks/expel', [
        'tableId' => 'mesa-03',
        'deviceId' => 'dev-expel-test-3',
      ]);

    $expel->assertStatus(200)
      ->assertJsonPath('success', true);

    $this->assertDatabaseMissing('device_locks', [
      'device_id' => 'dev-expel-test-3',
    ]);
  }

  public function test_expel_fails_with_invalid_host_pin(): void
  {
    $expel = $this->withHeaders(['X-Host-Pin' => '9999'])
      ->postJson('/api/v1/tables/expel', [
        'tableId' => 'mesa-01',
        'deviceId' => 'dev-any',
      ]);

    $expel->assertStatus(403)
      ->assertJsonPath('code', 'HOST_PIN_INVALID')
      ->assertJsonPath('success', false);
  }

  public function test_standard_json_response_structures(): void
  {
    // 1. Tables index -> 200 { success: true, data: [...] }
    $tablesRes = $this->getJson('/api/v1/tables');
    $tablesRes->assertStatus(200)
      ->assertJsonPath('success', true)
      ->assertJsonCount(6, 'data');

    // 2. Event show -> 200 { success: true, data: {...} }
    $eventRes = $this->getJson('/api/v1/event');
    $eventRes->assertStatus(200)
      ->assertJsonPath('success', true)
      ->assertJsonPath('data.eventCode', 'XV-NATALIA');

    // 3. Releases index -> 200 { success: true, data: [...] }
    $releaseRes = $this->getJson('/api/v1/releases');
    $releaseRes->assertStatus(200)
      ->assertJsonPath('success', true);
  }
}
