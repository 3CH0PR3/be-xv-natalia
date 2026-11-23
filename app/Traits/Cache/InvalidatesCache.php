<?php

namespace App\Traits\Cache;

use App\Support\Cache\CacheVersion;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Trait for Redis cache invalidation (single-key, SCAN-based pattern, and version-scoped).
 */
trait InvalidatesCache
{
  protected function forgetItem(string $key): void
  {
    Cache::forget($key);
    try {
      Cache::store('redis')->forget($key);
    } catch (\Throwable) {
      // Ignorar si redis no está configurado
    }
  }

  protected function invalidateScope(string $scope, string $module): void
  {
    CacheVersion::bump($scope, $module);
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
      Log::error('Redis scanAndDelete error: ' . $e->getMessage(), ['pattern' => $logicalPattern, 'exception' => $e]);
    }
  }
}
