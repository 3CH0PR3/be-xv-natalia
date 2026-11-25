<?php

namespace App\Actions\UploadOperation;

use App\DTOs\UploadOperation\UploadOperationActorData;
use App\Models\UploadOperation;
use App\Models\UploadOperationFile;
use App\Support\Storage\UploadOperationHelper;
use Illuminate\Database\Eloquent\Collection;

final readonly class ListUploadOperationsAction
{
  public function __construct(
    private UploadOperationHelper $helper,
  ) {}

  public function execute(UploadOperationActorData $data): Collection
  {
    if (!$data->deviceId && !$data->actorKey) {
      return new Collection();
    }

    $event = $this->helper->resolveEvent($data->eventCode);
    $operations = UploadOperation::with(['event', 'memory', 'files.mediaItem'])
      ->forEvent($event->id)
      ->visible()
      ->ownedBy($data->deviceId, $data->actorKey)
      ->orderByDesc('created_at')
      ->limit(25)
      ->get();

    foreach ($operations as $op) {
      if ($op->isTerminal()) {
        continue;
      }
      foreach ($op->files as $file) {
        if ($file->status === UploadOperationFile::STATUS_UPLOADING || $file->status === UploadOperationFile::STATUS_QUEUED_UPLOAD) {
          $redisChunks = $this->helper->readRedisChunks($file->id);
          if (count($redisChunks) > count($file->receivedChunksList())) {
            $merged = $this->helper->mergeIndices($file->receivedChunksList(), $redisChunks);
            $file->received_chunks = $merged;
            $file->uploaded_bytes = $this->helper->computeUploadedBytes($file->byte_size, $file->chunk_size, $file->total_chunks, $merged);
          }
        }
      }
    }

    return $operations;
  }
}
