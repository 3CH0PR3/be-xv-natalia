<?php

namespace App\Traits\Cache;

use App\Support\Cache\CacheContext;
use Illuminate\Support\Facades\Cache;

trait CacheableCache
{
  protected function remember(string $key, int $ttl, callable $callback): mixed
  {
    $hit = true;

    $result = Cache::remember($key, $ttl, function () use ($callback, &$hit) {
      $hit = false;
      return $callback();
    });

    CacheContext::record($key, $hit);

    return $result;
  }
}
