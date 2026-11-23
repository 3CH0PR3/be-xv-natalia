<?php

namespace App\Observers;

use App\Models\DeviceLock;
use App\Support\Cache\Keys\EventKeys;
use App\Traits\Cache\InvalidatesCache;

class DeviceLockObserver
{
  use InvalidatesCache;

  public function saving(DeviceLock $lock): void
  {
    $lock->guest_name = trim((string) $lock->guest_name);
    $lock->guest_name_slug = DeviceLock::normalizeSlug($lock->guest_name);

    if (!$lock->locked_at) {
      $lock->locked_at = now();
    }
  }

  public function saved(DeviceLock $lock): void
  {
    $this->forgetItem(EventKeys::tables($lock->event_id));
    $this->forgetItem(EventKeys::deviceLock($lock->device_id));
    $this->scanAndDelete(EventKeys::profilePattern($lock->event_id, $lock->id));

    if ($lock->wasChanged('guest_name') || $lock->wasChanged('avatar_path')) {
      $this->scanAndDelete(EventKeys::feedPattern($lock->event_id));
      $this->scanAndDelete(EventKeys::commentsPattern());
    }
  }

  public function deleted(DeviceLock $lock): void
  {
    $this->forgetItem(EventKeys::tables($lock->event_id));
    $this->forgetItem(EventKeys::deviceLock($lock->device_id));
    $this->scanAndDelete(EventKeys::profilePattern($lock->event_id, $lock->id));
    $this->scanAndDelete(EventKeys::feedPattern($lock->event_id));
    $this->scanAndDelete(EventKeys::commentsPattern());
  }
}
