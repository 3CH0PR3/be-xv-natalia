<?php

namespace App\DTOs\ChunkUpload;

use App\DTOs\Shared\HasSafeArray;

final readonly class InitChunkUploadData
{
  use HasSafeArray;

  public function __construct(
    public string $filename,
    public string $mimeType,
    public int $totalSize,
    public int $totalChunks,
    public ?string $actorKey = null,
    public ?string $deviceId = null,
  ) {}

  public static function fromArray(array $data): self
  {
    $mime = trim((string) (self::get($data, 'mimeType') ?: 'application/octet-stream'));

    return new self(
      filename: trim((string) self::get($data, 'filename')),
      mimeType: $mime,
      totalSize: (int) self::get($data, 'totalSize'),
      totalChunks: (int) self::get($data, 'totalChunks'),
      actorKey: self::get($data, 'actorKey'),
      deviceId: self::get($data, 'deviceId'),
    );
  }
}
