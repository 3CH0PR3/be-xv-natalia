<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

class AppReleasePublished implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(
        public readonly string $eventCode,
        public readonly array $release,
    ) {}

    public function broadcastOn(): array
    {
        return [new Channel("event.{$this->eventCode}")];
    }

    public function broadcastAs(): string
    {
        return 'release.published';
    }

    public function broadcastWith(): array
    {
        return [
            'release' => $this->release,
        ];
    }
}
