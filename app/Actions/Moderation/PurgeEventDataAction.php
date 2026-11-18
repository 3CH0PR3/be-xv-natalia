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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Purga recuerdos, votos y bloqueos de mesas del evento, rotando `epoch` y `db_scope`
 * y emitiendo `EventDataPurged` por Reverb para que los navegadores limpien su caché al instante.
 */
class PurgeEventDataAction
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

      DB::afterCommit(static function () use ($lockedEvent, $nextScope, $nextEpoch, $filesByDisk): void {
        foreach ($filesByDisk as $diskName => $paths) {
          if (empty($paths)) {
            continue;
          }
          Storage::disk($diskName)->delete($paths);
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
