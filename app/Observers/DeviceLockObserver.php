<?php

namespace App\Observers;

use App\Models\DeviceLock;
use App\Traits\Cache\InvalidatesCache;
use Illuminate\Support\Facades\Cache;

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
    Cache::store('redis')->forget("tables:event:{$lock->event_id}");
    Cache::store('redis')->forget("device:lock:{$lock->device_id}");
    $this->scanAndDelete("profile:data:{$lock->event_id}:{$lock->id}:*");

    if ($lock->wasChanged('guest_name')) {
      $this->scanAndDelete("memories:feed:event:{$lock->event_id}:*");
      $this->scanAndDelete("memory:comments:*");
    }
  }

  public function deleted(DeviceLock $lock): void
  {
    Cache::store('redis')->forget("tables:event:{$lock->event_id}");
    Cache::store('redis')->forget("device:lock:{$lock->device_id}");
    $this->scanAndDelete("profile:data:{$lock->event_id}:{$lock->id}:*");
    $this->scanAndDelete("memories:feed:event:{$lock->event_id}:*");
    $this->scanAndDelete("memory:comments:*");
  }
}
