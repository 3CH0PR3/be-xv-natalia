<?php

namespace App\Actions\Memory;

use App\DTOs\Memory\CreateMemoryData;
use App\Jobs\ProcessMemoryJob;
use App\Models\DeviceLock;
use App\Models\Event;
use App\Models\EventTable;
use App\Models\Memory;
use App\Models\MemoryMediaItem;
use App\Services\ChunkUploadService;
use App\Support\Cache\Keys\EventKeys;
use App\Traits\Cache\InvalidatesCache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateMemoryAction
{
  use InvalidatesCache;

  public function __construct(
    private readonly ChunkUploadService $chunkUploadService,
  ) {}

  public function execute(Event $event, CreateMemoryData $data): Memory
  {
    $table = EventTable::forEvent($event->id)->matchingIdentifier($data->tableCode)->first();
    $nowMs = (int) round(microtime(true) * 1000);
    $externalId = $data->externalId ?: ('xv-nat-' . $nowMs);
    $disk = config('xv.media_disk', 'public');
    $orderedSources = $this->resolveOrderedSources($data);

    return DB::transaction(function () use ($event, $table, $data, $nowMs, $externalId, $disk, $orderedSources): Memory {
      $deviceLockId = $data->deviceLockId;
      $hasOwnerCredential = ($data->ownerActorKey || $data->ownerDeviceId);
      if (!$deviceLockId && $hasOwnerCredential) {
        $deviceLock = DeviceLock::forEvent($event->id)
          ->where(function ($q) use ($data): void {
            if ($data->ownerActorKey) {
              $q->orWhere('actor_key', $data->ownerActorKey);
            }
            if ($data->ownerDeviceId) {
              $q->orWhere('device_id', $data->ownerDeviceId);
            }
            if ($data->guestName) {
              $q->orWhere('guest_name', $data->guestName);
            }
          })
          ->first();
        $deviceLockId = $deviceLock?->id;
      }

      $memory = Memory::updateOrCreate(
        ['external_id' => $externalId],
        [
          'event_id' => $event->id,
          'event_table_id' => $table?->id,
          'device_lock_id' => $deviceLockId,
          'guest_name' => $data->guestName,
          'table_code' => $data->tableCode,
          'owner_device_id' => $data->ownerDeviceId,
          'owner_actor_key' => $data->ownerActorKey,
          'moment_tag' => $data->momentTag,
          'caption' => $data->caption,
          'media_type' => 'image',
          'status' => Memory::STATUS_APPROVED,
          'timestamp_ms' => $nowMs,
        ]
      );

      $dir = "memories/{$event->code}/{$externalId}";
      $primaryType = 'image';

      foreach ($orderedSources as $idx => $source) {
        $blobKey = 'media-' . $nowMs . '-' . Str::lower(Str::random(6));

        if ($source['type'] === 'upload') {
          $assembled = $this->chunkUploadService->assembleToTargetDisk(
            uploadKey: $source['key'],
            targetDisk: $disk,
            destDir: $dir,
            blobKey: $blobKey,
            actorKey: $data->ownerActorKey,
            deviceId: $data->ownerDeviceId
          );
          $filePath = $assembled['filePath'];
          $mime = $assembled['mimeType'];
          $isVideo = $assembled['isVideo'];
          $byteSize = $assembled['byteSize'];
        } else {
          $uploadedFile = $source['file'];
          $mime = $uploadedFile->getMimeType() ?? 'application/octet-stream';
          $isVideo = str_starts_with($mime, 'video/');
          $ext = $uploadedFile->extension() ?: ($isVideo ? 'mp4' : 'jpg');
          $filePath = $uploadedFile->storeAs($dir, "{$blobKey}.{$ext}", $disk);
          $byteSize = $uploadedFile->getSize() ?: 0;
        }

        if ($idx === 0 && $isVideo) {
          $primaryType = 'video';
        }

        $posterPath = null;
        if (isset($data->posters[$idx]) && $data->posters[$idx]) {
          $posterPath = $data->posters[$idx]->storeAs($dir, "{$blobKey}_poster.jpg", $disk);
        }

        MemoryMediaItem::create([
          'memory_id' => $memory->id,
          'external_id' => "{$externalId}-{$idx}",
          'media_blob_key' => $blobKey,
          'media_type' => $isVideo ? 'video' : 'image',
          'disk' => $disk,
          'file_path' => $filePath,
          'thumb_path' => $posterPath ?: ($isVideo ? null : $filePath),
          'poster_path' => $isVideo ? $posterPath : null,
          'mime_type' => $mime,
          'byte_size' => $byteSize,
          'sort_order' => $idx,
        ]);
      }

      if ($memory->media_type !== $primaryType) {
        $memory->update(['media_type' => $primaryType]);
      }

      $freshMemory = $memory->fresh([
        'deviceLock:id,guest_name,avatar_path,cover_path,actor_key,device_id,updated_at',
        'mediaItems',
        'reactions',
        'comments.commentLikes'
      ]);

      DB::afterCommit(function () use ($freshMemory): void {
        try {
          $eventCode = $freshMemory->event?->code ?? (string) config('xv.default_event_code', 'XV-NATALIA');
          $payload = (new \App\Http\Resources\Api\V1\MemoryResource($freshMemory))->resolve();
          broadcast(new \App\Events\MemoryCreated($eventCode, $payload))->toOthers();
        } catch (\Throwable $e) {
          \Illuminate\Support\Facades\Log::warning('Direct broadcast of MemoryCreated failed: ' . $e->getMessage());
        }

        try {
          $this->forgetItem(EventKeys::memory($freshMemory->external_id));
          $this->scanAndDelete(EventKeys::feedPattern($freshMemory->event_id));
          $this->scanAndDelete(EventKeys::profilePattern($freshMemory->event_id));
        } catch (\Throwable) {
        }

        ProcessMemoryJob::dispatch($freshMemory->id, false);
      });

      return $freshMemory;
    });
  }

  private function resolveOrderedSources(CreateMemoryData $data): array
  {
    if (count($data->fileOrder) === 0) {
      $sources = [];
      foreach ($data->files as $file) {
        $sources[] = ['type' => 'file', 'file' => $file];
      }
      foreach ($data->uploadedKeys as $key) {
        $sources[] = ['type' => 'upload', 'key' => $key];
      }
      return $sources;
    }

    $sources = [];
    $usedFileIndexes = [];
    $usedUploadKeys = [];

    foreach ($data->fileOrder as $token) {
      if (str_starts_with($token, 'file:')) {
        $idx = (int) substr($token, 5);
        if (isset($data->files[$idx]) && !isset($usedFileIndexes[$idx])) {
          $usedFileIndexes[$idx] = true;
          $sources[] = ['type' => 'file', 'file' => $data->files[$idx]];
        }
      } elseif (str_starts_with($token, 'upload:')) {
        $key = substr($token, 7);
        if (in_array($key, $data->uploadedKeys, true) && !isset($usedUploadKeys[$key])) {
          $usedUploadKeys[$key] = true;
          $sources[] = ['type' => 'upload', 'key' => $key];
        }
      }
    }

    foreach ($data->files as $idx => $file) {
      if (!isset($usedFileIndexes[$idx])) {
        $sources[] = ['type' => 'file', 'file' => $file];
      }
    }

    foreach ($data->uploadedKeys as $key) {
      if (!isset($usedUploadKeys[$key])) {
        $sources[] = ['type' => 'upload', 'key' => $key];
      }
    }

    return $sources;
  }
}
