<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

class GuestCheckedIn implements ShouldBroadcastNow
{
  use Dispatchable, InteractsWithSockets;

  public function __construct(
    public readonly string $eventCode,
    public readonly string $guestName,
    public readonly string $tableCode,
    public readonly string $actorKey,
  ) {}

  public function broadcastOn(): array
  {
    return [new Channel("event.{$this->eventCode}")];
  }

  public function broadcastAs(): string
  {
    return 'guest.checked_in';
  }
}
