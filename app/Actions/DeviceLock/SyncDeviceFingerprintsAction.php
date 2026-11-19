<?php

namespace App\Actions\DeviceLock;

use App\Models\DeviceFingerprintIndex;
use App\Models\DeviceLock;

class SyncDeviceFingerprintsAction
{
  /** @param  array<int, string>  $candidates */
  public function execute(DeviceLock $lock, array $candidates): void
  {
    $clean = collect(array_merge($candidates, $lock->fingerprint_candidates ?? []))
      ->filter(static fn ($fp): bool => is_string($fp) && strlen($fp) >= 10 && (
        str_starts_with($fp, 'fphw') ||
        str_starts_with($fp, 'v2_') ||
        str_starts_with($fp, 'dev_')
      ))
      ->unique()
      ->take(25)
      ->values();

    $lock->update(['fingerprint_candidates' => $clean->all()]);

    foreach ($clean as $fp) {
      DeviceFingerprintIndex::updateOrCreate(
        ['event_id' => $lock->event_id, 'fingerprint' => $fp],
        ['device_lock_id' => $lock->id]
      );
    }
  }
}
