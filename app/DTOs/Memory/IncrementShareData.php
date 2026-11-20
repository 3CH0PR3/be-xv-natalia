<?php

namespace App\DTOs\Memory;

final readonly class IncrementShareData
{
  public function __construct(
    public string $externalId,
    public ?string $actorKey = null,
    public ?string $deviceId = null,
    public ?int $deviceLockId = null,
  ) {}
}
