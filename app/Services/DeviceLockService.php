<?php

namespace App\Services;

use App\Actions\DeviceLock\CheckinGuestAction;
use App\Actions\DeviceLock\CheckDeviceStatusAction;
use App\Actions\DeviceLock\ExpelGuestAction;
use App\Actions\DeviceLock\ResolveDeviceLockAction;
use App\Actions\Table\ListTablesAction;
use App\DTOs\DeviceLock\CheckinGuestData;
use App\DTOs\DeviceLock\DeviceStatusData;
use App\DTOs\DeviceLock\ExpelGuestData;
use App\DTOs\DeviceLock\ResolveDeviceLockData;
use App\Models\DeviceLock;
use App\Models\Event;
use Illuminate\Support\Collection;

class DeviceLockService
{
  public function __construct(
    private readonly ResolveDeviceLockAction $resolveLock,
    private readonly CheckinGuestAction $checkinGuest,
    private readonly CheckDeviceStatusAction $checkDeviceStatus,
    private readonly ExpelGuestAction $expelGuest,
    private readonly ListTablesAction $listTables,
  ) {}

  public function listTables(string $eventCode): Collection
  {
    return $this->listTables->execute($eventCode);
  }

  /** @return array{lock: ?DeviceLock, restoredByFingerprint: bool} */
  public function resolveLock(ResolveDeviceLockData $data): array
  {
    $event = Event::byCode($data->eventCode)->firstOrFail();
    return $this->resolveLock->execute($event, $data);
  }

  public function checkDeviceStatus(DeviceStatusData|string $data, ?string $deviceId = null): ?DeviceLock
  {
    $eventCode = $data instanceof DeviceStatusData ? $data->eventCode : (string) $data;
    $targetDeviceId = $data instanceof DeviceStatusData ? $data->deviceId : (string) $deviceId;

    return $this->checkDeviceStatus->execute($eventCode, $targetDeviceId);
  }

  public function checkin(CheckinGuestData $data): DeviceLock
  {
    $event = Event::byCode($data->eventCode)->firstOrFail();
    return $this->checkinGuest->execute($event, $data);
  }

  public function expelGuest(ExpelGuestData $data): Collection
  {
    $event = Event::byCode($data->eventCode)->firstOrFail();
    return $this->expelGuest->execute($event, $data);
  }
}
