<?php

namespace App\DTOs\UploadOperation;

use App\DTOs\Shared\HasSafeArray;
use App\Models\DeviceLock;

final readonly class CreateUploadOperationData
{
  use HasSafeArray;

  public function __construct(
    public ?string $eventCode,
    public ?DeviceLock $deviceLock,
    public ?string $deviceId,
    public ?string $actorKey,
    public array $payload,
    public ?string $requestOrigin = null,
  ) {}

  public static function fromArray(array $data): self
  {
    return new self(
      eventCode: self::get($data, 'eventCode'),
      deviceLock: self::get($data, 'deviceLock'),
      deviceId: self::get($data, 'deviceId'),
      actorKey: self::get($data, 'actorKey'),
      payload: (array) (self::get($data, 'payload') ?: []),
      requestOrigin: self::get($data, 'requestOrigin'),
    );
  }
}
