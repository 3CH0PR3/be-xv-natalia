<?php

namespace App\Support\Cache;

use Illuminate\Support\Facades\Cache;

/**
 * Helper genérico de caché por scope + módulo para XV Natalia.
 */
final class ScopedCache
{
  public static function index(
    string   $scope,
    string   $module,
    array    $params,
    int      $ttl,
    \Closure $callback,
  ): mixed {
    ksort($params);
    $v   = CacheVersion::get($scope, $module);
    $key = "{$scope}:{$module}:index:v{$v}:" . md5(json_encode($params));

    return self::remember($key, $ttl, $callback);
  }

  public static function item(
    string     $scope,
    string     $module,
    int|string $id,
    int        $ttl,
    \Closure   $callback,
  ): mixed {
    return self::remember("{$scope}:{$module}:{$id}", $ttl, $callback);
  }

  public static function forgetItem(string $scope, string $module, int|string $id): void
  {
    Cache::forget("{$scope}:{$module}:{$id}");
  }

  public static function invalidate(string $scope, string $module): void
  {
    CacheVersion::bump($scope, $module);
  }

  public static function remember(string $key, int $ttl, \Closure $callback): mixed
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
