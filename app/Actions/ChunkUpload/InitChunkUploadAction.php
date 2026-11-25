<?php

namespace App\Actions\ChunkUpload;

use App\DTOs\ChunkUpload\InitChunkUploadData;
use App\Exceptions\DomainRuleException;
use App\Support\Storage\ChunkUploadStoragePolicy;
use App\Support\Validation\MediaFilePolicy;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

final readonly class InitChunkUploadAction
{
  public function execute(InitChunkUploadData $data): array
  {
    $policyError = MediaFilePolicy::validateFilenameAndMime($data->filename, $data->mimeType);
    if ($policyError !== null) {
      throw new DomainRuleException($policyError, 422);
    }

    $uploadKey = 'ul_' . Str::lower(Str::random(24));
    $extension = strtolower((string) pathinfo($data->filename, PATHINFO_EXTENSION));
    $meta = [
      'uploadKey' => $uploadKey,
      'filename' => $data->filename,
      'extension' => $extension,
      'mimeType' => strtolower(trim($data->mimeType)) ?: 'application/octet-stream',
      'totalSize' => $data->totalSize,
      'totalChunks' => $data->totalChunks,
      'actorKey' => $data->actorKey,
      'deviceId' => $data->deviceId,
      'createdAt' => time(),
    ];

    $disk = Storage::disk(ChunkUploadStoragePolicy::CHUNK_DISK);
    $disk->makeDirectory(ChunkUploadStoragePolicy::chunksDir($uploadKey));
    $disk->put(ChunkUploadStoragePolicy::metaPath($uploadKey), json_encode($meta, JSON_THROW_ON_ERROR));

    return [
      'uploadKey' => $uploadKey,
      'chunkSize' => ChunkUploadStoragePolicy::DEFAULT_CHUNK_SIZE,
    ];
  }
}
