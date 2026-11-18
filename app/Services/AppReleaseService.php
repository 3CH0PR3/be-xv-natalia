<?php

namespace App\Services;

use App\Actions\AppRelease\SyncAppReleasesAction;
use App\DTOs\AppRelease\SyncAppReleasesData;
use App\Models\AppRelease;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class AppReleaseService
{
    public function __construct(
        private readonly SyncAppReleasesAction $syncReleases,
    ) {}

    public function listReleases(): Collection
    {
        return Cache::store('redis')->remember(
            'app:releases:list',
            3600,
            fn () => AppRelease::latestOrder()->get()
        );
    }

    public function syncReleases(SyncAppReleasesData $data, ?string $eventCode = null): Collection
    {
        $releases = $this->syncReleases->execute($data, $eventCode);
        Cache::store('redis')->forget('app:releases:list');
        return $releases;
    }
}
