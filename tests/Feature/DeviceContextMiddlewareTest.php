<?php

namespace Tests\Feature;

use App\Models\DeviceLock;
use App\Models\Event;
use App\Models\EventTable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class DeviceContextMiddlewareTest extends TestCase
{
  use RefreshDatabase;

  protected function setUp(): void
  {
    parent::setUp();
    $this->seed();
  }

  public function test_middleware_resolves_device_and_actor_from_headers(): void
  {
    $event = Event::first();
    $table = EventTable::first();

    DeviceLock::create([
      'event_id' => $event->id,
      'event_table_id' => $table->id,
      'device_id' => 'dev-middleware-hdr',
      'guest_name' => 'Header Guest',
      'guest_name_slug' => 'header-guest',
      'actor_key' => 'actor-middleware-hdr',
      'bio' => 'Probando middleware por cabecera',
      'location' => 'Margarita',
    ]);

    $res = $this->withHeaders([
      'X-Device-Id' => 'dev-middleware-hdr',
      'X-Actor-Key' => 'actor-middleware-hdr',
    ])->getJson('/api/v1/profile');

    $res->assertStatus(200)
      ->assertJsonPath('success', true)
      ->assertJsonPath('code', 200)
      ->assertJsonPath('data.guestName', 'Header Guest')
      ->assertJsonPath('data.isOwnProfile', true);
  }

  public function test_middleware_resolves_device_and_actor_from_query_params(): void
  {
    $event = Event::first();
    $table = EventTable::first();

    DeviceLock::create([
      'event_id' => $event->id,
      'event_table_id' => $table->id,
      'device_id' => 'dev-middleware-query',
      'guest_name' => 'Query Guest',
      'guest_name_slug' => 'query-guest',
      'actor_key' => 'actor-middleware-query',
      'bio' => 'Probando middleware por query',
    ]);

    $res = $this->getJson('/api/v1/profile?deviceId=dev-middleware-query&actorKey=actor-middleware-query');

    $res->assertStatus(200)
      ->assertJsonPath('success', true)
      ->assertJsonPath('code', 200)
      ->assertJsonPath('data.guestName', 'Query Guest')
      ->assertJsonPath('data.isOwnProfile', true);
  }

  public function test_whitespace_header_falls_back_to_query_param(): void
  {
    $event = Event::first();
    $table = EventTable::first();

    DeviceLock::create([
      'event_id' => $event->id,
      'event_table_id' => $table->id,
      'device_id' => 'dev-valid-fallback',
      'guest_name' => 'Fallback Guest',
      'guest_name_slug' => 'fallback-guest',
      'actor_key' => 'actor-valid-fallback',
    ]);

    // Send whitespace-only header X-Device-Id, which should NOT block falling back to query param
    $res = $this->withHeaders([
      'X-Device-Id' => '   ',
      'X-Actor-Key' => "  \t  ",
    ])->getJson('/api/v1/profile?deviceId=dev-valid-fallback&actorKey=actor-valid-fallback');

    $res->assertStatus(200)
      ->assertJsonPath('success', true)
      ->assertJsonPath('data.guestName', 'Fallback Guest');
  }

  public function test_request_macros_resolve_correct_values(): void
  {
    $request = Request::create('/api/v1/profile?deviceId=macro-dev&actorKey=macro-act', 'GET');
    $request->headers->set('X-Host-Pin', '1234');
    $request->headers->set('X-Event-Code', 'XV-CUSTOM');

    $this->assertEquals('macro-dev', $request->deviceId());
    $this->assertEquals('macro-act', $request->actorKey());
    $this->assertEquals('1234', $request->hostPin());
    $this->assertEquals('XV-CUSTOM', $request->eventCode());
  }

  public function test_host_pin_resolves_from_body_or_alternate_header(): void
  {
    $request1 = Request::create('/api/v1/tables/expel', 'POST', ['pin' => '9988']);
    $this->assertEquals('9988', $request1->hostPin());

    $request2 = Request::create('/api/v1/moderation/metrics', 'GET');
    $request2->headers->set('Host-Pin', '5544');
    $this->assertEquals('5544', $request2->hostPin());
  }

  public function test_device_locks_table_has_profile_columns_in_base_migration(): void
  {
    $this->assertTrue(\Illuminate\Support\Facades\Schema::hasColumn('device_locks', 'avatar_path'));
    $this->assertTrue(\Illuminate\Support\Facades\Schema::hasColumn('device_locks', 'cover_path'));
    $this->assertTrue(\Illuminate\Support\Facades\Schema::hasColumn('device_locks', 'bio'));
    $this->assertTrue(\Illuminate\Support\Facades\Schema::hasColumn('device_locks', 'location'));
  }
}
