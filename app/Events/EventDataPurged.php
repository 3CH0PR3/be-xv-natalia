<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

class EventDataPurged implements ShouldBroadcastNow
{
  use Dispatchable, InteractsWithSockets;

  public function __construct(
    public readonly string $eventCode,
    public readonly string $dbScope,
    public readonly int $epoch,
  ) {}

  public function broadcastOn(): array
  {
    return [new Channel("event.{$this->eventCode}")];
  }

  public function broadcastAs(): string
  {
    return 'event.purged';
  }

  public function broadcastWith(): array
  {
    return [
      'eventCode' => $this->eventCode,
      'dbScope' => $this->dbScope,
      'epoch' => $this->epoch,
      'purged' => true,
    ];
  }
}
