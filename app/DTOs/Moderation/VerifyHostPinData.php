<?php

namespace App\DTOs\Moderation;

final readonly class VerifyHostPinData
{
  public function __construct(
    public string $pin,
  ) {}
}
