<?php

namespace App\Actions\AppRelease;

use App\Models\AppRelease;
use App\Support\Cache\Keys\EventKeys;
use App\Traits\Cache\CacheableCache;
use Illuminate\Support\Collection;

class ListAppReleasesAction
{
  use CacheableCache;

  public function execute(): Collection
  {
    return $this->remember(
      EventKeys::appReleases(),
      3600,
      fn () => AppRelease::latestOrder()->get()
    );
  }
}
