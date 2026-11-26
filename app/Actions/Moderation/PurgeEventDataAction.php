<?php

namespace App\Actions\Moderation;

use App\Events\EventDataPurged;
use App\Events\TablesUpdated;
use App\Http\Resources\Api\V1\EventTableResource;
use App\Jobs\ProcessTableOccupancyJob;
use App\Models\DeviceLock;
use App\Models\Event;
use App\Models\EventTable;
use App\Models\Memory;
use App\Models\PartyReaction;
use App\Models\UploadOperation;
use App\Models\UploadOperationFile;
use App\Support\Storage\UploadOperationHelper;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;

/**
 * Purga recuerdos, votos y bloqueos de mesas del evento, rotando `epoch` y `db_scope`
 * y emitiendo `EventDataPurged` por Reverb para que los navegadores limpien su caché al instante.
 */
final readonly class PurgeEventDataAction
{
  public function execute(Event $event): array
  {
    $filesByDisk = Memory::forEvent($event->id)
      ->with('mediaItems')
      ->get()
      ->flatMap->mediaItems
      ->groupBy(static fn ($item) => $item->disk ?: config('xv.media_disk', 'public'))
      ->map(static fn ($items) => $items->flatMap->storedPaths()->filter()->values()->all())
      ->all();

    return DB::transaction(function () use ($event, $filesByDisk): array {
      $lockedEvent = Event::whereKey($event->id)->lockForUpdate()->firstOrFail();

      $operationIds = UploadOperation::forEvent($lockedEvent->id)->pluck('id')->all();
      $opFiles = !empty($operationIds)
        ? UploadOperationFile::whereIn('upload_operation_id', $operationIds)
          ->get(['id', 'disk', 'poster_path', 'final_path', 'temp_dir'])
        : collect();
      $opFileIds = $opFiles->pluck('id')->all();
      $tempDirs = $opFiles->pluck('temp_dir')->filter()->unique()->values()->all();

      $mergedFilesByDisk = $filesByDisk;
      $defaultDisk = (string) config('xv.media_disk', 'public');
      foreach ($opFiles as $opFile) {
        $opDisk = $opFile->disk ?: $defaultDisk;
        foreach ([$opFile->final_path, $opFile->poster_path] as $opPath) {
          if ($opPath) {
            $mergedFilesByDisk[$opDisk][] = $opPath;
          }
        }
      }
      foreach ($mergedFilesByDisk as $diskName => $paths) {
        $mergedFilesByDisk[$diskName] = array_values(array_unique($paths));
      }

      if (!empty($operationIds)) {
        UploadOperation::forEvent($lockedEvent->id)->delete();
      }
      Memory::forEvent($lockedEvent->id)->delete();
      DeviceLock::forEvent($lockedEvent->id)->delete();
      PartyReaction::where('event_id', $lockedEvent->id)->delete();

      $nextEpoch = $lockedEvent->epoch + 1;
      $nextScope = config('xv.db_scope_prefix', 'laravel') . "-{$nextEpoch}";

      $lockedEvent->update([
        'party_celebrations_count' => 0,
        'epoch' => $nextEpoch,
        'db_scope' => $nextScope,
      ]);

      $disksToClean = !empty($mergedFilesByDisk) ? $mergedFilesByDisk : [$defaultDisk => []];
      if (!array_key_exists($defaultDisk, $disksToClean)) {
        $disksToClean[$defaultDisk] = [];
      }

      DB::afterCommit(static function () use ($lockedEvent, $nextScope, $nextEpoch, $disksToClean, $opFileIds, $tempDirs): void {
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

        $eventDir = "memories/{$lockedEvent->code}";
        foreach ($disksToClean as $diskName => $paths) {
          $disk = Storage::disk($diskName);
          if (!empty($paths)) {
            $disk->delete($paths);
          }
          if ($disk->directoryExists($eventDir)) {
            foreach ($disk->directories($eventDir) as $subDir) {
              if (empty($disk->allFiles($subDir))) {
                $disk->deleteDirectory($subDir);
              }
            }
            if (empty($disk->allFiles($eventDir))) {
              $disk->deleteDirectory($eventDir);
            }
          }
        }

        $tables = EventTable::with('deviceLocks')->forEvent($lockedEvent->id)->ordered()->get();
        $payload = EventTableResource::collection($tables)->resolve();
        ProcessTableOccupancyJob::dispatch($lockedEvent->id, $payload);
        broadcast(new TablesUpdated($lockedEvent->code, $payload));
        broadcast(new EventDataPurged($lockedEvent->code, $nextScope, $nextEpoch));
      });

      return [
        'eventCode' => $lockedEvent->code,
        'dbScope' => $nextScope,
        'epoch' => $nextEpoch,
        'purged' => true,
      ];
    });
  }
}
