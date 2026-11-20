<?php

namespace App\Actions\AppRelease;

use App\Models\AppRelease;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class ListAppReleasesAction
{
  public function execute(): Collection
  {
    return Cache::store('redis')->remember(
      'app:releases:list',
      3600,
      fn () => AppRelease::latestOrder()->get()
    );
  }
}
