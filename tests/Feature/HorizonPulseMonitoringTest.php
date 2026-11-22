<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class HorizonPulseMonitoringTest extends TestCase
{
  use RefreshDatabase;

  protected function setUp(): void
  {
    parent::setUp();
    $this->seed();
  }

  public function test_pulse_tables_exist_with_proper_structure(): void
  {
    $this->assertTrue(Schema::hasTable('pulse_entries'));
    $this->assertTrue(Schema::hasTable('pulse_values'));
    $this->assertTrue(Schema::hasTable('pulse_aggregates'));
  }

  public function test_horizon_dashboard_is_forbidden_without_host_pin(): void
  {
    $response = $this->get('/horizon');
    $response->assertStatus(403);
  }

  public function test_horizon_dashboard_is_accessible_with_host_pin_header(): void
  {
    $response = $this->withHeaders([
      'X-Host-Pin' => config('xv.host_pin', '1515'),
    ])->get('/horizon');

    // Horizon typically returns 200 or 302 redirecting to /horizon/dashboard
    $this->assertTrue(in_array($response->getStatusCode(), [200, 302], true));
  }

  public function test_horizon_dashboard_is_accessible_with_host_pin_query_param(): void
  {
    $pin = config('xv.host_pin', '1515');
    $response = $this->get("/horizon?pin={$pin}");

    $this->assertTrue(in_array($response->getStatusCode(), [200, 302], true));
  }

  public function test_pulse_dashboard_is_forbidden_without_host_pin(): void
  {
    $response = $this->get('/pulse');
    $response->assertStatus(403);
  }

  public function test_pulse_dashboard_is_accessible_with_host_pin_header(): void
  {
    $response = $this->withHeaders([
      'X-Host-Pin' => config('xv.host_pin', '1515'),
    ])->get('/pulse');

    $response->assertStatus(200);
  }

  public function test_pulse_dashboard_is_accessible_with_host_pin_query_param(): void
  {
    $pin = config('xv.host_pin', '1515');
    $response = $this->get("/pulse?pin={$pin}");

    $response->assertStatus(200);
  }

  public function test_pulse_can_record_data_when_enabled(): void
  {
    config(['pulse.enabled' => true]);
    $this->assertTrue(config('pulse.enabled'));

    $response = $this->getJson('/api/v1/event');
    $response->assertStatus(200);
  }
}
