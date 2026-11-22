<?php

namespace Tests\Feature;

use App\Services\MonitoringAuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class HorizonPulseMonitoringTest extends TestCase
{
  use RefreshDatabase;

  protected string $validMonitoringPin = '$*Developer1';

  protected function setUp(): void
  {
    parent::setUp();
    $this->seed();
    config(['xv.monitoring_pin' => $this->validMonitoringPin]);
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

  public function test_horizon_dashboard_rejects_wrong_pin(): void
  {
    $response = $this->get('/horizon?pin=wrong_pin_123');
    $response->assertStatus(403);
  }

  public function test_horizon_dashboard_is_accessible_with_monitoring_pin_header(): void
  {
    $response = $this->withHeaders([
      'X-Host-Pin' => $this->validMonitoringPin,
    ])->get('/horizon');

    $this->assertTrue(in_array($response->getStatusCode(), [200, 302], true));
  }

  public function test_horizon_dashboard_is_accessible_with_monitoring_pin_query_param(): void
  {
    $response = $this->get("/horizon?pin={$this->validMonitoringPin}");

    $this->assertTrue(in_array($response->getStatusCode(), [200, 302], true));
  }

  public function test_horizon_session_persists_for_subsequent_ajax_requests_without_pin(): void
  {
    // 1. Entrada inicial a /horizon con el pin
    $initial = $this->get("/horizon?pin={$this->validMonitoringPin}");
    $this->assertTrue(in_array($initial->getStatusCode(), [200, 302], true));

    // 2. Simulación de llamada AJAX subsecuente de Horizon (sin parámetro pin ni header)
    // Se ejecuta en la misma sesión/cookie
    $ajaxResponse = $this->get('/horizon/api/jobs/pending');
    $this->assertTrue(in_array($ajaxResponse->getStatusCode(), [200, 302], true));
  }

  public function test_pulse_dashboard_is_forbidden_without_host_pin(): void
  {
    $response = $this->get('/pulse');
    $response->assertStatus(403);
  }

  public function test_pulse_dashboard_is_accessible_with_monitoring_pin_header(): void
  {
    $response = $this->withHeaders([
      'X-Host-Pin' => $this->validMonitoringPin,
    ])->get('/pulse');

    $response->assertStatus(200);
  }

  public function test_pulse_dashboard_is_accessible_with_monitoring_pin_query_param(): void
  {
    $response = $this->get("/pulse?pin={$this->validMonitoringPin}");

    $response->assertStatus(200);
  }

  public function test_pulse_session_persists_for_subsequent_requests_without_pin(): void
  {
    // 1. Entrada inicial a /pulse con el pin
    $initial = $this->get("/pulse?pin={$this->validMonitoringPin}");
    $initial->assertStatus(200);

    // 2. Petición subsecuente sin pin en la misma sesión
    $subsequent = $this->get('/pulse');
    $subsequent->assertStatus(200);
  }

  public function test_pulse_can_record_data_when_enabled(): void
  {
    config(['pulse.enabled' => true]);
    $this->assertTrue(config('pulse.enabled'));

    $response = $this->getJson('/api/v1/event');
    $response->assertStatus(200);
  }
}
