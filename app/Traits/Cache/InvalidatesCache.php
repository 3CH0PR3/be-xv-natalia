<?php

namespace App\Traits\Cache;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Trait for selective Redis cache invalidation using SCAN.
 * Replicates the pattern from be-gea-wrc.
 */
trait InvalidatesCache
{
  protected function forgetItem(string $key): void
  {
    Cache::store('redis')->forget($key);
  }

  protected function scanAndDelete(string $logicalPattern): void
  {
    try {
      $redisStore = Cache::store('redis');
      $rawRedis = $redisStore->getStore()->connection()->client();
      $cachePrefix = (string) config('cache.prefix', '');
      $fullPattern = $cachePrefix . $logicalPattern;

      $it = null;
      while ($keys = $rawRedis->scan($it, $fullPattern, 100)) {
        if (!empty($keys)) {
          $rawRedis->del($keys);
        }
      }
    } catch (\Throwable $e) {
      // Log or fallback safely if Redis scan encounters connection issue
      Log::error('Redis scanAndDelete error: ' . $e->getMessage(), ['pattern' => $logicalPattern, 'exception' => $e]);
    }
  }
}
