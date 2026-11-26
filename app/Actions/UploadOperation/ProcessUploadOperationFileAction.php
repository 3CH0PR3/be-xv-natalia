<?php

namespace App\Actions\UploadOperation;

use App\Models\Event;
use App\Models\Memory;
use App\Models\MemoryMediaItem;
use App\Models\UploadOperation;
use App\Models\UploadOperationFile;
use App\Support\Media\FfmpegVideoProcessor;
use App\Support\Storage\UploadOperationHelper;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

final readonly class ProcessUploadOperationFileAction
{
  public function __construct(
    private UploadOperationHelper $helper,
    private MarkOperationFileFailedAction $markFailedAction,
    private FfmpegVideoProcessor $ffmpeg,
  ) {}

  public function execute(int $uploadOperationFileId): void
  {
    $file = UploadOperationFile::with(['operation.event', 'operation.deviceLock'])->find($uploadOperationFileId);
    if (!$file || !$file->operation) {
      return;
    }

    if ($file->status === UploadOperationFile::STATUS_COMPLETED && $file->memory_media_item_id) {
      return;
    }

    $operation = $file->operation;
    $event = $operation->event;
    if (!$event) {
      $this->markFailedAction->executeById($file->id, 'EVENT_MISSING', 'Evento asociado no encontrado.');
      return;
    }

    $alreadyCompleted = DB::transaction(function () use ($operation, $file): bool {
      $lockedOp = UploadOperation::where('id', $operation->id)->lockForUpdate()->firstOrFail();
      $lockedFile = UploadOperationFile::where('id', $file->id)->lockForUpdate()->firstOrFail();

      if ($lockedFile->status === UploadOperationFile::STATUS_COMPLETED && $lockedFile->memory_media_item_id) {
        return true;
      }

      $lockedFile->update(['status' => UploadOperationFile::STATUS_PROCESSING]);
      $refreshedBefore = $this->helper->recalculateOperation($lockedOp);
      $changedFile = $refreshedBefore->files->firstWhere('id', $lockedFile->id);

      DB::afterCommit(function () use ($refreshedBefore, $changedFile): void {
        $this->helper->broadcastOperation($refreshedBefore, false, $changedFile);
      });

      return false;
    });

    if ($alreadyCompleted) {
      return;
    }

    $file->refresh();
    $operation->refresh();

    $targetDiskName = $file->disk ?: (string) config('xv.media_disk', 'public');
    $targetDisk = Storage::disk($targetDiskName);
    $stagingPath = $this->helper->stagingPathFor($event, $operation, $file);
    $chunkDir = $this->helper->chunkDirFor($event, $operation, $file);

    $receivedIndices = $file->receivedChunksList();
    if (count($receivedIndices) < $file->total_chunks) {
      throw new \RuntimeException(
        "Faltan fragmentos por recibir para \"{$file->original_name}\" (" . count($receivedIndices) . "/{$file->total_chunks})."
      );
    }

    if ($targetDisk->directoryExists($chunkDir) || $targetDisk->exists($this->helper->chunkPartPathFor($event, $operation, $file, 0))) {
      $assembledSize = $this->assembleFromChunkParts($file, $event, $operation, $targetDisk, $stagingPath);
    } elseif ($targetDisk->exists($stagingPath)) {
      $assembledSize = (int) $targetDisk->size($stagingPath);
    } else {
      $assembledSize = $this->assembleFromLegacyParts($file, $targetDisk, $stagingPath);
    }

    if ($assembledSize <= 0 || ($file->byte_size > 0 && $assembledSize < $file->byte_size)) {
      throw new \RuntimeException(
        "El archivo ensamblado \"{$file->original_name}\" quedó incompleto ({$assembledSize}/{$file->byte_size} bytes)."
      );
    }

    $overrideExt = null;
    $resolvedMime = $file->mime_type;
    $generatedPosterPath = null;

    if ($file->media_type === 'image') {
      $signature = $this->helper->detectImageSignature($targetDiskName, $stagingPath);
      if ($signature !== null) {
        if (!empty($signature['forbidden'])) {
          $targetDisk->delete($stagingPath);
          $this->helper->deleteChunkDirectory($targetDiskName, $event, $operation, $file);
          $this->markFailedAction->executeById($file->id, 'INVALID_MEDIA_TYPE', 'El formato GIF no está permitido.');

          return;
        }
        if (!$signature['webRenderable']) {
          $targetDisk->delete($stagingPath);
          $this->helper->deleteChunkDirectory($targetDiskName, $event, $operation, $file);
          $this->markFailedAction->executeById(
            $file->id,
            'UNSUPPORTED_IMAGE_ENCODING',
            "La imagen \"{$file->original_name}\" está en formato HEIC/RAW sin convertir y no es compatible con la web."
          );

          return;
        }
        $overrideExt = $signature['ext'];
        $resolvedMime = $signature['mime'];
      }
    } elseif ($file->media_type === 'video') {
      $videoOutcome = $this->processVideoWithFfmpeg($targetDiskName, $targetDisk, $stagingPath, $event, $operation, $file);
      $overrideExt = $videoOutcome['overrideExt'];
      if ($videoOutcome['resolvedMime'] !== null) {
        $resolvedMime = $videoOutcome['resolvedMime'];
      }
      if ($videoOutcome['byteSize'] !== null) {
        $assembledSize = $videoOutcome['byteSize'];
      }
      $generatedPosterPath = $videoOutcome['posterPath'];
    }

    $finalPath = $this->helper->finalPathFor($event, $operation, $file, $overrideExt);
    if ($targetDisk->exists($finalPath)) {
      $targetDisk->delete($finalPath);
    }
    $targetDisk->move($stagingPath, $finalPath);

    $thumbPath = null;
    if ($file->media_type === 'image') {
      $thumbPath = $this->helper->createThumbnailFromStoredImage($targetDiskName, $finalPath, $event, $operation, $file)
        ?: $finalPath;
    }

    DB::transaction(function () use (
      $operation,
      $file,
      $event,
      $targetDiskName,
      $finalPath,
      $thumbPath,
      $generatedPosterPath,
      $resolvedMime,
      $assembledSize
    ): void {
      $lockedOp = UploadOperation::where('id', $operation->id)->lockForUpdate()->firstOrFail();
      $lockedFile = UploadOperationFile::where('id', $file->id)->lockForUpdate()->firstOrFail();

      if ($lockedFile->status === UploadOperationFile::STATUS_COMPLETED && $lockedFile->memory_media_item_id) {
        return;
      }

      $isNewMemory = false;
      $memory = $lockedOp->memory_id ? Memory::find($lockedOp->memory_id) : null;
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

      $resolvedPosterPath = $lockedFile->poster_path ?: $generatedPosterPath;

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
          'thumb_path' => $lockedFile->media_type === 'image' ? $thumbPath : null,
          'poster_path' => $resolvedPosterPath,
          'mime_type' => $resolvedMime,
          'byte_size' => $assembledSize,
          'sort_order' => $lockedFile->sort_order,
        ]
      );

      $lockedFile->update([
        'memory_media_item_id' => $mediaItem->id,
        'mime_type' => $resolvedMime,
        'byte_size' => $assembledSize,
        'poster_path' => $resolvedPosterPath,
        'status' => UploadOperationFile::STATUS_COMPLETED,
        'final_path' => $finalPath,
        'uploaded_bytes' => $assembledSize,
        'processed_at' => now(),
        'error_code' => null,
        'error_message' => null,
      ]);

      $memory->touch();
      $freshOp = $this->helper->recalculateOperation($lockedOp);
      $completedFile = $freshOp->files->firstWhere('id', $lockedFile->id);

      DB::afterCommit(function () use ($freshOp, $completedFile, $memory, $event, $lockedOp, $lockedFile, $targetDiskName, $isNewMemory): void {
        $this->helper->deleteChunkDirectory($targetDiskName, $event, $lockedOp, $lockedFile);
        if ($lockedFile->temp_dir) {
          Storage::disk(UploadOperationHelper::CHUNK_DISK)->deleteDirectory($lockedFile->temp_dir);
        }
        $this->helper->clearRedisChunkKeys($lockedFile->id);
        $this->helper->invalidateMemoryCaches($memory);
        $this->helper->broadcastOperation($freshOp, false, $completedFile);
        $this->helper->broadcastMemoryChange($memory, $freshOp->request_origin, $isNewMemory);
      });
    });
  }

  public function cleanupFailedStaging(int $uploadOperationFileId): void
  {
    $file = UploadOperationFile::with('operation.event')->find($uploadOperationFileId);
    if (!$file || !$file->operation || !$file->operation->event) {
      return;
    }
    $targetDiskName = $file->disk ?: (string) config('xv.media_disk', 'public');
    $stagingPath = $this->helper->stagingPathFor($file->operation->event, $file->operation, $file);
    $disk = Storage::disk($targetDiskName);
    if ($disk->exists($stagingPath)) {
      $disk->delete($stagingPath);
    }
    if ($disk->exists($stagingPath . '.mp4')) {
      $disk->delete($stagingPath . '.mp4');
    }
    $this->helper->deleteChunkDirectory($targetDiskName, $file->operation->event, $file->operation, $file);
    if ($file->temp_dir) {
      Storage::disk(UploadOperationHelper::CHUNK_DISK)->deleteDirectory($file->temp_dir);
    }
  }

  private function processVideoWithFfmpeg(
    string $targetDiskName,
    $targetDisk,
    string $stagingPath,
    Event $event,
    UploadOperation $operation,
    UploadOperationFile $file,
  ): array {
    $result = [
      'overrideExt' => null,
      'resolvedMime' => null,
      'byteSize' => null,
      'posterPath' => null,
    ];

    $driver = $targetDisk->getConfig()['driver'] ?? 'local';
    if ($driver !== 'local' || !$this->ffmpeg->isAvailable()) {
      return $result;
    }

    $stagingAbs = $targetDisk->path($stagingPath);
    $probe = null;

    if ($this->ffmpeg->requiresTranscode($file->original_name, $file->mime_type)) {
      $probe = $this->ffmpeg->probeMetadata($stagingAbs);
      if ($probe['hasVideo'] || $probe['hasAudio']) {
        $transcodedRel = $stagingPath . '.mp4';
        $transcodedAbs = $targetDisk->path($transcodedRel);
        if ($this->ffmpeg->transcodeToMp4($stagingAbs, $transcodedAbs)) {
          $targetDisk->delete($stagingPath);
          $targetDisk->move($transcodedRel, $stagingPath);
          $result['overrideExt'] = 'mp4';
          $result['resolvedMime'] = 'video/mp4';
          $result['byteSize'] = (int) $targetDisk->size($stagingPath);
          $stagingAbs = $targetDisk->path($stagingPath);
          $probe = $this->ffmpeg->probeMetadata($stagingAbs);
        }
      }
    }

    $currentPosterPath = UploadOperationFile::where('id', $file->id)->value('poster_path');
    if (!$currentPosterPath) {
      $probe = $probe ?? $this->ffmpeg->probeMetadata($stagingAbs);
      if ($probe['hasVideo']) {
        $posterRel = "memories/{$event->code}/{$operation->memory_external_id}/posters/{$file->file_key}.jpg";
        $posterAbs = $targetDisk->path($posterRel);
        if ($this->ffmpeg->extractPosterJpg($stagingAbs, $posterAbs)) {
          $result['posterPath'] = $posterRel;
        }
      }
    }

    return $result;
  }

  private function assembleFromChunkParts(
    UploadOperationFile $file,
    Event $event,
    UploadOperation $operation,
    $targetDisk,
    string $stagingPath,
  ): int {
    $driver = $targetDisk->getConfig()['driver'] ?? 'local';
    if ($driver === 'local') {
      $absStaging = $targetDisk->path($stagingPath);
      $parentDir = dirname($absStaging);
      if (!is_dir($parentDir)) {
        @mkdir($parentDir, 0775, true);
      }

      $outHandle = fopen($absStaging, 'wb');
      if ($outHandle === false) {
        throw new \RuntimeException('No se pudo abrir archivo temporal para ensamblar fragmentos.');
      }

      try {
        for ($idx = 0; $idx < $file->total_chunks; $idx++) {
          $partPath = $this->helper->chunkPartPathFor($event, $operation, $file, $idx);
          if (!$targetDisk->exists($partPath)) {
            throw new \RuntimeException("Falta el fragmento #{$idx} para \"{$file->original_name}\".");
          }
          $inHandle = fopen($targetDisk->path($partPath), 'rb');
          if ($inHandle === false) {
            throw new \RuntimeException("No se pudo leer el fragmento #{$idx} de \"{$file->original_name}\".");
          }
          try {
            stream_copy_to_stream($inHandle, $outHandle);
          } finally {
            fclose($inHandle);
          }
        }
        fflush($outHandle);
      } finally {
        fclose($outHandle);
      }

      clearstatcache(true, $absStaging);

      return (int) (filesize($absStaging) ?: 0);
    }

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
          $partPath = $this->helper->chunkPartPathFor($event, $operation, $file, $idx);
          if (!$targetDisk->exists($partPath)) {
            throw new \RuntimeException("Falta el fragmento #{$idx} para \"{$file->original_name}\".");
          }
          $inStream = $targetDisk->readStream($partPath);
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
      $readMerged = fopen($tmpMerged, 'rb');
      if ($readMerged === false) {
        throw new \RuntimeException("No se pudo leer el archivo ensamblado de \"{$file->original_name}\".");
      }
      try {
        $targetDisk->writeStream($stagingPath, $readMerged);
      } finally {
        fclose($readMerged);
      }

      return $assembledSize;
    } finally {
      if (is_file($tmpMerged)) {
        @unlink($tmpMerged);
      }
    }
  }

  private function assembleFromLegacyParts(UploadOperationFile $file, $targetDisk, string $finalPath): int
  {
    $chunkDisk = Storage::disk(UploadOperationHelper::CHUNK_DISK);
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
