<?php

namespace Tests\Feature;

use App\Models\DeviceLock;
use App\Models\Event;
use App\Models\EventTable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfileSearchTest extends TestCase
{
  use RefreshDatabase;

  protected function setUp(): void
  {
    parent::setUp();
    $this->seed();
    Storage::fake('public');
  }

  public function test_can_list_initial_profiles_without_search(): void
  {
    $event = Event::where('code', 'XV-NATALIA')->first();
    $table = EventTable::where('event_id', $event->id)->first();

    // Create 12 guests
    for ($i = 1; $i <= 12; $i++) {
      DeviceLock::create([
        'event_id' => $event->id,
        'event_table_id' => $table->id,
        'device_id' => "device-{$i}",
        'actor_key' => "actor-{$i}",
        'guest_name' => "Invitado {$i}",
        'guest_name_slug' => "invitado-{$i}",
        'locked_at' => now()->subMinutes(12 - $i),
      ]);
    }

    $res = $this->getJson('/api/v1/profiles?limit=10');

    $res->assertStatus(200)
      ->assertJsonStructure([
        'data' => [
          'data' => [
            '*' => [
              'id',
              'guestName',
              'slug',
              'actorKey',
              'deviceId',
              'avatarUrl',
              'coverUrl',
              'tableCode',
            ],
          ],
          'pagination' => [
            'currentPage',
            'perPage',
            'total',
            'hasMore',
          ],
        ],
      ]);

    $items = $res->json('data.data');
    $this->assertCount(10, $items);
    $this->assertEquals(12, $res->json('data.pagination.total'));
    $this->assertTrue($res->json('data.pagination.hasMore'));
  }

  public function test_can_search_profiles_by_name_and_slug(): void
  {
    $event = Event::where('code', 'XV-NATALIA')->first();
    $table = EventTable::where('event_id', $event->id)->first();

    DeviceLock::create([
      'event_id' => $event->id,
      'event_table_id' => $table->id,
      'device_id' => 'dev-carlos',
      'actor_key' => 'actor-carlos',
      'guest_name' => 'Carlos Mendoza',
      'guest_name_slug' => 'carlos-mendoza',
      'locked_at' => now(),
    ]);

    DeviceLock::create([
      'event_id' => $event->id,
      'event_table_id' => $table->id,
      'device_id' => 'dev-sofia',
      'actor_key' => 'actor-sofia',
      'guest_name' => 'Sofía Ramírez',
      'guest_name_slug' => 'sofia-ramirez',
      'locked_at' => now(),
    ]);

    // Search 'carlos'
    $res = $this->getJson('/api/v1/profiles?search=carlos');
    $res->assertStatus(200);
    $data = $res->json('data.data');
    $this->assertCount(1, $data);
    $this->assertEquals('Carlos Mendoza', $data[0]['guestName']);

    // Search empty matches
    $resEmpty = $this->getJson('/api/v1/profiles?search=Inexistente');
    $resEmpty->assertStatus(200);
    $this->assertCount(0, $resEmpty->json('data.data'));
  }
}
