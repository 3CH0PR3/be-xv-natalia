<?php

namespace App\Services;

use App\Events\MemoryCreated;
use App\Events\MemoryUpdated;
use App\Events\UploadOperationUpdated;
use App\Exceptions\DomainRuleException;
use App\Http\Resources\Api\V1\MemoryResource;
use App\Http\Resources\Api\V1\UploadOperationFileResource;
use App\Jobs\ProcessUploadOperationFileJob;
use App\Models\DeviceLock;
use App\Models\Event;
use App\Models\Memory;
use App\Models\UploadOperation;
use App\Models\UploadOperationFile;
use App\Support\Cache\Keys\EventKeys;
use App\Support\Validation\MediaFilePolicy;
use App\Traits\Cache\InvalidatesCache;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class UploadOperationService
{
  use InvalidatesCache;

  public const CHUNK_DISK = 'local';
  public const DEFAULT_CHUNK_SIZE = 16777216;
  public const MAX_CHUNK_SIZE = 33554432;

  public function resolveEvent(?string $eventCode = null): Event
  {
    $code = trim((string) ($eventCode ?: config('xv.default_event_code', 'XV-NATALIA')));
    return Event::byCode($code)->firstOrFail();
  }

  public function stagingPathFor(Event $event, UploadOperation $operation, UploadOperationFile $file): string
  {
    return "memories/{$event->code}/{$operation->memory_external_id}/.staging-{$file->file_key}.tmp";
  }

  public function finalPathFor(Event $event, UploadOperation $operation, UploadOperationFile $file): string
  {
    $extension = strtolower(pathinfo($file->original_name, PATHINFO_EXTENSION));
    if ($extension === '') {
      $extension = $file->media_type === 'video' ? 'mp4' : 'jpg';
    }
    $safeStem = Str::slug(pathinfo($file->original_name, PATHINFO_FILENAME)) ?: 'media';
    return "memories/{$event->code}/{$operation->memory_external_id}/{$safeStem}-{$file->file_key}.{$extension}";
  }

  public function listOperations(Event $event, ?string $deviceId, ?string $actorKey): Collection
  {
    if (!$deviceId && !$actorKey) {
      return new Collection();
    }

    $operations = UploadOperation::with(['event', 'memory', 'files.mediaItem'])
      ->forEvent($event->id)
      ->visible()
      ->ownedBy($deviceId, $actorKey)
      ->orderByDesc('created_at')
      ->limit(25)
      ->get();

    foreach ($operations as $op) {
      if ($op->isTerminal()) {
        continue;
      }
      foreach ($op->files as $file) {
        if ($file->status === UploadOperationFile::STATUS_UPLOADING || $file->status === UploadOperationFile::STATUS_QUEUED_UPLOAD) {
          $redisChunks = $this->readRedisChunks($file->id);
          if (count($redisChunks) > count($file->receivedChunksList())) {
            $merged = $this->mergeIndices($file->receivedChunksList(), $redisChunks);
            $file->received_chunks = $merged;
            $file->uploaded_bytes = $this->computeUploadedBytes($file->byte_size, $file->chunk_size, $file->total_chunks, $merged);
          }
        }
      }
    }

    return $operations;
  }

  public function findOperationForActor(
    string $operationKey,
    Event $event,
    ?string $deviceId,
    ?string $actorKey,
    bool $isHost = false,
  ): UploadOperation {
    $operation = UploadOperation::with(['event', 'memory', 'files.mediaItem'])
      ->forEvent($event->id)
      ->where('operation_key', trim($operationKey))
      ->first();

    if (!$operation) {
      throw new DomainRuleException(
        'UPLOAD_OPERATION_NOT_FOUND',
        'No se encontró la operación de subida solicitada.',
        404
      );
    }

    if (!$isHost && !$operation->isOwnedBy($deviceId, $actorKey)) {
      throw new DomainRuleException(
        'FORBIDDEN_UPLOAD_OPERATION',
        'No tienes permiso para acceder a esta operación de subida.',
        403
      );
    }

    return $operation;
  }

  public function createOperation(
    Event $event,
    ?DeviceLock $deviceLock,
    ?string $deviceId,
    ?string $actorKey,
    array $payload,
    ?string $requestOrigin = null,
  ): UploadOperation {
    $operationKey = trim((string) ($payload['operationKey'] ?? '')) ?: ('uop-' . Str::lower((string) Str::ulid()));
    $ownerDeviceId = trim((string) ($deviceId ?: ($deviceLock?->device_id ?? ''))) ?: null;
    $ownerActorKey = trim((string) ($actorKey ?: ($deviceLock?->actor_key ?? ''))) ?: null;

    $existing = UploadOperation::with(['event', 'memory', 'files.mediaItem'])
      ->forEvent($event->id)
      ->where('operation_key', $operationKey)
      ->first();

    if ($existing) {
      if ($existing->isOwnedBy($ownerDeviceId, $ownerActorKey)) {
        return $existing;
      }
      $operationKey = 'uop-' . Str::lower((string) Str::ulid());
    }

    $memoryExternalId = trim((string) ($payload['memoryExternalId'] ?? ''))
      ?: ('xv-nat-' . ((int) round(microtime(true) * 1000)) . '-' . Str::lower(Str::random(6)));
    $guestName = trim((string) ($payload['guestName'] ?? '')) ?: ($deviceLock?->guest_name ?: 'Invitado');
    $tableCode = trim((string) ($payload['tableCode'] ?? '')) ?: ($deviceLock?->table?->code ?: 'Mesa 01 · Principal');
    $momentTag = trim((string) ($payload['momentTag'] ?? '')) ?: 'Fiesta en Vivo';
    $caption = trim((string) ($payload['caption'] ?? ''));

    $rawFiles = is_array($payload['files'] ?? null) ? $payload['files'] : [];
    $targetDisk = (string) config('xv.media_disk', 'public');
    $fileRows = [];

    foreach (array_values($rawFiles) as $index => $rawFile) {
      $originalName = trim((string) ($rawFile['originalName'] ?? "archivo-{$index}"));
      $mimeType = trim((string) ($rawFile['mimeType'] ?? '')) ?: 'application/octet-stream';
      $byteSize = max(0, (int) ($rawFile['byteSize'] ?? 0));
      $chunkSize = min(self::MAX_CHUNK_SIZE, max(1024, (int) ($rawFile['chunkSize'] ?? self::DEFAULT_CHUNK_SIZE)));
      $totalChunks = max(1, (int) ($rawFile['totalChunks'] ?? 1));
      $sortOrder = max(0, (int) ($rawFile['sortOrder'] ?? $index));
      $fileKey = trim((string) ($rawFile['fileKey'] ?? '')) ?: ('m-' . ($index + 1) . '-' . Str::lower(Str::random(8)));

      $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
      $mediaType = MediaFilePolicy::isVideoDescriptor($ext, $mimeType) ? 'video' : 'image';
      $validationError = MediaFilePolicy::validateFilenameAndMime($originalName, $mimeType);

      $status = UploadOperationFile::STATUS_QUEUED_UPLOAD;
      $errorCode = null;
      $errorMessage = null;

      if ($byteSize <= 0) {
        $status = UploadOperationFile::STATUS_FAILED;
        $errorCode = 'EMPTY_FILE';
        $errorMessage = "El archivo \"{$originalName}\" está vacío (0 bytes).";
      } elseif ($validationError !== null) {
        $status = UploadOperationFile::STATUS_FAILED;
        $errorCode = 'INVALID_MEDIA_TYPE';
        $errorMessage = $validationError;
      }

      $fileRows[] = [
        'file_key' => $fileKey,
        'original_name' => $originalName,
        'mime_type' => $mimeType,
        'media_type' => $mediaType,
        'byte_size' => $byteSize,
        'uploaded_bytes' => 0,
        'chunk_size' => $chunkSize,
        'total_chunks' => $totalChunks,
        'received_chunks' => [],
        'sort_order' => $sortOrder,
        'status' => $status,
        'disk' => $targetDisk,
        'temp_dir' => "chunk_uploads/ops/{$operationKey}/{$fileKey}",
        'error_code' => $errorCode,
        'error_message' => $errorMessage,
      ];
    }

    return DB::transaction(function () use (
      $event,
      $deviceLock,
      $operationKey,
      $memoryExternalId,
      $ownerDeviceId,
      $ownerActorKey,
      $guestName,
      $tableCode,
      $momentTag,
      $caption,
      $requestOrigin,
      $fileRows
    ): UploadOperation {
      $operation = UploadOperation::create([
        'operation_key' => $operationKey,
        'event_id' => $event->id,
        'event_table_id' => $deviceLock?->event_table_id,
        'device_lock_id' => $deviceLock?->id,
        'memory_external_id' => $memoryExternalId,
        'owner_device_id' => $ownerDeviceId,
        'owner_actor_key' => $ownerActorKey,
        'guest_name' => $guestName,
        'table_code' => $tableCode,
        'moment_tag' => $momentTag,
        'caption' => $caption,
        'request_origin' => $requestOrigin,
        'status' => UploadOperation::STATUS_QUEUED,
        'started_at' => now(),
      ]);

      foreach ($fileRows as $row) {
        $operation->files()->create($row);
      }

      $fresh = $this->recalculateOperation($operation);

      DB::afterCommit(function () use ($fresh): void {
        $this->broadcastOperation($fresh, false);
      });

      return $fresh;
    });
  }

  public function storeChunk(
    string $operationKey,
    string $fileKey,
    int $chunkIndex,
    UploadedFile $chunkFile,
    Event $event,
    ?string $deviceId,
    ?string $actorKey,
    bool $isHost = false,
  ): UploadOperation {
    $stream = fopen($chunkFile->getRealPath(), 'rb');
    if ($stream === false) {
      throw new DomainRuleException(
        'CHUNK_READ_FAILED',
        'No se pudo leer el fragmento recibido en el servidor.',
        500
      );
    }

    try {
      $ack = $this->storeBinaryChunk(
        operationKey: $operationKey,
        fileKey: $fileKey,
        chunkIndex: $chunkIndex,
        inputStream: $stream,
        event: $event,
        deviceId: $deviceId,
        actorKey: $actorKey,
        isHost: $isHost,
        forceCheckpoint: true,
      );

      if ($ack['operationModel'] instanceof UploadOperation) {
        return $ack['operationModel'];
      }

      return $this->findOperationForActor($operationKey, $event, $deviceId, $actorKey, $isHost);
    } finally {
      if (is_resource($stream)) {
        fclose($stream);
      }
    }
  }

  public function storeBinaryChunk(
    string $operationKey,
    string $fileKey,
    int $chunkIndex,
    $inputStream,
    Event $event,
    ?string $deviceId,
    ?string $actorKey,
    bool $isHost = false,
    bool $forceCheckpoint = false,
  ): array {
    $operation = $this->findOperationForActor($operationKey, $event, $deviceId, $actorKey, $isHost);
    $file = $operation->files()->where('file_key', trim($fileKey))->first();

    if (!$file) {
      throw new DomainRuleException(
        'UPLOAD_FILE_NOT_FOUND',
        'No se encontró el archivo dentro de esta operación de subida.',
        404
      );
    }

    if ($chunkIndex < 0 || $chunkIndex >= $file->total_chunks) {
      throw new DomainRuleException(
        'INVALID_CHUNK_INDEX',
        "Índice de fragmento inválido ({$chunkIndex} de {$file->total_chunks}).",
        422
      );
    }

    if ($file->status === UploadOperationFile::STATUS_COMPLETED) {
      return [
        'operationKey' => $operation->operation_key,
        'fileKey' => $file->file_key,
        'chunkIndex' => $chunkIndex,
        'receivedChunksCount' => $file->total_chunks,
        'totalChunks' => $file->total_chunks,
        'uploadedBytes' => (int) $file->byte_size,
        'status' => $file->status,
        'allReceived' => true,
        'operationModel' => $operation,
      ];
    }

    $targetDisk = $file->disk ?: (string) config('xv.media_disk', 'public');
    $stagingPath = $this->stagingPathFor($event, $operation, $file);
    $offset = $chunkIndex * (int) $file->chunk_size;

    $written = $this->writeStreamAtOffset($targetDisk, $stagingPath, $offset, $inputStream);
    if ($written <= 0 && $file->byte_size > 0) {
      throw new DomainRuleException(
        'EMPTY_CHUNK_BODY',
        "El fragmento #{$chunkIndex} llegó vacío.",
        422
      );
    }

    $redisState = $this->trackRedisChunk($file->id, $chunkIndex);
    $redisAvailable = $redisState['available'];
    $receivedCount = $redisState['count'];
    $allReceivedFast = $redisAvailable && $receivedCount >= $file->total_chunks;

    $prevBucket = $file->total_chunks > 0 ? (int) floor((max(0, $receivedCount - 1) / $file->total_chunks) * 6) : 0;
    $nextBucket = $file->total_chunks > 0 ? (int) floor(($receivedCount / $file->total_chunks) * 6) : 1;

    $needsCheckpoint = $forceCheckpoint
      || !$redisAvailable
      || $chunkIndex === 0
      || $allReceivedFast
      || $file->total_chunks <= 6
      || ($nextBucket > $prevBucket);

    if (!$needsCheckpoint) {
      $estimatedBytes = min(
        (int) $file->byte_size,
        $this->computeUploadedBytes($file->byte_size, $file->chunk_size, $file->total_chunks, $redisState['indices'])
      );

      return [
        'operationKey' => $operation->operation_key,
        'fileKey' => $file->file_key,
        'chunkIndex' => $chunkIndex,
        'receivedChunksCount' => $receivedCount,
        'totalChunks' => (int) $file->total_chunks,
        'uploadedBytes' => $estimatedBytes,
        'status' => UploadOperationFile::STATUS_UPLOADING,
        'allReceived' => false,
        'operationModel' => null,
      ];
    }

    return DB::transaction(function () use ($operation, $file, $chunkIndex, $redisAvailable): array {
      $lockedOp = UploadOperation::where('id', $operation->id)->lockForUpdate()->firstOrFail();
      $lockedFile = UploadOperationFile::where('id', $file->id)->lockForUpdate()->firstOrFail();

      if ($lockedFile->status === UploadOperationFile::STATUS_COMPLETED) {
        return [
          'operationKey' => $lockedOp->operation_key,
          'fileKey' => $lockedFile->file_key,
          'chunkIndex' => $chunkIndex,
          'receivedChunksCount' => (int) $lockedFile->total_chunks,
          'totalChunks' => (int) $lockedFile->total_chunks,
          'uploadedBytes' => (int) $lockedFile->byte_size,
          'status' => $lockedFile->status,
          'allReceived' => true,
          'operationModel' => $lockedOp->fresh(['event', 'memory', 'files.mediaItem']),
        ];
      }

      $prevStatus = $lockedFile->status;
      $received = $lockedFile->receivedChunksList();
      if ($redisAvailable) {
        $received = $this->mergeIndices($received, $this->readRedisChunks($lockedFile->id));
      }
      if (!in_array($chunkIndex, $received, true)) {
        $received[] = $chunkIndex;
        sort($received);
      }

      $uploadedBytes = $this->computeUploadedBytes($lockedFile->byte_size, $lockedFile->chunk_size, $lockedFile->total_chunks, $received);
      $allReceived = count($received) >= $lockedFile->total_chunks;

      $nextStatus = $allReceived ? UploadOperationFile::STATUS_UPLOADED : UploadOperationFile::STATUS_UPLOADING;

      $lockedFile->update([
        'received_chunks' => $received,
        'uploaded_bytes' => $uploadedBytes,
        'status' => $nextStatus,
        'started_at' => $lockedFile->started_at ?: now(),
        'uploaded_at' => $allReceived ? ($lockedFile->uploaded_at ?: now()) : null,
        'error_code' => null,
        'error_message' => null,
      ]);

      $freshOp = $this->recalculateOperation($lockedOp);
      $freshChangedFile = $freshOp->files->firstWhere('id', $lockedFile->id);

      if ($allReceived && !in_array($prevStatus, [UploadOperationFile::STATUS_PROCESSING, UploadOperationFile::STATUS_COMPLETED], true)) {
        $shouldDispatch = $this->acquireJobDispatchLock($lockedFile->id);
        if ($shouldDispatch) {
          DB::afterCommit(function () use ($lockedFile, $freshOp, $freshChangedFile): void {
            $this->broadcastOperation($freshOp, false, $freshChangedFile);
            ProcessUploadOperationFileJob::dispatch($lockedFile->id);
          });
        }
      } else {
        DB::afterCommit(function () use ($freshOp, $freshChangedFile): void {
          $this->broadcastOperation($freshOp, true, $freshChangedFile);
        });
      }

      return [
        'operationKey' => $freshOp->operation_key,
        'fileKey' => $lockedFile->file_key,
        'chunkIndex' => $chunkIndex,
        'receivedChunksCount' => count($received),
        'totalChunks' => (int) $lockedFile->total_chunks,
        'uploadedBytes' => $uploadedBytes,
        'status' => $nextStatus,
        'allReceived' => $allReceived,
        'operationModel' => $freshOp,
      ];
    });
  }

  public function attachPoster(
    string $operationKey,
    string $fileKey,
    UploadedFile $posterFile,
    Event $event,
    ?string $deviceId,
    ?string $actorKey,
    bool $isHost = false,
  ): UploadOperation {
    $operation = $this->findOperationForActor($operationKey, $event, $deviceId, $actorKey, $isHost);
    $file = $operation->files()->where('file_key', trim($fileKey))->first();

    if (!$file) {
      throw new DomainRuleException(
        'UPLOAD_FILE_NOT_FOUND',
        'No se encontró el archivo para adjuntar portada.',
        404
      );
    }

    $ext = strtolower($posterFile->getClientOriginalExtension() ?: 'jpg');
    $safeExt = in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true) ? $ext : 'jpg';
    $targetDisk = $file->disk ?: (string) config('xv.media_disk', 'public');
    $posterPath = "memories/{$event->code}/{$operation->memory_external_id}/posters/{$file->file_key}.{$safeExt}";

    $stream = fopen($posterFile->getRealPath(), 'rb');
    if ($stream !== false) {
      try {
        Storage::disk($targetDisk)->writeStream($posterPath, $stream);
      } finally {
        if (is_resource($stream)) {
          fclose($stream);
        }
      }
    }

    $file->update(['poster_path' => $posterPath]);

    if ($file->memory_media_item_id && $file->mediaItem) {
      $file->mediaItem->update(['poster_path' => $posterPath]);
      if ($operation->memory) {
        $this->invalidateMemoryCaches($operation->memory);
        $this->broadcastMemoryChange($operation->memory, $operation->request_origin, false);
      }
    }

    $freshOp = $operation->fresh(['event', 'memory', 'files.mediaItem']);
    $changedFile = $freshOp->files->firstWhere('id', $file->id);
    $this->broadcastOperation($freshOp, false, $changedFile);

    return $freshOp;
  }

  public function markFileFailed(
    string $operationKey,
    string $fileKey,
    string $errorCode,
    string $errorMessage,
    Event $event,
    ?string $deviceId,
    ?string $actorKey,
    bool $isHost = false,
  ): UploadOperation {
    $operation = $this->findOperationForActor($operationKey, $event, $deviceId, $actorKey, $isHost);
    $file = $operation->files()->where('file_key', trim($fileKey))->first();

    if (!$file) {
      throw new DomainRuleException(
        'UPLOAD_FILE_NOT_FOUND',
        'No se encontró el archivo para registrar el fallo.',
        404
      );
    }

    return $this->markFileFailedById($file->id, $errorCode, $errorMessage);
  }

  public function markFileFailedById(int $fileId, string $errorCode, string $errorMessage): UploadOperation
  {
    return DB::transaction(function () use ($fileId, $errorCode, $errorMessage): UploadOperation {
      $lockedFile = UploadOperationFile::where('id', $fileId)->lockForUpdate()->firstOrFail();
      $lockedOp = UploadOperation::where('id', $lockedFile->upload_operation_id)->lockForUpdate()->firstOrFail();

      if ($lockedFile->status !== UploadOperationFile::STATUS_COMPLETED) {
        $lockedFile->update([
          'status' => UploadOperationFile::STATUS_FAILED,
          'error_code' => Str::limit(trim($errorCode) ?: 'UPLOAD_FAILED', 64, ''),
          'error_message' => Str::limit(trim($errorMessage) ?: 'Error desconocido durante la subida.', 1000, ''),
          'processed_at' => now(),
        ]);
      }

      $freshOp = $this->recalculateOperation($lockedOp);
      $changedFile = $freshOp->files->firstWhere('id', $lockedFile->id);

      Log::error('[UploadOperation] File failed', [
        'operation_key' => $freshOp->operation_key,
        'file_key' => $lockedFile->file_key,
        'original_name' => $lockedFile->original_name,
        'error_code' => $errorCode,
        'error_message' => $errorMessage,
      ]);

      DB::afterCommit(function () use ($freshOp, $changedFile): void {
        $this->clearRedisChunkKeys($changedFile?->id ?? 0);
        $this->broadcastOperation($freshOp, false, $changedFile);
      });

      return $freshOp;
    });
  }

  public function dismissOperation(
    string $operationKey,
    Event $event,
    ?string $deviceId,
    ?string $actorKey,
    bool $isHost = false,
  ): void {
    $operation = $this->findOperationForActor($operationKey, $event, $deviceId, $actorKey, $isHost);
    $operation->update(['dismissed_at' => now()]);
  }

  public function recalculateOperation(UploadOperation $operation): UploadOperation
  {
    $files = $operation->files()->get();
    $totalFiles = $files->count();
    $uploadedFiles = 0;
    $completedFiles = 0;
    $failedFiles = 0;
    $totalBytes = 0;
    $uploadedBytes = 0;
    $hasUploading = false;
    $hasProcessing = false;
    $firstErrorMessage = null;

    foreach ($files as $file) {
      $totalBytes += (int) $file->byte_size;
      $uploadedBytes += (int) $file->uploaded_bytes;

      if (in_array($file->status, [
        UploadOperationFile::STATUS_UPLOADED,
        UploadOperationFile::STATUS_PROCESSING,
        UploadOperationFile::STATUS_COMPLETED,
      ], true)) {
        $uploadedFiles++;
      }

      if ($file->status === UploadOperationFile::STATUS_COMPLETED) {
        $completedFiles++;
      } elseif ($file->status === UploadOperationFile::STATUS_FAILED) {
        $failedFiles++;
        if ($firstErrorMessage === null && $file->error_message) {
          $firstErrorMessage = $file->error_message;
        }
      } elseif (in_array($file->status, [
        UploadOperationFile::STATUS_UPLOADED,
        UploadOperationFile::STATUS_PROCESSING,
      ], true)) {
        $hasProcessing = true;
      } elseif ($file->status === UploadOperationFile::STATUS_UPLOADING) {
        $hasUploading = true;
      }
    }

    $terminalCount = $completedFiles + $failedFiles;
    $completedAt = null;

    if ($totalFiles > 0 && $terminalCount === $totalFiles) {
      if ($completedFiles === $totalFiles) {
        $status = UploadOperation::STATUS_COMPLETED;
      } elseif ($completedFiles > 0 && $failedFiles > 0) {
        $status = UploadOperation::STATUS_COMPLETED_WITH_ERRORS;
      } else {
        $status = UploadOperation::STATUS_FAILED;
      }
      $completedAt = $operation->completed_at ?: now();
    } elseif ($hasUploading || ($uploadedBytes > 0 && $uploadedFiles < ($totalFiles - $failedFiles))) {
      $status = UploadOperation::STATUS_UPLOADING;
    } elseif ($hasProcessing || $uploadedFiles > 0) {
      $status = UploadOperation::STATUS_PROCESSING;
    } else {
      $status = UploadOperation::STATUS_QUEUED;
    }

    $errorSummary = null;
    if ($failedFiles > 0) {
      $errorSummary = $failedFiles === 1
        ? ($firstErrorMessage ?: '1 archivo falló al procesarse.')
        : "{$failedFiles} archivos fallaron" . ($firstErrorMessage ? ": {$firstErrorMessage}" : '.');
    }

    $operation->update([
      'status' => $status,
      'total_files' => $totalFiles,
      'uploaded_files' => $uploadedFiles,
      'completed_files' => $completedFiles,
      'failed_files' => $failedFiles,
      'total_bytes' => $totalBytes,
      'uploaded_bytes' => min($totalBytes, $uploadedBytes),
      'error_summary' => $errorSummary,
      'completed_at' => $completedAt,
    ]);

    return $operation->fresh(['event', 'memory', 'files.mediaItem']);
  }

  public function updateFileMetadata(
    string $operationKey,
    string $fileKey,
    array $meta,
    Event $event,
    ?string $deviceId,
    ?string $actorKey,
    bool $isHost = false,
  ): UploadOperation {
    $operation = $this->findOperationForActor($operationKey, $event, $deviceId, $actorKey, $isHost);
    $file = $operation->files()->where('file_key', trim($fileKey))->first();

    if (!$file) {
      throw new DomainRuleException(
        'UPLOAD_FILE_NOT_FOUND',
        'No se encontró el archivo para actualizar sus metadatos.',
        404
      );
    }

    if ($file->status === UploadOperationFile::STATUS_COMPLETED) {
      return $operation;
    }

    return DB::transaction(function () use ($operation, $file, $meta): UploadOperation {
      $lockedOp = UploadOperation::where('id', $operation->id)->lockForUpdate()->firstOrFail();
      $lockedFile = UploadOperationFile::where('id', $file->id)->lockForUpdate()->firstOrFail();

      $byteSize = max(1, (int) ($meta['byteSize'] ?? $lockedFile->byte_size));
      $chunkSize = min(self::MAX_CHUNK_SIZE, max(1024, (int) ($meta['chunkSize'] ?? $lockedFile->chunk_size)));
      $totalChunks = max(1, (int) ($meta['totalChunks'] ?? ceil($byteSize / $chunkSize)));
      $originalName = trim((string) ($meta['originalName'] ?? $lockedFile->original_name)) ?: $lockedFile->original_name;
      $mimeType = trim((string) ($meta['mimeType'] ?? $lockedFile->mime_type)) ?: $lockedFile->mime_type;

      $lockedFile->update([
        'original_name' => $originalName,
        'mime_type' => $mimeType,
        'byte_size' => $byteSize,
        'chunk_size' => $chunkSize,
        'total_chunks' => $totalChunks,
        'received_chunks' => [],
        'uploaded_bytes' => 0,
      ]);

      $this->clearRedisChunkKeys($lockedFile->id);
      $freshOp = $this->recalculateOperation($lockedOp);
      $changedFile = $freshOp->files->firstWhere('id', $lockedFile->id);

      DB::afterCommit(function () use ($freshOp, $changedFile): void {
        $this->broadcastOperation($freshOp, true, $changedFile);
      });

      return $freshOp;
    });
  }

  public function broadcastOperation(
    UploadOperation $operation,
    bool $toOthers = false,
    ?UploadOperationFile $changedFile = null,
  ): void {
    $operation->loadMissing(['event', 'memory', 'files.mediaItem']);
    $eventCode = $operation->event?->code ?? (string) config('xv.default_event_code', 'XV-NATALIA');
    $payload = $this->buildCompactBroadcastPayload($operation, $changedFile);

    $eventInstance = new UploadOperationUpdated(
      $eventCode,
      $payload,
      (string) ($operation->owner_device_id ?? ''),
      (string) ($operation->owner_actor_key ?? ''),
    );

    try {
      if ($toOthers) {
        broadcast($eventInstance)->toOthers();
      } else {
        broadcast($eventInstance);
      }
    } catch (\Throwable $e) {
      Log::warning('[UploadOperation] Non-fatal WebSocket broadcast error', [
        'operation_key' => $operation->operation_key,
        'error' => $e->getMessage(),
      ]);
    }
  }

  public function clearRedisChunkKeys(int $fileId): void
  {
    if ($fileId <= 0) {
      return;
    }
    try {
      Redis::del($this->redisChunkSetKey($fileId), $this->redisDispatchLockKey($fileId));
    } catch (\Throwable) {
    }
  }

  public function invalidateMemoryCaches(Memory $memory): void
  {
    $this->forgetItem(EventKeys::memory($memory->external_id));
    $this->scanAndDelete(EventKeys::feedPattern($memory->event_id));
    $this->scanAndDelete(EventKeys::profilePattern($memory->event_id));
  }

  public function broadcastMemoryChange(Memory $memory, ?string $requestOrigin, bool $isCreated): void
  {
    $fresh = Memory::with([
      'deviceLock:id,guest_name,avatar_path,cover_path,actor_key,device_id,updated_at',
      'event',
      'mediaItems',
      'reactions',
      'comments.deviceLock:id,guest_name,avatar_path,cover_path,actor_key,device_id,updated_at',
      'comments.commentLikes',
    ])->find($memory->id);

    if (!$fresh) {
      return;
    }

    $eventCode = $fresh->event?->code ?? (string) config('xv.default_event_code', 'XV-NATALIA');
    $payload = (new MemoryResource($fresh))->resolve();
    $normalized = $this->normalizeUrlsForOrigin($payload, $requestOrigin);

    try {
      if ($isCreated) {
        broadcast(new MemoryCreated($eventCode, $normalized));
      } else {
        broadcast(new MemoryUpdated($eventCode, $normalized));
      }
    } catch (\Throwable $e) {
      Log::warning('[UploadOperation] Non-fatal Memory broadcast error', [
        'memory_id' => $fresh->external_id,
        'error' => $e->getMessage(),
      ]);
    }
  }

  private function buildCompactBroadcastPayload(UploadOperation $operation, ?UploadOperationFile $changedFile = null): array
  {
    $origin = $operation->request_origin;
    $payload = [
      'id' => $operation->operation_key,
      'operationKey' => $operation->operation_key,
      'eventCode' => $operation->event?->code ?? '',
      'memoryExternalId' => $operation->memory_external_id,
      'memoryId' => $operation->memory ? $operation->memory->external_id : $operation->memory_external_id,
      'ownerDeviceId' => $operation->owner_device_id ?? '',
      'ownerActorKey' => $operation->owner_actor_key ?? '',
      'guestName' => $operation->guest_name,
      'tableCode' => $operation->table_code,
      'caption' => Str::limit((string) ($operation->caption ?? ''), 120),
      'status' => $operation->status,
      'totalFiles' => (int) $operation->total_files,
      'uploadedFiles' => (int) $operation->uploaded_files,
      'completedFiles' => (int) $operation->completed_files,
      'failedFiles' => (int) $operation->failed_files,
      'totalBytes' => (int) $operation->total_bytes,
      'uploadedBytes' => (int) $operation->uploaded_bytes,
      'progressPercent' => $operation->progressPercent(),
      'errorSummary' => $operation->error_summary ? Str::limit((string) $operation->error_summary, 280) : null,
      'completedAt' => optional($operation->completed_at)->toIso8601String(),
      'updatedAt' => optional($operation->updated_at)->toIso8601String(),
    ];

    if ($changedFile !== null) {
      $payload['updatedFile'] = $this->compactFileDelta($changedFile, $origin);
      return $payload;
    }

    if ($operation->files->count() <= 4) {
      $payload['files'] = $operation->files
        ->map(fn ($file) => $this->compactFileDelta($file, $origin))
        ->values()
        ->all();
    }

    return $payload;
  }

  private function compactFileDelta(UploadOperationFile $file, ?string $origin): array
  {
    $full = (new UploadOperationFileResource($file, $origin))->resolve();
    return [
      'id' => $full['id'],
      'fileKey' => $full['fileKey'],
      'originalName' => Str::limit((string) $full['originalName'], 90),
      'mediaType' => $full['mediaType'],
      'byteSize' => $full['byteSize'],
      'uploadedBytes' => $full['uploadedBytes'],
      'totalChunks' => $full['totalChunks'],
      'sortOrder' => $full['sortOrder'],
      'status' => $full['status'],
      'progressPercent' => $full['progressPercent'],
      'posterUrl' => $full['posterUrl'],
      'finalUrl' => $full['finalUrl'],
      'errorCode' => $full['errorCode'],
      'errorMessage' => $full['errorMessage'] ? Str::limit((string) $full['errorMessage'], 240) : null,
    ];
  }

  private function writeStreamAtOffset(string $targetDisk, string $stagingPath, int $offset, $inputStream): int
  {
    $disk = Storage::disk($targetDisk);
    $driver = $disk->getConfig()['driver'] ?? 'local';
    if ($driver !== 'local') {
      $buffer = stream_get_contents($inputStream);
      if ($buffer === false || strlen($buffer) === 0) {
        return 0;
      }
      $existing = $disk->exists($stagingPath) ? ($disk->get($stagingPath) ?: '') : '';
      $prefix = str_pad(substr($existing, 0, $offset), $offset, "\0");
      $suffix = strlen($existing) > $offset + strlen($buffer) ? substr($existing, $offset + strlen($buffer)) : '';
      $disk->put($stagingPath, $prefix . $buffer . $suffix);
      return strlen($buffer);
    }

    $tempBuf = fopen('php://temp/maxmemory:4194304', 'w+b');
    if ($tempBuf === false) {
      throw new DomainRuleException('TEMP_STREAM_ERROR', 'No se pudo inicializar el búfer temporal.', 500);
    }

    try {
      $copied = (int) stream_copy_to_stream($inputStream, $tempBuf);
      if ($copied <= 0) {
        return 0;
      }
      rewind($tempBuf);

      $absPath = $disk->path($stagingPath);
      $parentDir = dirname($absPath);
      if (!is_dir($parentDir)) {
        @mkdir($parentDir, 0775, true);
      }

      $targetHandle = fopen($absPath, 'c+b');
      if ($targetHandle === false) {
        throw new DomainRuleException('STAGING_OPEN_ERROR', 'No se pudo abrir el archivo de almacenamiento directo.', 500);
      }

      try {
        flock($targetHandle, LOCK_EX);
        fseek($targetHandle, $offset, SEEK_SET);
        stream_copy_to_stream($tempBuf, $targetHandle);
        fflush($targetHandle);
        flock($targetHandle, LOCK_UN);
      } finally {
        fclose($targetHandle);
      }

      return $copied;
    } finally {
      fclose($tempBuf);
    }
  }

  private function redisChunkSetKey(int $fileId): string
  {
    return "xv:op_file:{$fileId}:chunks";
  }

  private function redisDispatchLockKey(int $fileId): string
  {
    return "xv:op_file:{$fileId}:dispatched";
  }

  private function trackRedisChunk(int $fileId, int $chunkIndex): array
  {
    try {
      $key = $this->redisChunkSetKey($fileId);
      Redis::sAdd($key, (string) $chunkIndex);
      Redis::expire($key, 86400);
      $raw = Redis::sMembers($key);
      $indices = array_values(array_unique(array_map('intval', is_array($raw) ? $raw : [])));
      sort($indices);
      return [
        'available' => true,
        'count' => count($indices),
        'indices' => $indices,
      ];
    } catch (\Throwable) {
      return [
        'available' => false,
        'count' => 0,
        'indices' => [],
      ];
    }
  }

  private function readRedisChunks(int $fileId): array
  {
    try {
      $raw = Redis::sMembers($this->redisChunkSetKey($fileId));
      $indices = array_values(array_unique(array_map('intval', is_array($raw) ? $raw : [])));
      sort($indices);
      return $indices;
    } catch (\Throwable) {
      return [];
    }
  }

  private function acquireJobDispatchLock(int $fileId): bool
  {
    try {
      $result = Redis::set($this->redisDispatchLockKey($fileId), '1', 'EX', 86400, 'NX');
      return (bool) $result;
    } catch (\Throwable) {
      return true;
    }
  }

  private function mergeIndices(array $a, array $b): array
  {
    $merged = array_values(array_unique(array_map('intval', array_merge($a, $b))));
    sort($merged);
    return $merged;
  }

  private function computeUploadedBytes(int $byteSize, int $chunkSize, int $totalChunks, array $receivedIndices): int
  {
    if ($byteSize <= 0) {
      return 0;
    }

    $lastIndex = max(0, $totalChunks - 1);
    $lastChunkBytes = max(0, $byteSize - ($lastIndex * $chunkSize));
    $total = 0;

    foreach ($receivedIndices as $index) {
      if ($index < 0 || $index >= $totalChunks) {
        continue;
      }
      $total += ($index === $lastIndex) ? $lastChunkBytes : $chunkSize;
    }

    return min($byteSize, $total);
  }

  private function normalizeUrlsForOrigin(mixed $data, ?string $requestOrigin): mixed
  {
    $cleanOrigin = rtrim((string) ($requestOrigin ?: ''), '/');
    if ($cleanOrigin === '' || app()->bound('request')) {
      return $data;
    }

    $appBase = rtrim((string) config('app.url'), '/');
    if ($appBase === '' || $appBase === $cleanOrigin) {
      return $data;
    }

    if (is_string($data)) {
      if (str_starts_with($data, $appBase . '/storage/') || str_starts_with($data, $appBase . '/api/')) {
        return $cleanOrigin . substr($data, strlen($appBase));
      }
      return $data;
    }

    if (is_array($data)) {
      foreach ($data as $k => $v) {
        $data[$k] = $this->normalizeUrlsForOrigin($v, $cleanOrigin);
      }
    }

    return $data;
  }
}
