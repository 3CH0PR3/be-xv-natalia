<?php

namespace App\Services;

use App\Actions\Event\ResolveEventAction;
use App\Actions\Event\TogglePartyCelebrateAction;
use App\DTOs\Event\TogglePartyCelebrateData;
use App\DTOs\Shared\ViewerContextData;
use App\Models\Event;
use Illuminate\Support\Facades\Cache;

class EventStateService
{
    public function __construct(
        private readonly ResolveEventAction $resolveEvent,
        private readonly TogglePartyCelebrateAction $toggleCelebrate,
    ) {}

    public function getEventState(ViewerContextData $viewer): array
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

    public function toggleCelebration(TogglePartyCelebrateData $data): array
    {
        return $this->toggleCelebrate->execute($data);
    }

    public function requireEvent(string $eventCode): Event
    {
        return Event::byCode($eventCode)->firstOrFail();
    }
}
