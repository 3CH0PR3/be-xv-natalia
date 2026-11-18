<?php

namespace App\Actions\AppRelease;

use App\DTOs\AppRelease\SyncAppReleasesData;
use App\Events\AppReleasePublished;
use App\Http\Resources\Api\V1\AppReleaseResource;
use App\Models\AppRelease;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SyncAppReleasesAction
{
  public function execute(SyncAppReleasesData $data, ?string $eventCode = null): Collection
  {
    $hasLatestChange = false;
    $latestVersion = (string) ($data->releases[0]['version'] ?? '');

    foreach ($data->releases as $entry) {
      $existing = AppRelease::where('version', $entry['version'])->first();
      $entryBuiltAtMs = (int) ($entry['builtAtMs'] ?? ($entry['version'] === $latestVersion ? $data->builtAtMs : 0));

      $model = AppRelease::updateOrCreate(
        ['version' => $entry['version']],
        [
          'version_order' => AppRelease::computeVersionOrder($entry['version']),
          'type' => $entry['type'] ?? 'minor',
          'date_label' => $entry['date'] ?? now()->format('d M Y'),
          'title' => $entry['title'],
          'highlights' => !empty($entry['highlights']) ? $entry['highlights'] : [$entry['title']],
          'commit_sha' => $entry['commitSha'] ?? null,
          'build_id' => $entry['buildId'] ?? $data->buildId,
          'built_at_ms' => $entryBuiltAtMs ?: ($existing?->built_at_ms ?? 0),
          'applied_at_ms' => $existing?->applied_at_ms ?? (int) round(microtime(true) * 1000),
        ]
      );

      if ($entry['version'] === $latestVersion && ($model->wasRecentlyCreated || $model->wasChanged())) {
        $hasLatestChange = true;
      }
    }

    $ordered = AppRelease::latestOrder()->get();

    if ($hasLatestChange && $ordered->isNotEmpty()) {
      $targetCode = $eventCode ?: config('xv.default_event_code', 'XV-NATALIA');
      $latestResource = (new AppReleaseResource($ordered->first()))->resolve();
      DB::afterCommit(static function () use ($targetCode, $latestResource): void {
        broadcast(new AppReleasePublished($targetCode, $latestResource))->toOthers();
      });
    }

    return $ordered;
  }
}
