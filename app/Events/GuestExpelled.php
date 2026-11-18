<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

class GuestExpelled implements ShouldBroadcastNow
{
  use Dispatchable, InteractsWithSockets;

  public function __construct(
    public readonly string $deviceId,
    public readonly string $tableCode,
  ) {}

  public function broadcastOn(): array
  {
    return [new Channel("device.{$this->deviceId}")];
  }

  public function broadcastAs(): string
  {
    return 'guest.expelled';
  }
}
