<?php

namespace App\DTOs\Table;

final readonly class ValidateQrData
{
  public function __construct(
    public string $eventCode,
    public string $tableCode,
    public ?string $token,
    public ?string $rawPayload,
  ) {}
}
