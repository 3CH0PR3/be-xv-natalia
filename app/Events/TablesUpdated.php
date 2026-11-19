<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

class TablesUpdated implements ShouldBroadcastNow
{
  use Dispatchable, InteractsWithSockets;

  public function __construct(
    public readonly string $eventCode,
    public readonly array $tables,
  ) {}

  public function broadcastOn(): array
  {
    return [new Channel("event.{$this->eventCode}")];
  }

  public function broadcastAs(): string
  {
    return 'tables.updated';
  }

  public function broadcastWith(): array
  {
    return [
      'eventCode' => $this->eventCode,
      'updatedAt' => now()->toIso8601String(),
      'tables' => array_map(static fn ($t) => [
        'id' => $t['id'] ?? '',
        'number' => $t['number'] ?? '',
        'code' => $t['code'] ?? '',
        'capacity' => $t['capacity'] ?? 10,
        'guestsConnected' => $t['guestsConnected'] ?? (isset($t['guests']) ? count($t['guests']) : 0),
        'qrImageUrl' => $t['qrImageUrl'] ?? ($t['qr_image_url'] ?? null),
        'qrSvgUrl' => $t['qrSvgUrl'] ?? ($t['qr_svg_url'] ?? null),
        'accessUrl' => $t['accessUrl'] ?? ($t['access_url'] ?? null),
      ], $this->tables),
    ];
  }
}
