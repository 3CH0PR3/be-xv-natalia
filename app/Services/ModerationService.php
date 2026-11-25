<?php

namespace App\Services;

use App\Actions\DeviceLock\ExpelGuestAction;
use App\Actions\Event\ResolveEventAction;
use App\Actions\Memory\UpdateMemoryStatusAction;
use App\Actions\Moderation\BuildModerationMetricsAction;
use App\Actions\Moderation\ChangeHostPinAction;
use App\Actions\Moderation\ExportApprovedAlbumZipAction;
use App\Actions\Moderation\PurgeEventDataAction;
use App\Actions\Moderation\VerifyHostPinAction;
use App\DTOs\DeviceLock\ExpelGuestData;
use App\DTOs\Moderation\ChangeHostPinData;
use App\DTOs\Moderation\UpdateMemoryStatusData;
use App\DTOs\Moderation\VerifyHostPinData;
use App\Models\Memory;
use Illuminate\Support\Collection;

final readonly class ModerationService
{
  public function __construct(
    private ResolveEventAction $resolveEvent,
    private VerifyHostPinAction $verifyPin,
    private ChangeHostPinAction $changePin,
    private BuildModerationMetricsAction $buildMetrics,
    private UpdateMemoryStatusAction $updateStatus,
    private ExpelGuestAction $expelGuest,
    private ExportApprovedAlbumZipAction $exportZip,
    private PurgeEventDataAction $purgeEvent,
  ) {}

  public function verifyHostPin(VerifyHostPinData $pin): bool
  {
    return $this->verifyPin->execute($pin);
  }

  public function changePin(ChangeHostPinData $data): bool
  {
    return $this->changePin->execute($data);
  }

  public function getMetrics(string $eventCode): array
  {
    $event = $this->resolveEvent->execute($eventCode);

    return $this->buildMetrics->execute($event);
  }

  public function updateMemoryStatus(UpdateMemoryStatusData $dto): Memory
  {
    return $this->updateStatus->execute($dto);
  }

  public function expelGuest(ExpelGuestData $data): Collection
  {
    $event = $this->resolveEvent->execute($data->eventCode);

    return $this->expelGuest->execute($event, $data);
  }

  /** @return array{path: string, filename: string} */
  public function buildAlbumZip(string $eventCode): array
  {
    $event = $this->resolveEvent->execute($eventCode);

    return [
      'path' => $this->exportZip->execute($event),
      'filename' => "{$event->code}-album-hd.zip",
    ];
  }

  public function purgeEventData(string $eventCode): array
  {
    $event = $this->resolveEvent->execute($eventCode);

    return $this->purgeEvent->execute($event);
  }
}
