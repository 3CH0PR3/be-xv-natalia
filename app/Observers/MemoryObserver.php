<?php

namespace App\Observers;

use App\Models\Event;
use App\Models\Memory;
use App\Models\UploadOperation;
use App\Models\UploadOperationFile;
use App\Support\Cache\Keys\EventKeys;
use App\Support\Storage\UploadOperationHelper;
use App\Traits\Cache\InvalidatesCache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;

class MemoryObserver
{
  use InvalidatesCache;

  public function creating(Memory $memory): void
  {
    if (!$memory->timestamp_ms) {
      $memory->timestamp_ms = (int) round(microtime(true) * 1000);
    }

    if (!$memory->external_id) {
      $memory->external_id = 'xv-nat-' . $memory->timestamp_ms;
    }

    if (!$memory->moment_tag) {
      $memory->moment_tag = 'Fiesta en Vivo';
    }
  }

  public function created(Memory $memory): void
  {
    $this->forgetItem(EventKeys::memory($memory->external_id));
    $this->scanAndDelete(EventKeys::feedPattern($memory->event_id));
    $this->scanAndDelete(EventKeys::profilePattern($memory->event_id));
  }

  public function updated(Memory $memory): void
  {
    $this->forgetItem(EventKeys::memory($memory->external_id));
    $this->scanAndDelete(EventKeys::feedPattern($memory->event_id));
    $this->scanAndDelete(EventKeys::profilePattern($memory->event_id));
  }

  public function deleting(Memory $memory): void
  {
    $this->forgetItem(EventKeys::memory($memory->external_id));
    $this->scanAndDelete(EventKeys::feedPattern($memory->event_id));
    $this->scanAndDelete(EventKeys::profilePattern($memory->event_id));

    $eventCode = $memory->event?->code ?? Event::whereKey($memory->event_id)->value('code');
    $memoryExternalId = $memory->external_id;

    // Collect file paths before CASCADE delete from database
    $filesByDisk = $memory->mediaItems()
      ->get(['disk', 'file_path', 'thumb_path', 'poster_path'])
      ->groupBy(fn ($item) => $item->disk ?: config('xv.media_disk', 'public'))
      ->map(fn ($items) => $items->flatMap->storedPaths()->filter()->values()->all())
      ->all();

    $operationIds = UploadOperation::query()
      ->where('memory_id', $memory->id)
      ->when($memory->event_id && $memoryExternalId, static function ($query) use ($memory, $memoryExternalId): void {
        $query->orWhere(static function ($q) use ($memory, $memoryExternalId): void {
          $q->where('event_id', $memory->event_id)
            ->where('memory_external_id', $memoryExternalId);
        });
      })
      ->pluck('id')
      ->all();

    $opFiles = !empty($operationIds)
      ? UploadOperationFile::whereIn('upload_operation_id', $operationIds)
        ->get(['id', 'disk', 'poster_path', 'final_path', 'temp_dir'])
      : collect();
    $opFileIds = $opFiles->pluck('id')->all();
    $tempDirs = $opFiles->pluck('temp_dir')->filter()->unique()->values()->all();

    foreach ($opFiles as $opFile) {
      $opDisk = $opFile->disk ?: (string) config('xv.media_disk', 'public');
      foreach ([$opFile->final_path, $opFile->poster_path] as $opPath) {
        if ($opPath) {
          $filesByDisk[$opDisk][] = $opPath;
        }
      }
    }

    foreach ($filesByDisk as $diskName => $paths) {
      $filesByDisk[$diskName] = array_values(array_unique($paths));
    }

    if (!empty($operationIds)) {
      UploadOperation::whereIn('id', $operationIds)->delete();
    }

    $disksToClean = !empty($filesByDisk)
      ? $filesByDisk
      : [(string) config('xv.media_disk', 'public') => []];

    DB::afterCommit(static function () use ($disksToClean, $eventCode, $memoryExternalId, $opFileIds, $tempDirs): void {
      if (!empty($opFileIds)) {
        $redisKeys = [];
        foreach ($opFileIds as $fileId) {
          $redisKeys[] = "xv:op_file:{$fileId}:chunks";
          $redisKeys[] = "xv:op_file:{$fileId}:dispatched";
        }
        try {
          Redis::del(...$redisKeys);
        } catch (\Throwable) {
        }
      }

      if (!empty($tempDirs)) {
        $chunkDisk = Storage::disk(UploadOperationHelper::CHUNK_DISK);
        foreach ($tempDirs as $tempDir) {
          if ($chunkDisk->directoryExists($tempDir)) {
            $chunkDisk->deleteDirectory($tempDir);
          }
        }
      }

      foreach ($disksToClean as $diskName => $paths) {
        $disk = Storage::disk($diskName);
        if (!empty($paths)) {
          $disk->delete($paths);
        }

        if ($eventCode && $memoryExternalId) {
          $memoryDir = "memories/{$eventCode}/{$memoryExternalId}";
          if ($disk->directoryExists($memoryDir) && empty($disk->allFiles($memoryDir))) {
            $disk->deleteDirectory($memoryDir);
          }
        }
      }
    });
  }
}
