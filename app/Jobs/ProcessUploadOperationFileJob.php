<?php

namespace App\Jobs;

use App\Models\Memory;
use App\Models\MemoryMediaItem;
use App\Models\UploadOperation;
use App\Models\UploadOperationFile;
use App\Services\UploadOperationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ProcessUploadOperationFileJob implements ShouldQueue
{
  use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

  public int $tries = 2;
  public int $timeout = 300;

  public function __construct(
    public int $uploadOperationFileId
  ) {
    $this->queue = 'default';
  }

  public function handle(UploadOperationService $service): void
  {
    $file = UploadOperationFile::with(['operation.event', 'operation.deviceLock'])->find($this->uploadOperationFileId);
    if (!$file || !$file->operation) {
      return;
    }

    if ($file->status === UploadOperationFile::STATUS_COMPLETED && $file->memory_media_item_id) {
      return;
    }

    $operation = $file->operation;
    $event = $operation->event;
    if (!$event) {
      $service->markFileFailedById($file->id, 'EVENT_MISSING', 'Evento asociado no encontrado.');
      return;
    }

    $file->update(['status' => UploadOperationFile::STATUS_PROCESSING]);
    $refreshedBefore = $service->recalculateOperation($operation);
    $changedFile = $refreshedBefore->files->firstWhere('id', $file->id);
    $service->broadcastOperation($refreshedBefore, false, $changedFile);

    $targetDiskName = $file->disk ?: (string) config('xv.media_disk', 'public');
    $targetDisk = Storage::disk($targetDiskName);
    $stagingPath = $service->stagingPathFor($event, $operation, $file);
    $finalPath = $service->finalPathFor($event, $operation, $file);

    $receivedIndices = $file->receivedChunksList();
    if (count($receivedIndices) < $file->total_chunks) {
      throw new \RuntimeException("Faltan fragmentos por recibir para \"{$file->original_name}\" (" . count($receivedIndices) . "/{$file->total_chunks}).");
    }

    if ($targetDisk->exists($stagingPath)) {
      $stagingSize = (int) $targetDisk->size($stagingPath);
      if ($stagingSize <= 0 || ($file->byte_size > 0 && $stagingSize < $file->byte_size)) {
        throw new \RuntimeException("El archivo ensamblado \"{$file->original_name}\" quedó incompleto ({$stagingSize}/{$file->byte_size} bytes).");
      }

      if ($targetDisk->exists($finalPath)) {
        $targetDisk->delete($finalPath);
      }
      $targetDisk->move($stagingPath, $finalPath);
      $assembledSize = $stagingSize;
    } else {
      $assembledSize = $this->assembleFromLegacyParts($file, $targetDisk, $finalPath);
    }

    DB::transaction(function () use (
      $service,
      $operation,
      $file,
      $event,
      $targetDiskName,
      $finalPath,
      $assembledSize
    ): void {
      $lockedOp = UploadOperation::where('id', $operation->id)->lockForUpdate()->firstOrFail();
      $lockedFile = UploadOperationFile::where('id', $file->id)->lockForUpdate()->firstOrFail();

      if ($lockedFile->status === UploadOperationFile::STATUS_COMPLETED && $lockedFile->memory_media_item_id) {
        return;
      }

      $isNewMemory = false;
      $memory = null;

      if ($lockedOp->memory_id) {
        $memory = Memory::find($lockedOp->memory_id);
      }

      if (!$memory) {
        $memory = Memory::where('external_id', $lockedOp->memory_external_id)->first();
      }

      if (!$memory) {
        $memory = Memory::create([
          'external_id' => $lockedOp->memory_external_id,
          'event_id' => $event->id,
          'event_table_id' => $lockedOp->event_table_id,
          'device_lock_id' => $lockedOp->device_lock_id,
          'guest_name' => $lockedOp->guest_name,
          'table_code' => $lockedOp->table_code,
          'owner_device_id' => $lockedOp->owner_device_id,
          'owner_actor_key' => $lockedOp->owner_actor_key,
          'moment_tag' => $lockedOp->moment_tag ?: 'Fiesta en Vivo',
          'caption' => $lockedOp->caption ?? '',
          'media_type' => $lockedFile->media_type,
          'status' => Memory::STATUS_APPROVED,
          'timestamp_ms' => (int) round(microtime(true) * 1000),
        ]);
        $isNewMemory = true;
      }

      if ((int) $lockedOp->memory_id !== (int) $memory->id) {
        $lockedOp->update(['memory_id' => $memory->id]);
      }

      $mediaItem = MemoryMediaItem::firstOrCreate(
        [
          'memory_id' => $memory->id,
          'external_id' => $lockedFile->file_key,
        ],
        [
          'media_blob_key' => '',
          'media_type' => $lockedFile->media_type,
          'disk' => $targetDiskName,
          'file_path' => $finalPath,
          'thumb_path' => $lockedFile->media_type === 'image' ? $finalPath : null,
          'poster_path' => $lockedFile->poster_path,
          'mime_type' => $lockedFile->mime_type,
          'byte_size' => $assembledSize,
          'sort_order' => $lockedFile->sort_order,
        ]
      );

      $lockedFile->update([
        'memory_media_item_id' => $mediaItem->id,
        'status' => UploadOperationFile::STATUS_COMPLETED,
        'final_path' => $finalPath,
        'uploaded_bytes' => $lockedFile->byte_size,
        'processed_at' => now(),
        'error_code' => null,
        'error_message' => null,
      ]);

      $memory->touch();
      $freshOp = $service->recalculateOperation($lockedOp);
      $completedFile = $freshOp->files->firstWhere('id', $lockedFile->id);

      DB::afterCommit(function () use ($service, $freshOp, $completedFile, $memory, $lockedFile, $isNewMemory): void {
        if ($lockedFile->temp_dir) {
          Storage::disk(UploadOperationService::CHUNK_DISK)->deleteDirectory($lockedFile->temp_dir);
        }
        $service->clearRedisChunkKeys($lockedFile->id);
        $service->invalidateMemoryCaches($memory);
        $service->broadcastOperation($freshOp, false, $completedFile);
        $service->broadcastMemoryChange($memory, $freshOp->request_origin, $isNewMemory);
      });
    });
  }

  public function failed(\Throwable $exception): void
  {
    Log::error('[UploadOperation] ProcessUploadOperationFileJob failed', [
      'upload_operation_file_id' => $this->uploadOperationFileId,
      'message' => $exception->getMessage(),
    ]);

    try {
      app(UploadOperationService::class)->markFileFailedById(
        $this->uploadOperationFileId,
        'PROCESSING_FAILED',
        $exception->getMessage() ?: 'Error procesando el archivo en segundo plano.'
      );
    } catch (\Throwable) {
    }
  }

  private function assembleFromLegacyParts(UploadOperationFile $file, $targetDisk, string $finalPath): int
  {
    $chunkDisk = Storage::disk(UploadOperationService::CHUNK_DISK);
    $tmpMerged = tempnam(sys_get_temp_dir(), 'xv_op_');
    if ($tmpMerged === false) {
      throw new \RuntimeException('No se pudo asignar archivo temporal para ensamblar la multimedia.');
    }

    try {
      $outHandle = fopen($tmpMerged, 'wb');
      if ($outHandle === false) {
        throw new \RuntimeException('No se pudo abrir flujo temporal de escritura.');
      }

      try {
        for ($idx = 0; $idx < $file->total_chunks; $idx++) {
          $chunkPath = "{$file->temp_dir}/chunks/{$idx}.part";
          if (!$chunkDisk->exists($chunkPath)) {
            throw new \RuntimeException("Falta el fragmento #{$idx} para \"{$file->original_name}\".");
          }
          $inStream = $chunkDisk->readStream($chunkPath);
          if ($inStream === false) {
            throw new \RuntimeException("No se pudo leer el fragmento #{$idx} de \"{$file->original_name}\".");
          }
          try {
            stream_copy_to_stream($inStream, $outHandle);
          } finally {
            if (is_resource($inStream)) {
              fclose($inStream);
            }
          }
        }
      } finally {
        fclose($outHandle);
      }

      clearstatcache(true, $tmpMerged);
      $assembledSize = (int) (filesize($tmpMerged) ?: 0);
      if ($assembledSize <= 0) {
        throw new \RuntimeException("El archivo ensamblado \"{$file->original_name}\" quedó vacío (0 bytes).");
      }

      $readMerged = fopen($tmpMerged, 'rb');
      if ($readMerged === false) {
        throw new \RuntimeException("No se pudo leer el archivo ensamblado de \"{$file->original_name}\".");
      }

      try {
        $targetDisk->writeStream($finalPath, $readMerged);
      } finally {
        if (is_resource($readMerged)) {
          fclose($readMerged);
        }
      }

      return $assembledSize;
    } finally {
      if (is_file($tmpMerged)) {
        @unlink($tmpMerged);
      }
    }
  }
}
