<?php

namespace App\Actions\Memory;

use App\DTOs\Memory\IncrementShareData;
use App\Jobs\ProcessShareJob;
use App\Models\DeviceLock;
use App\Models\Memory;
use App\Models\MemoryShare;
use Illuminate\Support\Facades\DB;

class IncrementMemoryShareAction
{
  public function execute(
    IncrementShareData|string $data,
    ?string $actorKey = null,
    ?string $deviceId = null,
    int|string|null $deviceLockId = null
  ): Memory {
    $dto = $data instanceof IncrementShareData
      ? $data
      : new IncrementShareData($data, $actorKey, $deviceId, $deviceLockId);

    return DB::transaction(function () use ($dto): Memory {
      $memory = Memory::with('event')
        ->where('external_id', $dto->externalId)
        ->lockForUpdate()
        ->firstOrFail();

      $cleanActorKey = trim((string) $dto->actorKey);
      $cleanDeviceId = trim((string) $dto->deviceId);
      $resolvedLockId = $dto->deviceLockId;

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
