<?php

namespace App\DTOs\Moderation;

final readonly class UpdateMemoryStatusData
{
  public function __construct(
    public string $externalId,
    public string $status,
  ) {}
}
