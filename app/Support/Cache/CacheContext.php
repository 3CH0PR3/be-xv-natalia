<?php

namespace App\Support\Cache;

/**
 * Registro en memoria (por request) del estado de caché.
 *
 * Un Action llama a CacheContext::record() después de ejecutar remember().
 * Se resetea automáticamente en cada request.
 */
final class CacheContext
{
  private static bool   $hit      = false;
  private static string $key      = '';
  private static bool   $recorded = false;

  public static function record(string $key, bool $hit): void
  {
    self::$key      = $key;
    self::$hit      = $hit;
    self::$recorded = true;
  }

  public static function isHit(): bool
  {
    return self::$hit;
  }

  public static function getKey(): string
  {
    return self::$key;
  }

  public static function wasRecorded(): bool
  {
    return self::$recorded;
  }

  public static function reset(): void
  {
    self::$recorded = false;
    self::$hit      = false;
    self::$key      = '';
  }
}
