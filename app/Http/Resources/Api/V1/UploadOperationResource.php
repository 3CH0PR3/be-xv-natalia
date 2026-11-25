<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UploadOperationResource extends JsonResource
{
  public function toArray(Request $request): array
  {
    $origin = $this->request_origin;
    $files = $this->relationLoaded('files')
      ? $this->files->map(fn ($file) => (new UploadOperationFileResource($file, $origin))->toArray($request))->values()->all()
      : [];

    return [
      'id' => $this->operation_key,
      'operationKey' => $this->operation_key,
      'eventCode' => $this->relationLoaded('event') ? ($this->event?->code ?? '') : '',
      'memoryExternalId' => $this->memory_external_id,
      'memoryId' => $this->relationLoaded('memory') && $this->memory ? $this->memory->external_id : $this->memory_external_id,
      'ownerDeviceId' => $this->owner_device_id ?? '',
      'ownerActorKey' => $this->owner_actor_key ?? '',
      'guestName' => $this->guest_name,
      'tableCode' => $this->table_code,
      'momentTag' => $this->moment_tag,
      'caption' => $this->caption ?? '',
      'status' => $this->status,
      'totalFiles' => (int) $this->total_files,
      'uploadedFiles' => (int) $this->uploaded_files,
      'completedFiles' => (int) $this->completed_files,
      'failedFiles' => (int) $this->failed_files,
      'totalBytes' => (int) $this->total_bytes,
      'uploadedBytes' => (int) $this->uploaded_bytes,
      'progressPercent' => $this->progressPercent(),
      'errorSummary' => $this->error_summary,
      'startedAt' => optional($this->started_at)->toIso8601String(),
      'completedAt' => optional($this->completed_at)->toIso8601String(),
      'createdAt' => optional($this->created_at)->toIso8601String(),
      'updatedAt' => optional($this->updated_at)->toIso8601String(),
      'files' => $files,
    ];
  }
}
