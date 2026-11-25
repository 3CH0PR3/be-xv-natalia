<?php

namespace App\Actions\UploadOperation;

use App\DTOs\UploadOperation\AttachOperationPosterData;
use App\Exceptions\DomainRuleException;
use App\Models\UploadOperation;
use App\Support\Storage\UploadOperationHelper;
use Illuminate\Support\Facades\Storage;

final readonly class AttachOperationPosterAction
{
  public function __construct(
    private UploadOperationHelper $helper,
  ) {}

  public function execute(AttachOperationPosterData $data): UploadOperation
  {
    $event = $this->helper->resolveEvent($data->eventCode);
    $operation = $this->helper->findOperationForActor($data->operationKey, $event, $data->deviceId, $data->actorKey, $data->hostPin);
    $file = $operation->files()->where('file_key', trim($data->fileKey))->first();

    if (!$file) {
      throw new DomainRuleException('UPLOAD_FILE_NOT_FOUND', 'No se encontró el archivo para adjuntar portada.', 404);
    }

    $ext = strtolower($data->posterFile->getClientOriginalExtension() ?: 'jpg');
    $safeExt = in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true) ? $ext : 'jpg';
    $targetDisk = $file->disk ?: (string) config('xv.media_disk', 'public');
    $posterPath = "memories/{$event->code}/{$operation->memory_external_id}/posters/{$file->file_key}.{$safeExt}";

    $stream = fopen($data->posterFile->getRealPath(), 'rb');
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
        $this->helper->invalidateMemoryCaches($operation->memory);
        $this->helper->broadcastMemoryChange($operation->memory, $operation->request_origin, false);
      }
    }

    $freshOp = $operation->fresh(['event', 'memory', 'files.mediaItem']);
    $changedFile = $freshOp->files->firstWhere('id', $file->id);
    $this->helper->broadcastOperation($freshOp, false, $changedFile);

    return $freshOp;
  }
}
