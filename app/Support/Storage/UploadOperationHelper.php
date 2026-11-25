<?php

namespace App\Support\Storage;

use App\Events\MemoryCreated;
use App\Events\MemoryUpdated;
use App\Events\UploadOperationUpdated;
use App\Exceptions\DomainRuleException;
use App\Http\Resources\Api\V1\MemoryResource;
use App\Http\Resources\Api\V1\UploadOperationFileResource;
use App\Models\Event;
use App\Models\Memory;
use App\Models\UploadOperation;
use App\Models\UploadOperationFile;
use App\Models\User;
use App\Support\Cache\Keys\EventKeys;
use App\Traits\Cache\InvalidatesCache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

final class UploadOperationHelper
{
  use InvalidatesCache;

  public const CHUNK_DISK = 'local';
  public const DEFAULT_CHUNK_SIZE = 2097152;
  public const MAX_CHUNK_SIZE = 16777216;
  public const HEAVY_MEDIA_BYTES_THRESHOLD = 26214400; // 25 MB

  private const EXT_BY_MIME = [
    'image/jpeg' => 'jpg',
    'image/pjpeg' => 'jpg',
    'image/png' => 'png',
    'image/apng' => 'png',
    'image/webp' => 'webp',
    'image/avif' => 'avif',
    'image/bmp' => 'bmp',
    'image/svg+xml' => 'svg',
    'video/mp4' => 'mp4',
    'video/quicktime' => 'mov',
    'video/webm' => 'webm',
    'video/3gpp' => '3gp',
    'video/x-matroska' => 'mkv',
  ];

  public function resolveEvent(?string $eventCode = null): Event
  {
    $code = trim((string) ($eventCode ?: config('xv.default_event_code', 'XV-NATALIA')));

    return Event::byCode($code)->firstOrFail();
  }

