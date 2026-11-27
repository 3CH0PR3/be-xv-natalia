<?php

namespace App\Actions\UploadOperation;

use App\DTOs\UploadOperation\FailOperationFileData;
use App\Exceptions\DomainRuleException;
use App\Models\UploadOperation;
use App\Models\UploadOperationFile;
use App\Support\Storage\UploadOperationHelper;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

final readonly class MarkOperationFileFailedAction
{
  public function __construct(
    private UploadOperationHelper $helper,
  ) {}

  public function execute(FailOperationFileData $data): UploadOperation
  {
    $event = $this->helper->resolveEvent($data->eventCode);
    $operation = $this->helper->findOperationForActor($data->operationKey, $event, $data->deviceId, $data->actorKey, $data->hostPin);
    $file = $operation->files()->where('file_key', trim($data->fileKey))->first();

    if (!$file) {
      throw new DomainRuleException('UPLOAD_FILE_NOT_FOUND', 'No se encontró el archivo para registrar el fallo.', 404);
    }

    return $this->executeById($file->id, $data->errorCode, $data->errorMessage);
  }

  public function executeById(int|string $fileId, string $errorCode, string $errorMessage): UploadOperation
  {
    return DB::transaction(function () use ($fileId, $errorCode, $errorMessage): UploadOperation {
      $targetFile = UploadOperationFile::findOrFail($fileId);
      $lockedOp = UploadOperation::where('id', $targetFile->upload_operation_id)->lockForUpdate()->firstOrFail();
      $lockedFile = UploadOperationFile::where('id', $fileId)->lockForUpdate()->firstOrFail();

      if ($lockedFile->status !== UploadOperationFile::STATUS_COMPLETED) {
        $lockedFile->update([
          'status' => UploadOperationFile::STATUS_FAILED,
          'error_code' => Str::limit(trim($errorCode) ?: 'UPLOAD_FAILED', 64, ''),
          'error_message' => Str::limit(trim($errorMessage) ?: 'Error desconocido durante la subida.', 1000, ''),
          'processed_at' => now(),
        ]);
      }

      $freshOp = $this->helper->recalculateOperation($lockedOp);
      $changedFile = $freshOp->files->firstWhere('id', $lockedFile->id);

      Log::error('[UploadOperation] File failed', [
        'operation_key' => $freshOp->operation_key,
        'file_key' => $lockedFile->file_key,
        'original_name' => $lockedFile->original_name,
        'error_code' => $errorCode,
        'error_message' => $errorMessage,
      ]);

      DB::afterCommit(function () use ($freshOp, $changedFile): void {
        $this->helper->clearRedisChunkKeys($changedFile?->id ?? 0);
        $this->helper->broadcastOperation($freshOp, false, $changedFile);
      });

      return $freshOp;
    });
  }
}
