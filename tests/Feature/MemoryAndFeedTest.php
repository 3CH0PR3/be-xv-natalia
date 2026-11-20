<?php

namespace Tests\Feature;

use App\Models\Event;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MemoryAndFeedTest extends TestCase
{
  use RefreshDatabase;

  protected function setUp(): void
  {
    parent::setUp();
    $this->seed();
    Storage::fake('public');
  }

  public function test_can_publish_and_list_memory(): void
  {
    $file = UploadedFile::fake()->image('quince_photo.jpg');

    $publishPayload = [
      'eventCode' => 'XV-NATALIA',
      'guestName' => 'Valeria Gómez',
      'tableCode' => 'Mesa 01 · Principal',
      'ownerDeviceId' => 'dev-valeria-1',
      'ownerActorKey' => 'actor-valeria-1',
      'momentTag' => 'Vals de las Flores',
      'caption' => 'Felicidades Natalia en tus 15!',
      'files' => [$file],
    ];

    // Without active QR lock -> 403
    $this->withHeaders(['X-Device-Id' => 'dev-valeria-1'])
      ->postJson('/api/v1/memories', $publishPayload)
      ->assertStatus(403);

    // Checkin Valeria to activate QR session
    $this->postJson('/api/v1/checkin', [
      'eventCode' => 'XV-NATALIA',
      'tableCode' => 'Mesa 01 · Principal',
      'guestName' => 'Valeria Gómez',
      'deviceId' => 'dev-valeria-1',
      'actorKey' => 'actor-valeria-1',
    ])->assertStatus(201);

    // Now publish memory -> 201
    $response = $this->withHeaders(['X-Device-Id' => 'dev-valeria-1'])
      ->postJson('/api/v1/memories', $publishPayload);

    $response->assertStatus(201)
      ->assertJsonPath('data.guestName', 'Valeria Gómez')
      ->assertJsonPath('data.caption', 'Felicidades Natalia en tus 15!')
      ->assertJsonCount(1, 'data.mediaItems');

    $externalId = $response->json('data.id');

    // Test list feed
    $feedRes = $this->getJson('/api/v1/memories');
    $feedRes->assertStatus(200)
      ->assertJsonCount(1, 'data');

    // Test category filter on feed (no comments yet)
    $catRes = $this->getJson('/api/v1/memories?category=comments');
    $catRes->assertStatus(200)
      ->assertJsonCount(0, 'data');

    // Test update caption by owner
    $updateRes = $this->withHeaders([
      'X-Device-Id' => 'dev-valeria-1',
      'X-Actor-Key' => 'actor-valeria-1',
    ])->patchJson("/api/v1/memories/{$externalId}/caption", [
      'caption' => 'Nueva dedicatoria actualizada!',
    ]);
    $updateRes->assertStatus(200)
      ->assertJsonPath('data.caption', 'Nueva dedicatoria actualizada!');

    // Checkin second guest to react
    $this->postJson('/api/v1/checkin', [
      'eventCode' => 'XV-NATALIA',
      'tableCode' => 'Mesa 02 · Orquídea',
      'guestName' => 'Pedro Pérez',
      'deviceId' => 'dev-react-1',
      'actorKey' => 'guest-react-1',
    ])->assertStatus(201);

    // Test toggle reaction: fire
    $reactRes = $this->withHeaders(['X-Device-Id' => 'dev-react-1'])
      ->postJson("/api/v1/memories/{$externalId}/reactions/toggle", [
        'actorKey' => 'guest-react-1',
        'deviceId' => 'dev-react-1',
        'choice' => 'fires',
      ]);
    $reactRes->assertStatus(200)
      ->assertJsonPath('data.fires', 1)
      ->assertJsonPath('data.likes', 0)
      ->assertJsonPath('data.cheers', 0)
      ->assertJsonPath('data.choice', 'fires');

    // Test mutual exclusion: switching to 'likes' removes 'fires' and increments 'likes'
    $switchRes = $this->withHeaders(['X-Device-Id' => 'dev-react-1'])
      ->postJson("/api/v1/memories/{$externalId}/reactions/toggle", [
        'actorKey' => 'guest-react-1',
        'deviceId' => 'dev-react-1',
        'choice' => 'likes',
      ]);
    $switchRes->assertStatus(200)
      ->assertJsonPath('data.fires', 0)
      ->assertJsonPath('data.likes', 1)
      ->assertJsonPath('data.cheers', 0)
      ->assertJsonPath('data.choice', 'likes');

    // Test untoggle: clicking same reaction ('likes') toggles it off to 0
    $untoggleRes = $this->withHeaders(['X-Device-Id' => 'dev-react-1'])
      ->postJson("/api/v1/memories/{$externalId}/reactions/toggle", [
        'actorKey' => 'guest-react-1',
        'deviceId' => 'dev-react-1',
        'choice' => 'likes',
      ]);
    $untoggleRes->assertStatus(200)
      ->assertJsonPath('data.fires', 0)
      ->assertJsonPath('data.likes', 0)
      ->assertJsonPath('data.cheers', 0)
      ->assertJsonPath('data.choice', '');

    // Test increment share counter (both /share and /shares)
    $shareRes = $this->withHeaders(['X-Device-Id' => 'dev-react-1'])
      ->postJson("/api/v1/memories/{$externalId}/shares");
    $shareRes->assertStatus(200)
      ->assertJsonPath('data.shares', 1);

    $shareRes2 = $this->withHeaders(['X-Device-Id' => 'dev-react-1'])
      ->postJson("/api/v1/memories/{$externalId}/share");
    $shareRes2->assertStatus(200)
      ->assertJsonPath('data.shares', 2);

    $this->assertDatabaseHas('memories', [
      'external_id' => $externalId,
      'shares_count' => 2,
    ]);

    $this->assertDatabaseCount('memory_shares', 2);
    $memModel = \App\Models\Memory::where('external_id', $externalId)->first();
    $this->assertCount(2, $memModel->shares);
    $this->assertEquals('dev-react-1', $memModel->shares->first()->device_id);

    // Test delete memory by owner
    $delRes = $this->withHeaders([
      'X-Device-Id' => 'dev-valeria-1',
      'X-Actor-Key' => 'actor-valeria-1',
    ])->deleteJson("/api/v1/memories/{$externalId}");
    $delRes->assertStatus(200);

    $this->assertDatabaseMissing('memories', ['external_id' => $externalId]);
  }

  public function test_relational_device_lock_updates_name_across_memories_and_comments_with_fallback(): void
  {
    $file = UploadedFile::fake()->image('quince_photo.jpg');

    // 1. Checkin
    $checkinRes = $this->postJson('/api/v1/checkin', [
      'eventCode' => 'XV-NATALIA',
      'tableCode' => 'Mesa 01 · Principal',
      'guestName' => 'Andres Original',
      'deviceId' => 'dev-andres-rel',
      'actorKey' => 'actor-andres-rel',
    ]);
    $checkinRes->assertStatus(201);

    // 2. Publish memory
    $memRes = $this->withHeaders(['X-Device-Id' => 'dev-andres-rel', 'X-Actor-Key' => 'actor-andres-rel'])
      ->postJson('/api/v1/memories', [
        'eventCode' => 'XV-NATALIA',
        'guestName' => 'Andres Original',
        'tableCode' => 'Mesa 01 · Principal',
        'ownerDeviceId' => 'dev-andres-rel',
        'ownerActorKey' => 'actor-andres-rel',
        'momentTag' => 'Fiesta en Vivo',
        'caption' => 'Hermosa fiesta!',
        'files' => [$file],
      ]);
    $memRes->assertStatus(201)
      ->assertJsonPath('data.guestName', 'Andres Original');
    $memoryId = $memRes->json('data.id');

    // 3. Add comment
    $commRes = $this->withHeaders(['X-Device-Id' => 'dev-andres-rel', 'X-Actor-Key' => 'actor-andres-rel'])
      ->postJson("/api/v1/memories/{$memoryId}/comments", [
        'author' => 'Andres Original',
        'table' => 'Mesa 01 · Principal',
        'text' => 'Comentario de prueba relacional',
        'deviceId' => 'dev-andres-rel',
        'actorKey' => 'actor-andres-rel',
      ]);
    $commRes->assertStatus(201)
      ->assertJsonPath('data.author', 'Andres Original');

    // Verify foreign keys are populated in DB
    $memoryRecord = \App\Models\Memory::where('external_id', $memoryId)->first();
    $commentRecord = \App\Models\MemoryComment::where('memory_id', $memoryRecord->id)->first();
    $this->assertNotNull($memoryRecord->device_lock_id);
    $this->assertNotNull($commentRecord->device_lock_id);

    // 4. Update guest name in the source of truth (device_locks)
    $deviceLock = \App\Models\DeviceLock::find($memoryRecord->device_lock_id);
    $deviceLock->update(['guest_name' => 'Andres Actualizado']);

    // 5. Query memory feed API -> guestName must dynamically reflect "Andres Actualizado"
    $getMemRes = $this->getJson("/api/v1/memories/{$memoryId}");
    $getMemRes->assertStatus(200)
      ->assertJsonPath('data.guestName', 'Andres Actualizado');

    // 6. Query comment API -> author must dynamically reflect "Andres Actualizado"
    $getCommRes = $this->getJson("/api/v1/memories/{$memoryId}/comments");
    $getCommRes->assertStatus(200)
      ->assertJsonPath('data.0.author', 'Andres Actualizado');

    // 7. Delete device_lock (guest expelled / nullOnDelete)
    $deviceLock->delete();
    $memoryRecord->refresh();
    $commentRecord->refresh();
    $this->assertNull($memoryRecord->device_lock_id);
    $this->assertNull($commentRecord->device_lock_id);

    // 8. Both memory and comment must gracefully fallback to historical text
    $getMemFallback = $this->getJson("/api/v1/memories/{$memoryId}");
    $getMemFallback->assertStatus(200)
      ->assertJsonPath('data.guestName', 'Andres Original');

    $getCommFallback = $this->getJson("/api/v1/memories/{$memoryId}/comments");
    $getCommFallback->assertStatus(200)
      ->assertJsonPath('data.0.author', 'Andres Original');
  }

  public function test_rejects_gif_and_documents_and_accepts_valid_media(): void
  {
    // Checkin guest
    $this->postJson('/api/v1/checkin', [
      'eventCode' => 'XV-NATALIA',
      'tableCode' => 'Mesa 01 · Principal',
      'guestName' => 'Claudia Mendoza',
      'deviceId' => 'dev-claudia-file',
      'actorKey' => 'actor-claudia-file',
    ])->assertStatus(201);

    // 1. Intentar subir un GIF -> Debe fallar con 422
    $gifFile = \Illuminate\Http\UploadedFile::fake()->create('animated.gif', 500, 'image/gif');
    $resGif = $this->withHeaders(['X-Device-Id' => 'dev-claudia-file'])
      ->postJson('/api/v1/memories', [
        'eventCode' => 'XV-NATALIA',
        'guestName' => 'Claudia Mendoza',
        'tableCode' => 'Mesa 01 · Principal',
        'ownerDeviceId' => 'dev-claudia-file',
        'ownerActorKey' => 'actor-claudia-file',
        'caption' => 'Mi gif animado',
        'files' => [$gifFile],
      ]);
    $resGif->assertStatus(422)
      ->assertJsonValidationErrors(['files.0']);

    // 2. Intentar subir un PDF -> Debe fallar con 422
    $pdfFile = \Illuminate\Http\UploadedFile::fake()->create('documento.pdf', 300, 'application/pdf');
    $resPdf = $this->withHeaders(['X-Device-Id' => 'dev-claudia-file'])
      ->postJson('/api/v1/memories', [
        'eventCode' => 'XV-NATALIA',
        'guestName' => 'Claudia Mendoza',
        'tableCode' => 'Mesa 01 · Principal',
        'ownerDeviceId' => 'dev-claudia-file',
        'ownerActorKey' => 'actor-claudia-file',
        'caption' => 'Mi pdf',
        'files' => [$pdfFile],
      ]);
    $resPdf->assertStatus(422)
      ->assertJsonValidationErrors(['files.0']);

    // 3. Intentar subir un archivo Excel -> Debe fallar con 422
    $excelFile = \Illuminate\Http\UploadedFile::fake()->create('invitados.xlsx', 100, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    $resExcel = $this->withHeaders(['X-Device-Id' => 'dev-claudia-file'])
      ->postJson('/api/v1/memories', [
        'eventCode' => 'XV-NATALIA',
        'guestName' => 'Claudia Mendoza',
        'tableCode' => 'Mesa 01 · Principal',
        'ownerDeviceId' => 'dev-claudia-file',
        'ownerActorKey' => 'actor-claudia-file',
        'caption' => 'Mi excel',
        'files' => [$excelFile],
      ]);
    $resExcel->assertStatus(422)
      ->assertJsonValidationErrors(['files.0']);

    // 4. Subir imagen válida (ej. webp / png / jpg) -> Debe aceptarse con 201
    $webpFile = \Illuminate\Http\UploadedFile::fake()->create('foto_real.webp', 800, 'image/webp');
    $resOk = $this->withHeaders(['X-Device-Id' => 'dev-claudia-file'])
      ->postJson('/api/v1/memories', [
        'eventCode' => 'XV-NATALIA',
        'guestName' => 'Claudia Mendoza',
        'tableCode' => 'Mesa 01 · Principal',
        'ownerDeviceId' => 'dev-claudia-file',
        'ownerActorKey' => 'actor-claudia-file',
        'caption' => 'Foto en alta calidad',
        'files' => [$webpFile],
      ]);
    $resOk->assertStatus(201)
      ->assertJsonPath('data.guestName', 'Claudia Mendoza');
  }
}
