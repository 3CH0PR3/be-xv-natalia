<?php

namespace App\Actions\Event;

use App\DTOs\Event\TogglePartyCelebrateData;
use App\Jobs\ProcessPartyReactionJob;
use App\Models\Event;
use App\Models\PartyReaction;
use App\Support\Cache\Keys\EventKeys;
use App\Traits\Cache\InvalidatesCache;
use Illuminate\Support\Facades\DB;

class TogglePartyCelebrateAction
{
  use InvalidatesCache;

  public function __construct(
    private readonly ResolveEventAction $resolveEvent,
  ) {}

  public function execute(TogglePartyCelebrateData $data): array
  {
    $event = $this->resolveEvent->execute($data->eventCode);

    return DB::transaction(function () use ($event, $data): array {
      $lockedEvent = Event::whereKey($event->id)->lockForUpdate()->firstOrFail();

      $existing = PartyReaction::where('event_id', $lockedEvent->id)
        ->where('actor_key', $data->actorKey)
        ->first();

      $reacted = false;
      if ($existing) {
        $existing->delete();
        $nextCount = max(0, $lockedEvent->party_celebrations_count - 1);
      }
      if (!$existing) {
        PartyReaction::create([
          'event_id' => $lockedEvent->id,
          'actor_key' => $data->actorKey,
          'device_id' => $data->deviceId,
        ]);
        $nextCount = $lockedEvent->party_celebrations_count + 1;
        $reacted = true;
      }

      $lockedEvent->update(['party_celebrations_count' => $nextCount]);

      $this->forgetItem(EventKeys::eventState($lockedEvent->code));

      DB::afterCommit(static function () use ($lockedEvent, $nextCount, $data): void {
        ProcessPartyReactionJob::dispatch(
          $lockedEvent->id,
          $data->actorKey,
          $data->deviceId,
          $nextCount
        );
      });

      return [
        'count' => $nextCount,
        'reacted' => $reacted,
        'reactedByMe' => $reacted,
        'eventCode' => $lockedEvent->code,
      ];
    });
  }
}
