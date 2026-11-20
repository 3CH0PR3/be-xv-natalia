<?php

namespace App\Actions\DeviceLock;

use App\DTOs\DeviceLock\ResolveDeviceLockData;
use App\Models\DeviceFingerprintIndex;
use App\Models\DeviceLock;
use App\Models\Event;

class ResolveDeviceLockAction
{
  public function __construct(
    private readonly SyncDeviceFingerprintsAction $syncFingerprints,
  ) {}

  /** @return array{lock: ?DeviceLock, restoredByFingerprint: bool} */
  public function execute(Event $event, ResolveDeviceLockData $data): array
  {
    $extraFps = array_filter([$data->deviceId, $data->hwFingerprint]);
    $candidates = array_values(array_unique(array_merge($data->candidateFingerprints, $extraFps)));

    [$lock, $restoredByFp] = $this->findExistingLock(
      $event,
      $data->deviceId,
      $candidates,
      $data->hardwareSummary
    );

    if (!$lock) {
      return ['lock' => null, 'restoredByFingerprint' => false];
    }

    $candidatesToSync = $data->deviceId !== '' && $data->deviceId !== $lock->device_id
      ? array_merge($candidates, [$data->deviceId])
      : $candidates;

    $this->syncFingerprints->execute($lock, $candidatesToSync);

    if (!empty($data->hardwareSummary)) {
      $lock->update(['hardware_summary' => $data->hardwareSummary]);
    }

    return [
      'lock' => $lock->fresh(['table']),
      'restoredByFingerprint' => $restoredByFp,
    ];
  }

  /** @return array{0: ?DeviceLock, 1: bool} */
  public function findExistingLock(
    Event $event,
    string $deviceId,
    array $candidates,
    ?array $hardwareSummary = null
  ): array {
    $byDevice = $deviceId !== ''
      ? DeviceLock::with('table')
        ->forEvent($event->id)
        ->byDeviceId($deviceId)
        ->first()
      : null;

    if ($byDevice) {
      return [$byDevice, false];
    }

    $fpMatch = !empty($candidates)
      ? DeviceFingerprintIndex::with('deviceLock.table')
        ->forCandidates($event->id, $candidates)
        ->first()
      : null;

    if ($fpMatch?->deviceLock) {
      return [$fpMatch->deviceLock, true];
    }

    $byHwFp = !empty($candidates)
      ? DeviceLock::with('table')
        ->forEvent($event->id)
        ->whereIn('hw_fingerprint', $candidates)
        ->first()
      : null;

    if ($byHwFp) {
      return [$byHwFp, true];
    }

    $metrics = trim((string) ($hardwareSummary['metrics'] ?? ''));
    $gpu = trim((string) ($hardwareSummary['gpu'] ?? ''));
    $hasHardwareCriteria = $metrics !== '' && $gpu !== '';

    $byHardware = $hasHardwareCriteria
      ? DeviceLock::with('table')
        ->forEvent($event->id)
        ->where('hardware_summary->metrics', $metrics)
        ->where('hardware_summary->gpu', $gpu)
        ->first()
      : null;

    if ($byHardware) {
      return [$byHardware, true];
    }

    $byResilient = $this->findByResilientHardware($event, $hardwareSummary);
    if ($byResilient) {
      return [$byResilient, true];
    }

    return [null, false];
  }

  private function findByResilientHardware(Event $event, ?array $summary): ?DeviceLock
  {
    if (empty($summary)) {
      return null;
    }

    $os = trim((string) ($summary['os'] ?? ''));
    $metrics = trim((string) ($summary['metrics'] ?? ''));
    if ($os === '' || $metrics === '') {
      return null;
    }

    $scrToken = preg_match('/scr:([^|]+)/', $metrics, $m) ? $m[1] : '';
    if ($scrToken === '') {
      return null;
    }

    return DeviceLock::with('table')
      ->forEvent($event->id)
      ->where('hardware_summary->os', $os)
      ->where('hardware_summary->metrics', 'like', "%scr:{$scrToken}%")
      ->latest('locked_at')
      ->first();
  }
}
