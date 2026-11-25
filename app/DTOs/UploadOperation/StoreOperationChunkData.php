<?php

namespace App\DTOs\UploadOperation;

use App\DTOs\Shared\HasSafeArray;
use Illuminate\Http\UploadedFile;

final readonly class StoreOperationChunkData
{
  use HasSafeArray;

  public function __construct(
    public string $operationKey,
    public string $fileKey,
    public int $chunkIndex,
    public ?UploadedFile $chunkFile = null,
    public ?string $rawBinaryBody = null,
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
      chunkIndex: (int) self::get($data, 'chunkIndex'),
      chunkFile: self::get($data, 'chunkFile'),
      rawBinaryBody: self::get($data, 'rawBinaryBody'),
      eventCode: self::get($data, 'eventCode'),
      deviceId: self::get($data, 'deviceId'),
      actorKey: self::get($data, 'actorKey'),
      hostPin: self::get($data, 'hostPin'),
    );
  }
}
