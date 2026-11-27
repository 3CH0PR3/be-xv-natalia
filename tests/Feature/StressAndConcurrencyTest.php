<?php

namespace Tests\Feature;

use App\Actions\Table\GenerateTableQrsAction;
use App\Models\DeviceLock;
use App\Models\Event;
use App\Models\EventTable;
use App\Models\Memory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StressAndConcurrencyTest extends TestCase
{
  use RefreshDatabase;

  protected function setUp(): void
  {
    parent::setUp();
    $this->seed();
    Storage::fake('public');
  }

  public function test_high_concurrency_table_saturation_respects_exact_capacity(): void
  {
    $table = EventTable::where('slug', 'mesa-01')->firstOrFail();
    $names = [
      'Invitado Uno',
      'Invitado Dos',
      'Invitado Tres',
      'Invitado Cuatro',
      'Invitado Cinco',
      'Invitado Seis',
      'Invitado Siete',
      'Invitado Ocho',
      'Invitado Nueve',
      'Invitado Diez',
    ];

    // 1. Llenar la mesa hasta su capacidad máxima de 10 personas
    foreach ($names as $idx => $name) {
      $num = $idx + 1;
      $res = $this->postJson('/api/v1/checkin', [
        'eventCode' => 'XV-NATALIA',
        'tableCode' => 'Mesa 01 · Principal',
        'guestName' => $name,
        'deviceId' => "dev-stress-concurrent-{$num}",
        'hwFingerprint' => "v2_fp_stress_{$num}",
      ]);

      $res->assertStatus(201);
    }

    $this->assertEquals(10, DeviceLock::where('event_table_id', $table->id)->count());

    // 2. Intento de registro del invitado #11 en la misma mesa saturada
    $overflowRes = $this->postJson('/api/v1/checkin', [
      'eventCode' => 'XV-NATALIA',
      'tableCode' => 'Mesa 01 · Principal',
      'guestName' => 'Invitado Excedente Once',
      'deviceId' => 'dev-stress-overflow-11',
      'hwFingerprint' => 'v2_fp_stress_11',
    ]);

    $overflowRes->assertStatus(409)
      ->assertJsonPath('code', 'TABLE_FULL');

    // La capacidad permanece estrictamente en 10
    $this->assertEquals(10, DeviceLock::where('event_table_id', $table->id)->count());
  }

  public function test_qr_regeneration_preserves_connected_guests_and_invalidates_old_qr_tokens(): void
  {
    $event = Event::byCode('XV-NATALIA')->firstOrFail();

    // 1. Generar QRs oficiales iniciales (Token A)
    app(GenerateTableQrsAction::class)->execute($event, 'http://localhost:3000', 10);
    $table = EventTable::where('event_id', $event->id)->where('slug', 'mesa-01')->firstOrFail();
    $oldToken = $table->qr_token;

    $this->assertNotEmpty($oldToken);

    // 2. Conectar invitado a Mesa 01 con token original A
    $checkinRes = $this->postJson('/api/v1/checkin', [
      'eventCode' => 'XV-NATALIA',
      'tableCode' => 'Mesa 01 · Principal',
      'guestName' => 'Maria Fernandez',
      'deviceId' => 'dev-maria-active',
      'hwFingerprint' => 'v2_fp_maria_1',
      'qrToken' => $oldToken,
    ]);
    $checkinRes->assertStatus(201);

    // 3. Moderador regenera los QRs de todas las mesas (Token B)
    app(GenerateTableQrsAction::class)->execute($event, 'http://localhost:3000', 10);
    $table->refresh();
    $newToken = $table->qr_token;

    $this->assertNotEmpty($newToken);
    $this->assertNotEquals($oldToken, $newToken);

    // 4. VERIFICACIÓN DE SESIÓN ACTIVA:
    // Maria (ya conectada) puede seguir publicando recuerdos sin que se le cierre la sesión
    $photo = UploadedFile::fake()->image('recuerdo-maria.jpg');
    $memoryRes = $this->withHeaders(['X-Device-Id' => 'dev-maria-active'])
      ->postJson('/api/v1/memories', [
        'eventCode' => 'XV-NATALIA',
        'tableCode' => 'Mesa 01 · Principal',
        'guestName' => 'Maria Fernandez',
        'ownerDeviceId' => 'dev-maria-active',
        'caption' => 'Celebrando con la quinceañera',
        'files' => [$photo],
      ]);
    $memoryRes->assertStatus(201);
    $this->assertDatabaseHas('memories', [
      'owner_device_id' => 'dev-maria-active',
      'caption' => 'Celebrando con la quinceañera',
    ]);

    // 5. INTENTO DE ACCESO EXTERNO CON QR VIEJO (foto compartida / link viejo con Token A):
    // Debe ser rechazado con 422 QR_TOKEN_INVALID
    $intruderOldQrRes = $this->postJson('/api/v1/checkin', [
      'eventCode' => 'XV-NATALIA',
      'tableCode' => 'Mesa 01 · Principal',
      'guestName' => 'Intruso Link Viejo',
      'deviceId' => 'dev-intruder-old-qr',
      'hwFingerprint' => 'v2_fp_intruder_old',
      'qrToken' => $oldToken,
    ]);
    $intruderOldQrRes->assertStatus(422)
      ->assertJsonPath('code', 'QR_TOKEN_INVALID');

    // 6. ACCESO CON QR NUEVO OFICIAL (Token B):
    // Debe ser aceptado con 201
    $legitNewQrRes = $this->postJson('/api/v1/checkin', [
      'eventCode' => 'XV-NATALIA',
      'tableCode' => 'Mesa 01 · Principal',
      'guestName' => 'Invitado Nuevo Oficial',
      'deviceId' => 'dev-legit-new-qr',
      'hwFingerprint' => 'v2_fp_legit_new',
      'qrToken' => $newToken,
    ]);
    $legitNewQrRes->assertStatus(201);
  }

  public function test_device_lock_inviolability_prevents_unauthorized_table_switching(): void
  {
    // 1. Invitado se registra en Mesa 01
    $this->postJson('/api/v1/checkin', [
      'eventCode' => 'XV-NATALIA',
      'tableCode' => 'Mesa 01 · Principal',
      'guestName' => 'Santiago Rivas',
      'deviceId' => 'dev-santiago-inviolable',
      'hwFingerprint' => 'v2_fp_santiago_lock',
    ])->assertStatus(201);

    // 2. Intenta registrarse en Mesa 02, Mesa 03, Mesa 04:
    // El sistema rechaza cualquier auto-cambio categóricamente
    foreach (['Mesa 02 · Orquídea', 'Mesa 03 · Magnolia', 'Mesa 04 · Esmeralda'] as $targetTable) {
      $this->postJson('/api/v1/checkin', [
        'eventCode' => 'XV-NATALIA',
        'tableCode' => $targetTable,
        'guestName' => 'Santiago Rivas',
        'deviceId' => 'dev-santiago-inviolable',
        'hwFingerprint' => 'v2_fp_santiago_lock',
      ])->assertStatus(409)
        ->assertJsonPath('code', 'DEVICE_LOCKED_OTHER_TABLE');
    }

    // 3. La anfitriona libera su puesto en Mesa 01
    $this->withHeaders(['X-Host-Pin' => '1515'])
      ->postJson('/api/v1/tables/expel', [
        'eventCode' => 'XV-NATALIA',
        'tableId' => 'mesa-01',
        'deviceId' => 'dev-santiago-inviolable',
      ])->assertStatus(200);

    // 4. Ahora sí se permite el registro en su nueva mesa asignada
    $newTableRes = $this->postJson('/api/v1/checkin', [
      'eventCode' => 'XV-NATALIA',
      'tableCode' => 'Mesa 04 · Esmeralda',
      'guestName' => 'Santiago Rivas',
      'deviceId' => 'dev-santiago-inviolable',
      'hwFingerprint' => 'v2_fp_santiago_lock',
    ]);
    $newTableRes->assertStatus(201)
      ->assertJsonPath('data.tableCode', 'Mesa 4');
  }
}
