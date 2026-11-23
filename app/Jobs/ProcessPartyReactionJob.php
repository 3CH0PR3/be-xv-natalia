<?php

namespace App\Jobs;

use App\Events\PartyCelebrated;
use App\Models\Event;
use App\Models\PartyReaction;
use App\Support\Cache\Keys\EventKeys;
use App\Traits\Cache\InvalidatesCache;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ProcessPartyReactionJob implements ShouldQueue
{
  use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, InvalidatesCache;

  public int $tries = 3;
  public int $timeout = 30;

  public function __construct(
    public int $eventId,
    public ?string $actorKey = null,
    public ?string $deviceId = null,
    public ?int $count = null
  ) {
    $this->queue = 'default';
  }

  public function handle(): void
  {
    $event = Event::find($this->eventId);
    if (!$event) {
      return;
    }

    $nextCount = $this->resolveCountAndSyncDb($event);

    $this->forgetItem(EventKeys::eventState($event->code));

    $eventCode = $event->code ?? config('xv.default_event_code', 'XV-NATALIA');

    broadcast(new PartyCelebrated($eventCode, $nextCount));
  }

  public function failed(\Throwable $exception): void
  {
    Log::error("ProcessPartyReactionJob failed for event #{$this->eventId}: " . $exception->getMessage(), [
      'exception' => $exception,
    ]);
  }

  private function resolveCountAndSyncDb(Event $event): int
  {
    if ($this->count !== null) {
      return $this->count;
    }

    if ($this->actorKey === null) {
      $count = PartyReaction::where('event_id', $event->id)->count();
      $event->update(['party_celebrations_count' => $count]);
      return $count;
    }

    return $this->toggleReactionInDb($event);
  }

  private function toggleReactionInDb(Event $event): int
  {
    $existing = PartyReaction::where('event_id', $event->id)
      ->where('actor_key', $this->actorKey)
      ->first();

    if ($existing) {
      $existing->delete();
      $nextCount = max(0, $event->party_celebrations_count - 1);
      $event->update(['party_celebrations_count' => $nextCount]);
      return $nextCount;
    }

    PartyReaction::create([
      'event_id' => $event->id,
      'actor_key' => $this->actorKey,
      'device_id' => $this->deviceId,
    ]);
    $nextCount = $event->party_celebrations_count + 1;
    $event->update(['party_celebrations_count' => $nextCount]);

    return $nextCount;
  }
}
