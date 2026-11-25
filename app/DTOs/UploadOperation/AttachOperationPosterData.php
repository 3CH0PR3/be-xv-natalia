<?php

namespace App\DTOs\UploadOperation;

use App\DTOs\Shared\HasSafeArray;
use Illuminate\Http\UploadedFile;

final readonly class AttachOperationPosterData
{
  use HasSafeArray;

  public function __construct(
    public string $operationKey,
    public string $fileKey,
    public UploadedFile $posterFile,
    public ?string $eventCode = null,
    public ?string $deviceId = null,
    public ?string $actorKey = null,
    public ?string $hostPin = null,
  ) {}

  public static function fromArray(array $data): self
  {
    return new self(
      operationKey: trim((string) self::get($data, 'operationKey')),
      fileKey: trim((string) self::get($data, 'fileKey')),
      posterFile: self::get($data, 'posterFile'),
      eventCode: self::get($data, 'eventCode'),
      deviceId: self::get($data, 'deviceId'),
      actorKey: self::get($data, 'actorKey'),
      hostPin: self::get($data, 'hostPin'),
    );
  }
}
