<?php

namespace App\Services;

use App\Actions\DeviceLock\CheckinGuestAction;
use App\Actions\DeviceLock\ResolveDeviceLockAction;
use App\DTOs\DeviceLock\CheckinGuestData;
use App\DTOs\DeviceLock\ResolveDeviceLockData;
use App\Models\DeviceLock;
use App\Models\Event;
use App\Models\EventTable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class DeviceLockService
{
    public function __construct(
        private readonly ResolveDeviceLockAction $resolveLock,
        private readonly CheckinGuestAction $checkinGuest,
    ) {}

    public function listTables(string $eventCode): Collection
    {
        $event = Event::byCode($eventCode)->firstOrFail();

        return Cache::store('redis')->remember(
            "tables:event:{$event->id}",
            60,
            fn () => EventTable::with('deviceLocks')
                ->forEvent($event->id)
                ->ordered()
                ->get()
        );
    }

    /** @return array{lock: ?DeviceLock, restoredByFingerprint: bool} */
    public function resolveLock(ResolveDeviceLockData $data): array
    {
        $event = Event::byCode($data->eventCode)->firstOrFail();
        return $this->resolveLock->execute($event, $data);
    }

    public function checkDeviceStatus(string $eventCode, string $deviceId): ?DeviceLock
    {
        $event = Event::byCode($eventCode)->firstOrFail();

        return Cache::store('redis')->remember(
            "device:lock:{$deviceId}",
            120,
            fn () => DeviceLock::with('table')
                ->forEvent($event->id)
                ->byDeviceId($deviceId)
                ->first()
        );
    }

    public function checkin(CheckinGuestData $data): DeviceLock
    {
        $event = Event::byCode($data->eventCode)->firstOrFail();
        return $this->checkinGuest->execute($event, $data);
    }
}
