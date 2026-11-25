<?php

namespace App\DTOs\UploadOperation;

use App\DTOs\Shared\HasSafeArray;

final readonly class FailOperationFileData
{
  use HasSafeArray;

  public function __construct(
    public string $operationKey,
    public string $fileKey,
    public string $errorCode,
    public string $errorMessage,
    public ?string $eventCode = null,
    public ?string $deviceId = null,
    public ?string $actorKey = null,
    public ?string $hostPin = null,
  ) {}

  public static function fromArray(array $data): self
  {
    $code = trim((string) (self::get($data, 'errorCode') ?: 'CLIENT_UPLOAD_FAILED'));

    return new self(
      operationKey: trim((string) self::get($data, 'operationKey')),
      fileKey: trim((string) self::get($data, 'fileKey')),
      errorCode: $code,
      errorMessage: (string) self::get($data, 'errorMessage'),
      eventCode: self::get($data, 'eventCode'),
      deviceId: self::get($data, 'deviceId'),
      actorKey: self::get($data, 'actorKey'),
      hostPin: self::get($data, 'hostPin'),
    );
  }
}
