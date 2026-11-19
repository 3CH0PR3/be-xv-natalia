<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

class CommentLikeUpdated implements ShouldBroadcastNow
{
  use Dispatchable, InteractsWithSockets;

  public function __construct(
    public readonly string $eventCode,
    public readonly string $memoryId,
    public readonly string $commentId,
    public readonly int $likes,
    public readonly string $actorKey,
    public readonly bool $liked,
    public readonly ?string $deviceId = null,
  ) {}

  public function broadcastOn(): array
  {
    return [new Channel("event.{$this->eventCode}")];
  }

  public function broadcastAs(): string
  {
    return 'comment.liked';
  }
}
