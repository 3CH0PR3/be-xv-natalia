<?php

namespace App\DTOs\ChunkUpload;

use App\DTOs\Shared\HasSafeArray;
use Illuminate\Http\UploadedFile;

final readonly class StoreChunkPartData
{
  use HasSafeArray;

  public function __construct(
    public string $uploadKey,
    public int $chunkIndex,
    public UploadedFile $chunkFile,
    public ?string $actorKey = null,
    public ?string $deviceId = null,
  ) {}

  public static function fromArray(array $data): self
  {
    return new self(
      uploadKey: trim((string) self::get($data, 'uploadKey')),
      chunkIndex: (int) self::get($data, 'chunkIndex'),
      chunkFile: self::get($data, 'chunkFile'),
      actorKey: self::get($data, 'actorKey'),
      deviceId: self::get($data, 'deviceId'),
    );
  }
}
