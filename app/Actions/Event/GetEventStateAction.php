<?php

namespace App\Actions\Event;

use App\DTOs\Shared\ViewerContextData;
use App\Models\Event;
use Illuminate\Support\Facades\Cache;

class GetEventStateAction
{
  public function __construct(
    private readonly ResolveEventAction $resolveEvent,
  ) {}

  /**
   * @return array{event: Event, reactedByMe: bool}
   */
  public function execute(ViewerContextData $viewer): array
  {
    $event = Cache::store('redis')->remember(
      "event:state:{$viewer->eventCode}",
      300,
      fn () => $this->resolveEvent->execute($viewer->eventCode)
    );

    $reactedByMe = $event->hasPartyReactionBy($viewer->actorKey, $viewer->deviceId);

    return [
      'event' => $event,
      'reactedByMe' => $reactedByMe,
    ];
  }
}
