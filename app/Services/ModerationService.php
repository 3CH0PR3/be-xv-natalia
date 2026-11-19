<?php

namespace App\Services;

use App\Actions\DeviceLock\ExpelGuestAction;
use App\Actions\Memory\UpdateMemoryStatusAction;
use App\Actions\Moderation\BuildModerationMetricsAction;
use App\Actions\Moderation\ExportApprovedAlbumZipAction;
use App\Actions\Moderation\PurgeEventDataAction;
use App\DTOs\DeviceLock\ExpelGuestData;
use App\Models\Event;
use App\Models\Memory;
use App\Models\User;
use Illuminate\Support\Collection;

class ModerationService
{
  public function __construct(
    private readonly BuildModerationMetricsAction $buildMetrics,
    private readonly UpdateMemoryStatusAction $updateStatus,
    private readonly ExpelGuestAction $expelGuest,
    private readonly ExportApprovedAlbumZipAction $exportZip,
    private readonly PurgeEventDataAction $purgeEvent,
  ) {}

  public function verifyHostPin(string $pin): bool
  {
    return User::verifyHostPin($pin);
  }

  public function getMetrics(string $eventCode): array
  {
    $event = Event::byCode($eventCode)->firstOrFail();
    return $this->buildMetrics->execute($event);
  }

  public function updateMemoryStatus(string $externalId, string $status): Memory
  {
    return $this->updateStatus->execute($externalId, $status);
  }

  public function expelGuest(ExpelGuestData $data): Collection
  {
    $event = Event::byCode($data->eventCode)->firstOrFail();
    return $this->expelGuest->execute($event, $data);
  }

  /** @return array{path: string, filename: string} */
  public function buildAlbumZip(string $eventCode): array
  {
    $event = Event::byCode($eventCode)->firstOrFail();
    return [
      'path' => $this->exportZip->execute($event),
      'filename' => "{$event->code}-album-hd.zip",
    ];
  }

  public function purgeEventData(string $eventCode): array
  {
    $event = Event::byCode($eventCode)->firstOrFail();
    return $this->purgeEvent->execute($event);
  }
}
