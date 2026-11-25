<?php

namespace App\Services;

use App\Actions\ChunkUpload\AssembleChunkUploadAction;
use App\Actions\ChunkUpload\InitChunkUploadAction;
use App\Actions\ChunkUpload\StoreChunkPartAction;
use App\DTOs\ChunkUpload\InitChunkUploadData;
use App\DTOs\ChunkUpload\StoreChunkPartData;

final readonly class ChunkUploadService
{
  public function __construct(
    private InitChunkUploadAction $initUploadAction,
    private StoreChunkPartAction $storeChunkAction,
    private AssembleChunkUploadAction $assembleAction,
  ) {}

  public function initUpload(InitChunkUploadData $data): array
  {
    return $this->initUploadAction->execute($data);
  }

  public function storeChunk(StoreChunkPartData $data): array
  {
    return $this->storeChunkAction->execute($data);
  }

  public function assembleToTargetDisk(
    string $uploadKey,
    string $targetDisk,
    string $destDir,
    string $blobKey,
    ?string $actorKey,
    ?string $deviceId,
  ): array {
    return $this->assembleAction->execute($uploadKey, $targetDisk, $destDir, $blobKey, $actorKey, $deviceId);
  }
}
