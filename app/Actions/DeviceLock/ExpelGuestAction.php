<?php

namespace App\Actions\DeviceLock;

use App\DTOs\DeviceLock\ExpelGuestData;
use App\Events\GuestExpelled;
use App\Events\TablesUpdated;
use App\Http\Resources\Api\V1\EventTableResource;
use App\Jobs\ProcessTableOccupancyJob;
use App\Models\DeviceLock;
use App\Models\Event;
use App\Models\EventTable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ExpelGuestAction
{
  public function execute(Event $event, ExpelGuestData $data): Collection
  {
    $table = EventTable::forEvent($event->id)
      ->matchingIdentifier($data->tableId)
      ->firstOrFail();

    DB::transaction(function () use ($event, $table, $data): void {
      $lock = DeviceLock::forEvent($event->id)
        ->where('event_table_id', $table->id)
        ->matchingGuestIdentity($data->deviceId, $data->hwFingerprint)
        ->lockForUpdate()
        ->first();

      if (!$lock) {
        return;
      }

      $expelledDeviceId = $lock->device_id;
      // Desvincular de la mesa liberando el puesto en el salón, pero preservando
      // la huella digital del teléfono, perfil (avatar, bio) y recuerdos en base de datos
      $lock->update([
        'event_table_id' => null,
        'locked_at' => null,
      ]);

      DB::afterCommit(static function () use ($expelledDeviceId, $table): void {
        broadcast(new GuestExpelled($expelledDeviceId, $table->code));
      });
    });

    $tables = EventTable::with('deviceLocks')->forEvent($event->id)->ordered()->get();
    try {
      $payload = EventTableResource::collection($tables)->resolve();
      ProcessTableOccupancyJob::dispatch($event->id, $payload);
      broadcast(new TablesUpdated($event->code, $payload));
    } catch (\Throwable $e) {
      report($e);
    }

    return $tables;
  }
}
