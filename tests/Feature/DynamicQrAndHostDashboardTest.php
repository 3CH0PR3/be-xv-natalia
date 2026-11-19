<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventTable;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DynamicQrAndHostDashboardTest extends TestCase
{
  use RefreshDatabase;

  private Event $event;

  protected function setUp(): void
  {
    parent::setUp();

    $this->event = Event::create([
      'code' => 'XV-NATALIA',
      'honoree_name' => 'Natalia Isabella',
      'subtitle' => 'Gala de 15 Años',
      'venue' => 'Salón Las Marías',
      'party_celebrations_count' => 0,
    ]);

    User::create([
      'name' => 'Anfitriona XV',
      'email' => config('xv.host_email', 'anfitriona@xv-natalia-isabela.com'),
      'password' => Hash::make('1515'),
    ]);
  }

  public function test_can_generate_dynamic_qrs_with_rotating_tokens(): void
  {
    $hostPin = '1515';

    $res1 = $this->withHeader('X-Host-Pin', $hostPin)
      ->postJson('/api/v1/tables/generate-qrs', [
        'tableCount' => 6,
        'baseUrl' => 'https://xv-natalia-isabela.com',
      ]);

    $res1->assertStatus(200);
    $data1 = $res1->json('data');
    $this->assertCount(6, $data1);
    $firstToken1 = $data1[0]['qrToken'];
    $this->assertNotEmpty($firstToken1);
    $this->assertStringContainsString($firstToken1, $data1[0]['accessUrl']);

    // Regenerate QRs -> tokens must rotate and be different
    $res2 = $this->withHeader('X-Host-Pin', $hostPin)
      ->postJson('/api/v1/tables/generate-qrs', [
        'tableCount' => 6,
        'baseUrl' => 'https://xv-natalia-isabela.com',
      ]);

    $res2->assertStatus(200);
    $data2 = $res2->json('data');
    $firstToken2 = $data2[0]['qrToken'];
    $this->assertNotEmpty($firstToken2);
    $this->assertNotEquals($firstToken1, $firstToken2);
  }

  public function test_can_scale_table_count_dynamically_to_10_tables(): void
  {
    $hostPin = '1515';

    $res = $this->withHeader('X-Host-Pin', $hostPin)
      ->postJson('/api/v1/tables/generate-qrs', [
        'tableCount' => 10,
        'baseUrl' => 'https://xv-natalia-isabela.com',
      ]);

    $res->assertStatus(200);
    $tables = $res->json('data');
    $this->assertCount(10, $tables);
    $this->assertSame('10', $tables[9]['number']);
  }

  public function test_qr_validation_accepts_valid_dynamic_qr(): void
  {
    $this->withHeader('X-Host-Pin', '1515')
      ->postJson('/api/v1/tables/generate-qrs', [
        'tableCount' => 6,
        'baseUrl' => 'https://xv-natalia-isabela.com',
      ]);

    $table = EventTable::forEvent($this->event->id)->first();
    $this->assertNotNull($table->qr_token);

    $res = $this->postJson('/api/v1/tables/validate-qr', [
      'eventCode' => 'XV-NATALIA',
      'tableCode' => $table->slug,
      'token' => $table->qr_token,
    ]);

    $res->assertStatus(200);
    $this->assertTrue($res->json('data.isValid'));
    $this->assertSame('QR_VALID', $res->json('data.code'));
    $this->assertSame($table->code, $res->json('data.table.code'));
  }

  public function test_qr_validation_rejects_expired_or_tampered_token(): void
  {
    $this->withHeader('X-Host-Pin', '1515')
      ->postJson('/api/v1/tables/generate-qrs', [
        'tableCount' => 6,
        'baseUrl' => 'https://xv-natalia-isabela.com',
      ]);

    $table = EventTable::forEvent($this->event->id)->first();

    $res = $this->postJson('/api/v1/tables/validate-qr', [
      'eventCode' => 'XV-NATALIA',
      'tableCode' => $table->slug,
      'token' => 'token_invalido_falso',
    ]);

    $res->assertStatus(422);
    $this->assertSame('QR_TOKEN_INVALID', $res->json('errors.code'));
  }

  public function test_qr_validation_parses_and_validates_raw_scanned_url(): void
  {
    $this->withHeader('X-Host-Pin', '1515')
      ->postJson('/api/v1/tables/generate-qrs', [
        'tableCount' => 6,
        'baseUrl' => 'https://xv-natalia-isabela.com',
      ]);

    $table = EventTable::forEvent($this->event->id)->where('number', '03')->first();
    $rawUrl = "https://xv-natalia-isabela.com/acceso?mesa={$table->slug}&token={$table->qr_token}";

    $res = $this->postJson('/api/v1/tables/validate-qr', [
      'eventCode' => 'XV-NATALIA',
      'rawPayload' => $rawUrl,
    ]);

    $res->assertStatus(200);
    $this->assertTrue($res->json('data.isValid'));
    $this->assertSame($table->code, $res->json('data.table.code'));
  }

  public function test_qr_validation_rejects_unknown_table_or_other_event(): void
  {
    $resOtherEvent = $this->postJson('/api/v1/tables/validate-qr', [
      'eventCode' => 'EVENTO-DESCONOCIDO',
      'tableCode' => 'mesa-01',
    ]);
    $resOtherEvent->assertStatus(422);
    $this->assertSame('EVENT_NOT_FOUND', $resOtherEvent->json('errors.code'));

    $resUnknownTable = $this->postJson('/api/v1/tables/validate-qr', [
      'eventCode' => 'XV-NATALIA',
      'tableCode' => 'mesa-999-inexistente',
    ]);
    $resUnknownTable->assertStatus(422);
    $this->assertSame('TABLE_NOT_FOUND', $resUnknownTable->json('errors.code'));
  }

  public function test_can_change_host_pin_with_correct_current_pin(): void
  {
    // Invalid current PIN fails
    $resFail = $this->withHeader('X-Host-Pin', '1515')
      ->postJson('/api/v1/moderation/change-pin', [
        'currentPin' => '9999',
        'newPin' => '2026',
      ]);
    $resFail->assertStatus(403);

    // Valid current PIN succeeds
    $resSuccess = $this->withHeader('X-Host-Pin', '1515')
      ->postJson('/api/v1/moderation/change-pin', [
        'currentPin' => '1515',
        'newPin' => '2026',
      ]);
    $resSuccess->assertStatus(200);

    // Old PIN fails now
    $this->assertFalse(User::verifyHostPin('1515'));
    // New PIN works
    $this->assertTrue(User::verifyHostPin('2026'));
  }
}
