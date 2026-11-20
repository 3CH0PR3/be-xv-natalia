<?php

namespace App\Actions\DeviceLock;

use App\Models\DeviceLock;
use App\Models\Event;

class CheckDeviceStatusAction
{
  public function execute(string $eventCode, string $deviceId): ?DeviceLock
  {
    $event = Event::byCode($eventCode)->firstOrFail();

    return DeviceLock::with('table')
      ->forEvent($event->id)
      ->byDeviceId($deviceId)
      ->first();
  }
}
