<?php

namespace App\Support\Cache;

use Illuminate\Support\Facades\Cache;

/**
 * Versionado de caché por scope/módulo.
 *
 * Usa tokens únicos basados en random_bytes.
 * Si Redis desaloja la key por maxmemory-policy,
 * al recrearla con un nuevo token nunca colisiona con keys antiguas.
 */
final class CacheVersion
{
  // Cache en memoria por request para evitar roundtrip a Redis
  private static array $cache = [];

  public static function get(string $scope, string $module): string
  {
    $cacheKey = "{$scope}:{$module}";

    if (isset(self::$cache[$cacheKey])) {
      return self::$cache[$cacheKey];
    }

    $redisKey = self::key($scope, $module);
    $v = Cache::get($redisKey);

    if ($v !== null) {
      return self::$cache[$cacheKey] = (string) $v;
    }

    $new = self::token();

    // SET NX atómico: si otro proceso ganó, usamos el suyo
    $stored = Cache::add($redisKey, $new, null)
      ? $new
      : (string) (Cache::get($redisKey) ?? $new);

    return self::$cache[$cacheKey] = $stored;
  }

  public static function bump(string $scope, string $module): string
  {
    $new     = self::token();
    $cacheKey = "{$scope}:{$module}";

    Cache::forever(self::key($scope, $module), $new);

    unset(self::$cache[$cacheKey]);

    return $new;
  }

  /**
   * Resetea el cache en memoria (útil en tests y entre requests en Octane).
   */
  public static function flush(): void
  {
    self::$cache = [];
  }

  private static function key(string $scope, string $module): string
  {
    return "{$scope}:{$module}:version";
  }

  private static function token(): string
  {
    return bin2hex(random_bytes(6));
  }
}
