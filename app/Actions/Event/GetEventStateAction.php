<?php

namespace App\Actions\Event;

use App\DTOs\Shared\ViewerContextData;
use App\Models\Event;
use App\Support\Cache\Keys\EventKeys;
use App\Traits\Cache\CacheableCache;

class GetEventStateAction
{
  use CacheableCache;

  public function __construct(
    private readonly ResolveEventAction $resolveEvent,
  ) {}

  /**
   * @return array{event: Event, reactedByMe: bool}
   */
  public function execute(ViewerContextData $viewer): array
  {
    $event = $this->remember(
      EventKeys::eventState($viewer->eventCode),
      300,
      fn () => $this->resolveEvent->execute($viewer->eventCode)
    );

    if ($event instanceof Event) {
      $event->setConnection(config('database.default'));
    }

    $reactedByMe = $event->hasPartyReactionBy($viewer->actorKey, $viewer->deviceId);

    return [
      'event' => $event,
      'reactedByMe' => $reactedByMe,
    ];
  }
}
