<?php

namespace App\DTOs\Moderation;

final readonly class ChangeHostPinData
{
  public function __construct(
    public string $currentPin,
    public string $newPin,
  ) {}
}
