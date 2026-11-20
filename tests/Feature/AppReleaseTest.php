<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AppReleaseTest extends TestCase
{
  use RefreshDatabase;

  public function test_can_sync_and_list_releases(): void
  {
    $payload = [
      'buildId' => 'build-1234',
      'releases' => [
        [
          'version' => '1.4.17',
          'type' => 'minor',
          'date' => '14 Nov 2026',
          'title' => 'Lanzamiento de API Laravel 11',
          'highlights' => ['Arquitectura completa por capas', 'WebSockets con Reverb'],
        ],
      ],
    ];

    // Public release sync -> 200
    $syncRes = $this->postJson('/api/v1/releases/sync', $payload);
    $syncRes->assertStatus(200)
      ->assertJsonPath('data.0.version', '1.4.17');

    $listRes = $this->getJson('/api/v1/releases');
    $listRes->assertStatus(200)
      ->assertJsonCount(1, 'data')
      ->assertJsonPath('data.0.version', '1.4.17');

    // Test syncing 55 releases passes validation (> 50 limit)
    $manyReleases = [];
    for ($i = 1; $i <= 55; $i++) {
      $manyReleases[] = [
        'version' => "1.0.{$i}",
        'type' => 'patch',
        'title' => "Parche {$i}",
      ];
    }
    $manySyncRes = $this->postJson('/api/v1/releases/sync', [
      'releases' => $manyReleases,
    ]);
    $manySyncRes->assertStatus(200);
  }
}
