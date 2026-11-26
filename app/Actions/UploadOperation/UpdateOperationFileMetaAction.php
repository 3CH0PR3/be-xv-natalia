<?php

namespace App\Actions\UploadOperation;

use App\DTOs\UploadOperation\UpdateOperationFileMetaData;
use App\Exceptions\DomainRuleException;
use App\Models\UploadOperation;
use App\Models\UploadOperationFile;
use App\Support\Storage\UploadOperationHelper;
use App\Support\Validation\MediaFilePolicy;
use Illuminate\Support\Facades\DB;

final readonly class UpdateOperationFileMetaAction
{
  public function __construct(
    private UploadOperationHelper $helper,
  ) {}

  public function execute(UpdateOperationFileMetaData $data): UploadOperation
  {
    $event = $this->helper->resolveEvent($data->eventCode);
    $operation = $this->helper->findOperationForActor($data->operationKey, $event, $data->deviceId, $data->actorKey, $data->hostPin);
    $file = $operation->files()->where('file_key', trim($data->fileKey))->first();

    if (!$file) {
      throw new DomainRuleException('UPLOAD_FILE_NOT_FOUND', 'No se encontró el archivo para actualizar sus metadatos.', 404);
    }

    if ($file->status === UploadOperationFile::STATUS_COMPLETED) {
      return $operation;
    }

    $meta = $data->meta;

    return DB::transaction(function () use ($event, $operation, $file, $meta): UploadOperation {
      $lockedOp = UploadOperation::where('id', $operation->id)->lockForUpdate()->firstOrFail();
      $lockedFile = UploadOperationFile::where('id', $file->id)->lockForUpdate()->firstOrFail();

      $byteSize = max(1, (int) ($meta['byteSize'] ?? $lockedFile->byte_size));
      $chunkSize = min(UploadOperationHelper::MAX_CHUNK_SIZE, max(1024, (int) ($meta['chunkSize'] ?? $lockedFile->chunk_size)));
      $totalChunks = max(1, (int) ($meta['totalChunks'] ?? ceil($byteSize / $chunkSize)));
      $originalName = trim((string) ($meta['originalName'] ?? $lockedFile->original_name)) ?: $lockedFile->original_name;
      $mimeType = trim((string) ($meta['mimeType'] ?? $lockedFile->mime_type)) ?: $lockedFile->mime_type;

      $validationError = MediaFilePolicy::validateFilenameAndMime($originalName, $mimeType);
      if ($validationError !== null) {
        throw new DomainRuleException('INVALID_MEDIA_TYPE', $validationError, 422);
      }

      $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
      $mediaType = MediaFilePolicy::isVideoDescriptor($ext, $mimeType) ? 'video' : 'image';

      $lockedFile->update([
        'original_name' => $originalName,
        'mime_type' => $mimeType,
        'media_type' => $mediaType,
        'byte_size' => $byteSize,
        'chunk_size' => $chunkSize,
        'total_chunks' => $totalChunks,
        'received_chunks' => [],
        'uploaded_bytes' => 0,
      ]);

      $this->helper->clearRedisChunkKeys($lockedFile->id);
      $targetDisk = $lockedFile->disk ?: (string) config('xv.media_disk', 'public');
      $this->helper->deleteChunkDirectory($targetDisk, $event, $lockedOp, $lockedFile);
      $freshOp = $this->helper->recalculateOperation($lockedOp);
      $changedFile = $freshOp->files->firstWhere('id', $lockedFile->id);

      DB::afterCommit(function () use ($freshOp, $changedFile): void {
        $this->helper->broadcastOperation($freshOp, true, $changedFile);
      });

      return $freshOp;
    });
  }
}
