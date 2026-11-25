<?php

namespace App\DTOs\Shared;

trait HasSafeArray
{
  protected static function get(array $data, string $key): mixed
  {
    return $data[$key] ?? null;
  }
}
