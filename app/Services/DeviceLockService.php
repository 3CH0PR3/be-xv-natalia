<?php

namespace App\Services;

use App\Actions\DeviceLock\CheckDeviceStatusAction;
use App\Actions\DeviceLock\CheckinGuestAction;
use App\Actions\DeviceLock\ExpelGuestAction;
use App\Actions\DeviceLock\ResolveDeviceLockAction;
use App\Actions\Event\ResolveEventAction;
use App\Actions\Table\ListTablesAction;
use App\DTOs\DeviceLock\CheckinGuestData;
use App\DTOs\DeviceLock\DeviceStatusData;
use App\DTOs\DeviceLock\ExpelGuestData;
use App\DTOs\DeviceLock\ResolveDeviceLockData;
use App\Models\DeviceLock;
use Illuminate\Support\Collection;

final readonly class DeviceLockService
{
  public function __construct(
    private ResolveEventAction $resolveEvent,
    private ResolveDeviceLockAction $resolveLock,
    private CheckinGuestAction $checkinGuest,
    private CheckDeviceStatusAction $checkDeviceStatus,
    private ExpelGuestAction $expelGuest,
    private ListTablesAction $listTables,
  ) {}

  public function listTables(string $eventCode): Collection
  {
    return $this->listTables->execute($eventCode);
  }

  /** @return array{lock: ?DeviceLock, restoredByFingerprint: bool} */
  public function resolveLock(ResolveDeviceLockData $data): array
  {
    $event = $this->resolveEvent->execute($data->eventCode);

    return $this->resolveLock->execute($event, $data);
  }

  public function checkDeviceStatus(DeviceStatusData $data): ?DeviceLock
  {
    return $this->checkDeviceStatus->execute($data->eventCode, $data->deviceId);
  }

  public function checkin(CheckinGuestData $data): DeviceLock
  {
    $event = $this->resolveEvent->execute($data->eventCode);

    return $this->checkinGuest->execute($event, $data);
  }

  public function expelGuest(ExpelGuestData $data): Collection
  {
    $event = $this->resolveEvent->execute($data->eventCode);

    return $this->expelGuest->execute($event, $data);
  }
}
