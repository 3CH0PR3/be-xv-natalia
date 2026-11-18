<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EventStateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $event = $this['event'];
        $reactedByMe = (bool) ($this['reactedByMe'] ?? false);

        return [
            'eventCode' => $event->code,
            'honoreeName' => $event->honoree_name,
            'subtitle' => $event->subtitle,
            'venue' => $event->venue,
            'dbScope' => $event->db_scope ?? 'laravel-v1',
            'epoch' => (int) ($event->epoch ?? 1),
            'partyReaction' => [
                'count' => $event->party_celebrations_count,
                'reacted' => $reactedByMe,
                'reactedByMe' => $reactedByMe,
            ],
        ];
    }
}
