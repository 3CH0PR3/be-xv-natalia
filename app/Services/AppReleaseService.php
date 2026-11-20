<?php

namespace App\Services;

use App\Actions\AppRelease\ListAppReleasesAction;
use App\Actions\AppRelease\SyncAppReleasesAction;
use App\DTOs\AppRelease\SyncAppReleasesData;
use Illuminate\Support\Collection;

class AppReleaseService
{
  public function __construct(
    private readonly ListAppReleasesAction $listReleases,
    private readonly SyncAppReleasesAction $syncReleases,
  ) {}

  public function listReleases(): Collection
  {
    return $this->listReleases->execute();
  }

  public function syncReleases(SyncAppReleasesData $data, ?string $eventCode = null): Collection
  {
    return $this->syncReleases->execute($data, $eventCode);
  }
}
