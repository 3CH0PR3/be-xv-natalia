<?php

namespace App\Actions\UploadOperation;

use App\DTOs\UploadOperation\CreateUploadOperationData;
use App\Models\UploadOperation;
use App\Models\UploadOperationFile;
use App\Support\Storage\UploadOperationHelper;
use App\Support\Validation\MediaFilePolicy;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final readonly class CreateUploadOperationAction
{
  public function __construct(
    private UploadOperationHelper $helper,
  ) {}

  public function execute(CreateUploadOperationData $data): UploadOperation
  {
    $event = $this->helper->resolveEvent($data->eventCode);
    $payload = $data->payload;
    $deviceLock = $data->deviceLock;

    $operationKey = trim((string) ($payload['operationKey'] ?? '')) ?: ('uop-' . Str::lower((string) Str::ulid()));
    $ownerDeviceId = trim((string) ($data->deviceId ?: ($deviceLock?->device_id ?? ''))) ?: null;
    $ownerActorKey = trim((string) ($data->actorKey ?: ($deviceLock?->actor_key ?? ''))) ?: null;

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
    $tableCode = trim((string) ($payload['tableCode'] ?? '')) ?: ($deviceLock?->table?->code ?: 'Mesa 1');
    $momentTag = trim((string) ($payload['momentTag'] ?? '')) ?: 'Fiesta en Vivo';
    $caption = trim((string) ($payload['caption'] ?? ''));

    $fileRows = $this->buildFileRows($payload, $operationKey);

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
      $data,
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
        'request_origin' => $data->requestOrigin,
        'status' => UploadOperation::STATUS_QUEUED,
        'started_at' => now(),
      ]);

      foreach ($fileRows as $row) {
        $operation->files()->create($row);
      }

      $fresh = $this->helper->recalculateOperation($operation);

      DB::afterCommit(function () use ($fresh): void {
        $this->helper->broadcastOperation($fresh, false);
      });

      return $fresh;
    });
  }

  private function buildFileRows(array $payload, string $operationKey): array
  {
    $rawFiles = is_array($payload['files'] ?? null) ? $payload['files'] : [];
    $targetDisk = (string) config('xv.media_disk', 'public');
    $maxFileBytes = max(1024 * 1024, (int) config('xv.max_upload_file_bytes', 700 * 1024 * 1024));
    $fileRows = [];

    foreach (array_values($rawFiles) as $index => $rawFile) {
      $originalName = trim((string) ($rawFile['originalName'] ?? "archivo-{$index}"));
      $mimeType = trim((string) ($rawFile['mimeType'] ?? '')) ?: 'application/octet-stream';
      $byteSize = max(0, (int) ($rawFile['byteSize'] ?? 0));
      $chunkSize = min(UploadOperationHelper::MAX_CHUNK_SIZE, max(1024, (int) ($rawFile['chunkSize'] ?? UploadOperationHelper::DEFAULT_CHUNK_SIZE)));
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
      } elseif ($byteSize > $maxFileBytes) {
        $status = UploadOperationFile::STATUS_FAILED;
        $errorCode = 'FILE_TOO_LARGE';
        $maxMb = (int) round($maxFileBytes / (1024 * 1024));
        $errorMessage = "El archivo \"{$originalName}\" supera el límite permitido de {$maxMb} MB.";
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

    return $fileRows;
  }
}
