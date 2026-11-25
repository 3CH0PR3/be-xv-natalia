<?php

namespace App\Actions\ChunkUpload;

use App\DTOs\ChunkUpload\StoreChunkPartData;
use App\Exceptions\DomainRuleException;
use App\Support\Storage\ChunkUploadStoragePolicy;
use Illuminate\Support\Facades\Storage;

final readonly class StoreChunkPartAction
{
  public function execute(StoreChunkPartData $data): array
  {
    $meta = ChunkUploadStoragePolicy::readAndAuthorizeMeta($data->uploadKey, $data->actorKey, $data->deviceId);
    $totalChunks = (int) ($meta['totalChunks'] ?? 0);

    if ($data->chunkIndex < 0 || $data->chunkIndex >= $totalChunks) {
      throw new DomainRuleException('Índice de fragmento fuera de rango.', 422);
    }

    $disk = Storage::disk(ChunkUploadStoragePolicy::CHUNK_DISK);
    $chunkPath = ChunkUploadStoragePolicy::chunkFilePath($data->uploadKey, $data->chunkIndex);
    $stream = fopen($data->chunkFile->getRealPath(), 'rb');
    if ($stream === false) {
      throw new DomainRuleException('No se pudo leer el fragmento recibido.', 500);
    }

    try {
      $disk->put($chunkPath, $stream);
    } finally {
      if (is_resource($stream)) {
        fclose($stream);
      }
    }

    return [
      'uploadKey' => $data->uploadKey,
      'chunkIndex' => $data->chunkIndex,
      'received' => true,
      'bytesReceived' => (int) ($data->chunkFile->getSize() ?: 0),
    ];
  }
}
