<?php

namespace App\Actions\DeviceLock;

use App\DTOs\DeviceLock\CheckinGuestData;
use App\Events\GuestCheckedIn;
use App\Events\TablesUpdated;
use App\Exceptions\DomainRuleException;
use App\Http\Resources\Api\V1\DeviceLockResource;
use App\Http\Resources\Api\V1\EventTableResource;
use App\Jobs\ProcessTableOccupancyJob;
use App\Models\DeviceLock;
use App\Models\Event;
use App\Models\EventTable;
use Illuminate\Support\Facades\DB;

class CheckinGuestAction
{
  public function __construct(
    private readonly ResolveDeviceLockAction $resolveLock,
    private readonly SyncDeviceFingerprintsAction $syncFingerprints,
  ) {}

  public function execute(Event $event, CheckinGuestData $data): DeviceLock
  {
    $targetTable = EventTable::forEvent($event->id)
      ->matchingIdentifier($data->tableCode)
      ->first();

    if (!$targetTable) {
      throw new DomainRuleException(
        'INVALID_TABLE',
        'El código QR no corresponde a ninguna de las mesas del evento.',
        422
      );
    }

    $isTokenMismatch = !empty($targetTable->qr_token) && $data->qrToken &&
      !hash_equals((string) $targetTable->qr_token, (string) $data->qrToken);

    if ($isTokenMismatch) {
      throw new DomainRuleException(
        'QR_TOKEN_INVALID',
        'El código QR ha caducado o fue renovado. Escanea el código oficial actualizado de tu mesa.',
        422
      );
    }

    $cleanName = trim((string) $data->guestName);
    if (!preg_match('/^[a-zA-ZáéíóúÁÉÍÓÚñÑüÜ\s]+$/u', $cleanName) || count(preg_split('/\s+/', $cleanName, -1, PREG_SPLIT_NO_EMPTY)) < 2) {
      throw new DomainRuleException(
        'INVALID_GUEST_NAME',
        'Debe ser un nombre real con nombre y apellido (solo letras, sin números ni símbolos).',
        422
      );
    }

    $nameSlug = DeviceLock::normalizeSlug($cleanName);

    return DB::transaction(function () use ($event, $targetTable, $data, $nameSlug): DeviceLock {
      $lockedTable = EventTable::whereKey($targetTable->id)->lockForUpdate()->firstOrFail();

      [$existingLock] = $this->resolveLock->findExistingLock(
        $event,
        $data->deviceId,
        $data->candidateFingerprints,
        $data->hardwareSummary
      );

      $isAssignedToOtherTable = $existingLock &&
        $existingLock->event_table_id !== null &&
        !$existingLock->belongsToTable($lockedTable->id);

      if ($isAssignedToOtherTable) {
        throw new DomainRuleException(
          'DEVICE_LOCKED_OTHER_TABLE',
          "Este dispositivo ya pertenece a {$existingLock->table?->code}. No puedes cambiarte ni registrarte en otra mesa.",
          409,
          [
            'lockedTableCode' => $existingLock->table?->code,
            'restoredLock' => (new DeviceLockResource($existingLock, true))->resolve(),
          ]
        );
      }

      $isTakingNewSlot = !$existingLock || !$existingLock->belongsToTable($lockedTable->id);
      if ($isTakingNewSlot && !$lockedTable->hasAvailableSlot()) {
        throw new DomainRuleException(
          'TABLE_FULL',
          "{$lockedTable->code} ya alcanzó su capacidad máxima de {$lockedTable->capacity} personas registradas.",
          409
        );
      }

      $duplicateName = DeviceLock::where('event_table_id', $lockedTable->id)
        ->where('guest_name_slug', $nameSlug)
        ->when($existingLock, static fn ($q) => $q->where('id', '!=', $existingLock->id))
        ->exists();

      if ($duplicateName) {
        throw new DomainRuleException(
          'NAME_ALREADY_TAKEN',
          "Ya hay un invitado registrado como \"{$data->guestName}\" en {$lockedTable->code}. Agrega tu apellido.",
          409
        );
      }

      $canonicalDeviceId = $existingLock?->device_id ?: $data->deviceId;
      $actorKey = DeviceLock::normalizeSlug($lockedTable->code) . '__' . $nameSlug;

      $lock = DeviceLock::updateOrCreate(
        ['event_id' => $event->id, 'device_id' => $canonicalDeviceId],
        [
          'event_table_id' => $lockedTable->id,
          'hw_fingerprint' => $data->hwFingerprint ?: ($existingLock?->hw_fingerprint ?? ''),
          'hardware_summary' => $data->hardwareSummary ?? $existingLock?->hardware_summary,
          'guest_name' => $data->guestName,
          'guest_name_slug' => $nameSlug,
          'actor_key' => $actorKey,
          'locked_at' => $existingLock?->locked_at ?? now(),
        ]
      );

      $candidatesToSync = array_values(array_unique(array_filter(array_merge(
        $data->candidateFingerprints,
        [$data->deviceId, $data->hwFingerprint]
      ))));
      $this->syncFingerprints->execute($lock, $candidatesToSync);

      DB::afterCommit(static function () use ($event, $lock, $lockedTable): void {
        try {
          $tables = EventTable::with('deviceLocks')->forEvent($event->id)->ordered()->get();
          $payload = EventTableResource::collection($tables)->resolve();
          ProcessTableOccupancyJob::dispatch($event->id, $payload);
          broadcast(new TablesUpdated($event->code, $payload));
          // Notificar a todos los invitados del evento que alguien llegó
          broadcast(new GuestCheckedIn(
            $event->code,
            $lock->guest_name,
            $lockedTable->code,
            $lock->actor_key,
          ));
        } catch (\Throwable $e) {
          report($e);
        }
      });

      return $lock->fresh(['table']);
    });
  }
}
