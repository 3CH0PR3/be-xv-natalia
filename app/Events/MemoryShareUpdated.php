<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

class MemoryShareUpdated implements ShouldBroadcastNow
{
  use Dispatchable, InteractsWithSockets;

  public function __construct(
    public readonly string $eventCode,
    public readonly string $memoryId,
    public readonly int $shares,
  ) {}

  public function broadcastOn(): array
  {
    return [new Channel("event.{$this->eventCode}")];
  }

  public function broadcastAs(): string
  {
    return 'memory.share.updated';
  }

  public function broadcastWith(): array
  {
    return [
      'memoryId' => $this->memoryId,
      'shares' => $this->shares,
      'sharesCount' => $this->shares,
    ];
  }
}
