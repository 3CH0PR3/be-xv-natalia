<?php

namespace App\Services;

use App\Actions\Event\GetEventStateAction;
use App\Actions\Event\ResolveEventAction;
use App\Actions\Event\TogglePartyCelebrateAction;
use App\DTOs\Event\TogglePartyCelebrateData;
use App\DTOs\Shared\ViewerContextData;
use App\Models\Event;

class EventStateService
{
  public function __construct(
    private readonly GetEventStateAction $getEventState,
    private readonly TogglePartyCelebrateAction $toggleCelebrate,
    private readonly ResolveEventAction $resolveEvent,
  ) {}

  public function getEventState(ViewerContextData $viewer): array
  {
    return $this->getEventState->execute($viewer);
  }

  public function toggleCelebration(TogglePartyCelebrateData $data): array
  {
    return $this->toggleCelebrate->execute($data);
  }

  public function requireEvent(string $eventCode): Event
  {
    return $this->resolveEvent->execute($eventCode);
  }
}