  public function findOperationForActor(
    string $operationKey,
    Event $event,
    ?string $deviceId,
    ?string $actorKey,
    ?string $hostPin = null,
  ): UploadOperation {
    $isHost = $hostPin !== null && $hostPin !== '' && User::verifyHostPin($hostPin);
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

  public function stagingPathFor(Event $event, UploadOperation $operation, UploadOperationFile $file): string
  {
    return "memories/{$event->code}/{$operation->memory_external_id}/.staging-{$file->file_key}.tmp";
  }

  public function finalPathFor(
    Event $event,
    UploadOperation $operation,
    UploadOperationFile $file,
    ?string $overrideExtension = null
  ): string {
    $cleanMime = strtolower(trim(explode(';', (string) $file->mime_type)[0]));
    $extension = $overrideExtension ? strtolower(trim($overrideExtension, '. ')) : '';
    if ($extension === '' && isset(self::EXT_BY_MIME[$cleanMime])) {
      $extension = self::EXT_BY_MIME[$cleanMime];
    }
    if ($extension === '') {
      $extension = strtolower(pathinfo($file->original_name, PATHINFO_EXTENSION));
    }
    if ($extension === '' || in_array($extension, ['heic', 'heif'], true)) {
      $extension = $file->media_type === 'video' ? 'mp4' : 'jpg';
    }
    $safeStem = Str::slug(pathinfo($file->original_name, PATHINFO_FILENAME)) ?: 'media';

    return "memories/{$event->code}/{$operation->memory_external_id}/{$safeStem}-{$file->file_key}.{$extension}";
  }

  public function queueForFile(UploadOperationFile $file): string
  {
    if ($file->media_type === 'video' && (int) $file->byte_size > self::HEAVY_MEDIA_BYTES_THRESHOLD) {
      return 'media-heavy';
    }

    return 'media-fast';
  }

  public function detectImageSignature(string $diskName, string $path): ?array
  {
    $stream = Storage::disk($diskName)->readStream($path);
    if ($stream === false) {
      return null;
    }

    try {
      $header = fread($stream, 32) ?: '';
    } finally {
      if (is_resource($stream)) {
        fclose($stream);
      }
    }

    if (strlen($header) < 4) {
      return null;
    }

    if (str_starts_with($header, "\xFF\xD8\xFF")) {
      return ['mime' => 'image/jpeg', 'ext' => 'jpg', 'webRenderable' => true];
    }
    if (str_starts_with($header, "\x89PNG\r\n\x1A\n")) {
      return ['mime' => 'image/png', 'ext' => 'png', 'webRenderable' => true];
    }
    if (str_starts_with($header, 'RIFF') && substr($header, 8, 4) === 'WEBP') {
      return ['mime' => 'image/webp', 'ext' => 'webp', 'webRenderable' => true];
    }
    if (str_starts_with($header, 'GIF87a') || str_starts_with($header, 'GIF89a')) {
      return ['mime' => 'image/gif', 'ext' => 'gif', 'webRenderable' => false, 'forbidden' => true];
    }
    if (str_starts_with($header, "II*\x00") || str_starts_with($header, "MM\x00*")) {
      return ['mime' => 'image/x-adobe-dng', 'ext' => 'dng', 'webRenderable' => false];
    }
    if (substr($header, 4, 4) === 'ftyp') {
      $brandBlock = strtolower(substr($header, 8, 20));
      if (preg_match('/(heic|heix|hevc|hevx|heim|heis|hevm|hevs|mif1|msf1)/', $brandBlock) === 1) {
        return ['mime' => 'image/heic', 'ext' => 'heic', 'webRenderable' => false];
      }
      if (str_contains($brandBlock, 'avif') || str_contains($brandBlock, 'avis')) {
        return ['mime' => 'image/avif', 'ext' => 'avif', 'webRenderable' => true];
      }
    }

    return null;
  }

  public function createThumbnailFromStoredImage(
    string $diskName,
    string $finalPath,
    Event $event,
    UploadOperation $operation,
    UploadOperationFile $file,
    int $maxDim = 480
  ): ?string {
    if (!function_exists('imagecreatetruecolor') || !function_exists('getimagesizefromstring')) {
      return null;
    }

    $disk = Storage::disk($diskName);
    $rawStream = $disk->readStream($finalPath);
    if ($rawStream === false) {
      return null;
    }

    $tmpSrc = tempnam(sys_get_temp_dir(), 'xv_thumb_');
    if ($tmpSrc === false) {
      fclose($rawStream);
      return null;
    }

    try {
      $tmpHandle = fopen($tmpSrc, 'wb');
      if ($tmpHandle === false) {
        return null;
      }
      try {
        stream_copy_to_stream($rawStream, $tmpHandle);
      } finally {
        fclose($tmpHandle);
      }
    } finally {
      if (is_resource($rawStream)) {
        fclose($rawStream);
      }
    }

    try {
      $info = @getimagesize($tmpSrc);
      if (!$info || empty($info[0]) || empty($info[1])) {
        return null;
      }

      $srcW = (int) $info[0];
      $srcH = (int) $info[1];
      $imageType = (int) ($info[2] ?? 0);

      $srcImg = match ($imageType) {
        IMAGETYPE_JPEG => function_exists('imagecreatefromjpeg') ? @imagecreatefromjpeg($tmpSrc) : false,
        IMAGETYPE_PNG => function_exists('imagecreatefrompng') ? @imagecreatefrompng($tmpSrc) : false,
        IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($tmpSrc) : false,
        default => false,
      };

      if (!$srcImg) {
        return null;
      }

      if ($imageType === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
        $exif = @exif_read_data($tmpSrc);
        $orientation = (int) ($exif['Orientation'] ?? 1);
        $rotated = match ($orientation) {
          3 => @imagerotate($srcImg, 180, 0),
          6 => @imagerotate($srcImg, -90, 0),
          8 => @imagerotate($srcImg, 90, 0),
          default => false,
        };
        if ($rotated) {
          imagedestroy($srcImg);
          $srcImg = $rotated;
          $srcW = imagesx($srcImg);
          $srcH = imagesy($srcImg);
        }
      }

      $scale = min(1.0, $maxDim / max(1, max($srcW, $srcH)));
      $dstW = max(1, (int) round($srcW * $scale));
      $dstH = max(1, (int) round($srcH * $scale));

      $dstImg = imagecreatetruecolor($dstW, $dstH);
      imagealphablending($dstImg, false);
      imagesavealpha($dstImg, true);
      $transparent = imagecolorallocatealpha($dstImg, 0, 0, 0, 127);
      imagefilledrectangle($dstImg, 0, 0, $dstW, $dstH, $transparent);
      imagealphablending($dstImg, true);
      imagecopyresampled($dstImg, $srcImg, 0, 0, 0, 0, $dstW, $dstH, $srcW, $srcH);
      imagedestroy($srcImg);

      $useWebp = function_exists('imagewebp');
      $thumbExt = $useWebp ? 'webp' : 'jpg';
      $thumbPath = "memories/{$event->code}/{$operation->memory_external_id}/thumbs/{$file->file_key}.{$thumbExt}";

      ob_start();
      $ok = $useWebp ? @imagewebp($dstImg, null, 78) : @imagejpeg($dstImg, null, 80);
      $thumbBytes = ob_get_clean();
      imagedestroy($dstImg);

      if (!$ok || !is_string($thumbBytes) || strlen($thumbBytes) <= 0) {
        return null;
      }

      $disk->put($thumbPath, $thumbBytes);
      return $thumbPath;
    } catch (\Throwable) {
      return null;
    } finally {
      if (is_file($tmpSrc)) {
        @unlink($tmpSrc);
      }
    }
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

  public function writeStreamAtOffset(string $targetDisk, string $stagingPath, int $offset, $inputStream): int
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

  public function trackRedisChunk(int $fileId, int $chunkIndex): array
  {
    try {
      $key = $this->redisChunkSetKey($fileId);
      Redis::sAdd($key, (string) $chunkIndex);
      Redis::expire($key, 86400);
      $raw = Redis::sMembers($key);
      $indices = array_values(array_unique(array_map('intval', is_array($raw) ? $raw : [])));
      sort($indices);

      return ['available' => true, 'count' => count($indices), 'indices' => $indices];
    } catch (\Throwable) {
      return ['available' => false, 'count' => 0, 'indices' => []];
    }
  }

  public function readRedisChunks(int $fileId): array
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

  public function acquireJobDispatchLock(int $fileId): bool
  {
    try {
      return (bool) Redis::set($this->redisDispatchLockKey($fileId), '1', 'EX', 86400, 'NX');
    } catch (\Throwable) {
      return true;
    }
  }

  public function mergeIndices(array $a, array $b): array
  {
    $merged = array_values(array_unique(array_map('intval', array_merge($a, $b))));
    sort($merged);

    return $merged;
  }

  public function computeUploadedBytes(int $byteSize, int $chunkSize, int $totalChunks, array $receivedIndices): int
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

  private function redisChunkSetKey(int $fileId): string
  {
    return "xv:op_file:{$fileId}:chunks";
  }

  private function redisDispatchLockKey(int $fileId): string
  {
    return "xv:op_file:{$fileId}:dispatched";
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
