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

        // Test delete memory by owner
        $delRes = $this->withHeaders([
            'X-Device-Id' => 'dev-valeria-1',
            'X-Actor-Key' => 'actor-valeria-1',
        ])->deleteJson("/api/v1/memories/{$externalId}");
        $delRes->assertStatus(200);

        $this->assertDatabaseMissing('memories', ['external_id' => $externalId]);
    }
}
