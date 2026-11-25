<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

class UploadOperationUpdated implements ShouldBroadcastNow
{
  use Dispatchable, InteractsWithSockets;

  public function __construct(
    public readonly string $eventCode,
    public readonly array $operation,
    public readonly string $ownerDeviceId = '',
    public readonly string $ownerActorKey = '',
  ) {}

  public function broadcastOn(): array
  {
    return [new Channel("event.{$this->eventCode}")];
  }

  public function broadcastAs(): string
  {
    return 'upload.operation.updated';
  }
}
