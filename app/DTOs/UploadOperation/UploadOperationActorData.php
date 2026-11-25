<?php

namespace App\DTOs\UploadOperation;

use App\DTOs\Shared\HasSafeArray;

final readonly class UploadOperationActorData
{
  use HasSafeArray;

  public function __construct(
    public ?string $operationKey = null,
    public ?string $eventCode = null,
    public ?string $deviceId = null,
    public ?string $actorKey = null,
    public ?string $hostPin = null,
  ) {}

  public static function fromArray(array $data): self
  {
    return new self(
      operationKey: self::get($data, 'operationKey'),
      eventCode: self::get($data, 'eventCode'),
      deviceId: self::get($data, 'deviceId'),
      actorKey: self::get($data, 'actorKey'),
      hostPin: self::get($data, 'hostPin'),
    );
  }
}
