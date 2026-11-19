<?php

namespace App\Actions\Memory;

use App\Jobs\ProcessShareJob;
use App\Models\DeviceLock;
use App\Models\Memory;
use App\Models\MemoryShare;
use Illuminate\Support\Facades\DB;

class IncrementMemoryShareAction
{
  public function execute(
    string $externalId,
    ?string $actorKey = null,
    ?string $deviceId = null,
    ?int $deviceLockId = null
  ): Memory {
    return DB::transaction(function () use ($externalId, $actorKey, $deviceId, $deviceLockId): Memory {
      $memory = Memory::with('event')
        ->where('external_id', $externalId)
        ->lockForUpdate()
        ->firstOrFail();

      $cleanActorKey = trim((string) $actorKey);
      $cleanDeviceId = trim((string) $deviceId);
      $resolvedLockId = $deviceLockId;

      $hasOwnerCredential = ($cleanActorKey !== '' || $cleanDeviceId !== '');
      if (!$resolvedLockId && $hasOwnerCredential) {
        $lock = DeviceLock::forEvent($memory->event_id)
          ->where(function ($q) use ($cleanActorKey, $cleanDeviceId): void {
            if ($cleanActorKey !== '') {
              $q->orWhere('actor_key', $cleanActorKey);
            }
            if ($cleanDeviceId !== '') {
              $q->orWhere('device_id', $cleanDeviceId);
            }
          })
          ->first();
        $resolvedLockId = $lock?->id;
      }

      MemoryShare::create([
        'memory_id' => $memory->id,
        'device_lock_id' => $resolvedLockId,
        'actor_key' => $cleanActorKey !== '' ? $cleanActorKey : ($cleanDeviceId !== '' ? $cleanDeviceId : 'anonymous'),
        'device_id' => $cleanDeviceId !== '' ? $cleanDeviceId : null,
      ]);

      $sharesCount = MemoryShare::where('memory_id', $memory->id)->count();
      $memory->update(['shares_count' => $sharesCount]);

      $fresh = $memory->fresh(['deviceLock:id,guest_name', 'mediaItems', 'reactions', 'comments']);

      DB::afterCommit(static function () use ($fresh, $sharesCount): void {
        ProcessShareJob::dispatch($fresh->id, $sharesCount);
      });

      return $fresh;
    });
  }
}
