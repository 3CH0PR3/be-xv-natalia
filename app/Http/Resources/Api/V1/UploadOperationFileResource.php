<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UploadOperationFileResource extends JsonResource
{
  public function __construct($resource, private readonly ?string $fallbackOrigin = null)
  {
    parent::__construct($resource);
  }

  public function toArray(Request $request): array
  {
    $origin = $this->fallbackOrigin ?: ($this->relationLoaded('operation') ? $this->operation?->request_origin : null);
    $receivedChunks = $this->receivedChunksList();

    return [
      'id' => $this->file_key,
      'fileKey' => $this->file_key,
      'originalName' => $this->original_name,
      'mimeType' => $this->mime_type,
      'mediaType' => $this->media_type,
      'byteSize' => (int) $this->byte_size,
      'uploadedBytes' => (int) $this->uploaded_bytes,
      'chunkSize' => (int) $this->chunk_size,
      'totalChunks' => (int) $this->total_chunks,
      'receivedChunks' => $receivedChunks,
      'receivedChunksCount' => count($receivedChunks),
      'sortOrder' => (int) $this->sort_order,
      'status' => $this->status,
      'progressPercent' => $this->progressPercent(),
      'posterUrl' => $this->resolvePublicUrl($this->poster_path, $origin),
      'finalUrl' => $this->resolvePublicUrl($this->final_path, $origin),
      'memoryMediaItemId' => $this->relationLoaded('mediaItem') ? $this->mediaItem?->external_id : null,
      'errorCode' => $this->error_code,
      'errorMessage' => $this->error_message,
      'startedAt' => optional($this->started_at)->toIso8601String(),
      'uploadedAt' => optional($this->uploaded_at)->toIso8601String(),
      'processedAt' => optional($this->processed_at)->toIso8601String(),
    ];
  }
}
