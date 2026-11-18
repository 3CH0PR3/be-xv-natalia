<?php

namespace App\Traits\Cache;

use Illuminate\Support\Facades\Cache;

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
            $redis = Cache::store('redis')->getRedis();
            $redisPrefix = config('database.redis.options.prefix', '');
            $cachePrefix = config('cache.prefix', '');
            $fullPattern = $redisPrefix . $cachePrefix . $logicalPattern;

            $cursor = '0';
            do {
                [$cursor, $keys] = $redis->scan($cursor, 'MATCH', $fullPattern, 'COUNT', 100);
                if (!empty($keys)) {
                    $redis->del(...$keys);
                }
            } while ($cursor !== '0' && !empty($cursor));
        } catch (\Throwable $e) {
            // Log or fallback safely if Redis scan encounters connection issue
            logger()->warning('Redis scanAndDelete error: ' . $e->getMessage(), ['pattern' => $logicalPattern]);
        }
    }
}
