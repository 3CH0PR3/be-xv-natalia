<?php

namespace App\Actions\UploadOperation;

use App\DTOs\UploadOperation\StoreOperationChunkData;
use App\Exceptions\DomainRuleException;
use App\Jobs\ProcessUploadOperationFileJob;
use App\Models\Event;
use App\Models\UploadOperation;
use App\Models\UploadOperationFile;
use App\Support\Storage\UploadOperationHelper;
use Illuminate\Support\Facades\DB;

final readonly class StoreOperationChunkAction
{
  public function __construct(
    private UploadOperationHelper $helper,
  ) {}

  public function executeStream(StoreOperationChunkData $data, bool $forceCheckpoint = false): array
  {
    $event = $this->helper->resolveEvent($data->eventCode);
    $stream = $this->openInputStream($data);

    try {
      return $this->processStream($data, $event, $stream, $forceCheckpoint);
    } finally {
      if (is_resource($stream)) {
        fclose($stream);
      }
    }
  }

  public function executeMultipart(StoreOperationChunkData $data): UploadOperation
  {
    $ack = $this->executeStream($data, true);
    if ($ack['operationModel'] instanceof UploadOperation) {
      return $ack['operationModel'];
    }

    $event = $this->helper->resolveEvent($data->eventCode);

    return $this->helper->findOperationForActor(
      $data->operationKey,
      $event,
      $data->deviceId,
      $data->actorKey,
      $data->hostPin,
    );
  }

  private function openInputStream(StoreOperationChunkData $data)
  {
    if ($data->chunkFile !== null) {
      $stream = fopen($data->chunkFile->getRealPath(), 'rb');
      if ($stream === false) {
        throw new DomainRuleException('CHUNK_READ_FAILED', 'No se pudo leer el fragmento recibido en el servidor.', 500);
      }

      return $stream;
    }

    $stream = fopen('php://input', 'rb');
    if ($stream === false) {
      throw new DomainRuleException('CHUNK_READ_FAILED', 'No se pudo abrir el flujo binario de entrada.', 400);
    }

    if (!app()->runningUnitTests() || $data->rawBinaryBody === null || $data->rawBinaryBody === '') {
      return $stream;
    }

    $probe = fread($stream, 1);
    fclose($stream);
    $memStream = fopen('php://temp', 'w+b');
    if ($probe === '' || $probe === false) {
      fwrite($memStream, $data->rawBinaryBody);
    } else {
      fwrite($memStream, $data->rawBinaryBody);
    }
    rewind($memStream);

    return $memStream;
  }

  private function processStream(StoreOperationChunkData $data, Event $event, $inputStream, bool $forceCheckpoint): array
  {
    $operation = $this->helper->findOperationForActor($data->operationKey, $event, $data->deviceId, $data->actorKey, $data->hostPin);
    $file = $operation->files()->where('file_key', trim($data->fileKey))->first();

    if (!$file) {
      throw new DomainRuleException('UPLOAD_FILE_NOT_FOUND', 'No se encontró el archivo dentro de esta operación de subida.', 404);
    }

    if ($data->chunkIndex < 0 || $data->chunkIndex >= $file->total_chunks) {
      throw new DomainRuleException('INVALID_CHUNK_INDEX', "Índice de fragmento inválido ({$data->chunkIndex} de {$file->total_chunks}).", 422);
    }

    if ($file->status === UploadOperationFile::STATUS_COMPLETED) {
      return [
        'operationKey' => $operation->operation_key,
        'fileKey' => $file->file_key,
        'chunkIndex' => $data->chunkIndex,
        'receivedChunksCount' => $file->total_chunks,
        'totalChunks' => $file->total_chunks,
        'uploadedBytes' => (int) $file->byte_size,
        'status' => $file->status,
        'allReceived' => true,
        'operationModel' => $operation,
      ];
    }

    $targetDisk = $file->disk ?: (string) config('xv.media_disk', 'public');
    $stagingPath = $this->helper->stagingPathFor($event, $operation, $file);
    $offset = $data->chunkIndex * (int) $file->chunk_size;

    $written = $this->helper->writeStreamAtOffset($targetDisk, $stagingPath, $offset, $inputStream);
    if ($written <= 0 && $file->byte_size > 0) {
      throw new DomainRuleException('EMPTY_CHUNK_BODY', "El fragmento #{$data->chunkIndex} llegó vacío.", 422);
    }

    $redisState = $this->helper->trackRedisChunk($file->id, $data->chunkIndex);
    $redisAvailable = $redisState['available'];
    $receivedCount = $redisState['count'];
    $allReceivedFast = $redisAvailable && $receivedCount >= $file->total_chunks;
    $prevBucket = $file->total_chunks > 0 ? (int) floor((max(0, $receivedCount - 1) / $file->total_chunks) * 6) : 0;
    $nextBucket = $file->total_chunks > 0 ? (int) floor(($receivedCount / $file->total_chunks) * 6) : 1;

    $needsCheckpoint = $forceCheckpoint || !$redisAvailable || $data->chunkIndex === 0 || $allReceivedFast || $file->total_chunks <= 6 || ($nextBucket > $prevBucket);
    if (!$needsCheckpoint) {
      return [
        'operationKey' => $operation->operation_key,
        'fileKey' => $file->file_key,
        'chunkIndex' => $data->chunkIndex,
        'receivedChunksCount' => $receivedCount,
        'totalChunks' => (int) $file->total_chunks,
        'uploadedBytes' => min((int) $file->byte_size, $this->helper->computeUploadedBytes($file->byte_size, $file->chunk_size, $file->total_chunks, $redisState['indices'])),
        'status' => UploadOperationFile::STATUS_UPLOADING,
        'allReceived' => false,
        'operationModel' => null,
      ];
    }

    return $this->commitChunkCheckpoint($operation, $file, $data->chunkIndex, $redisAvailable);
  }

  private function commitChunkCheckpoint(UploadOperation $operation, UploadOperationFile $file, int $chunkIndex, bool $redisAvailable): array
  {
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
        $received = $this->helper->mergeIndices($received, $this->helper->readRedisChunks($lockedFile->id));
      }
      if (!in_array($chunkIndex, $received, true)) {
        $received[] = $chunkIndex;
        sort($received);
      }

      $uploadedBytes = $this->helper->computeUploadedBytes($lockedFile->byte_size, $lockedFile->chunk_size, $lockedFile->total_chunks, $received);
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

      $freshOp = $this->helper->recalculateOperation($lockedOp);
      $freshChangedFile = $freshOp->files->firstWhere('id', $lockedFile->id);

      if ($allReceived && !in_array($prevStatus, [UploadOperationFile::STATUS_PROCESSING, UploadOperationFile::STATUS_COMPLETED], true)) {
        if ($this->helper->acquireJobDispatchLock($lockedFile->id)) {
          $queueName = $this->helper->queueForFile($lockedFile);
          DB::afterCommit(function () use ($lockedFile, $freshOp, $freshChangedFile, $queueName): void {
            $this->helper->broadcastOperation($freshOp, false, $freshChangedFile);
            ProcessUploadOperationFileJob::dispatch($lockedFile->id, $queueName);
          });
        }
      } else {
        DB::afterCommit(function () use ($freshOp, $freshChangedFile): void {
          $this->helper->broadcastOperation($freshOp, true, $freshChangedFile);
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
}
