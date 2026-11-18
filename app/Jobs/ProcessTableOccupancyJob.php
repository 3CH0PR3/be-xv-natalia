<?php

namespace App\Jobs;

use App\Events\TablesUpdated;
use App\Http\Resources\Api\V1\EventTableResource;
use App\Models\Event;
use App\Models\EventTable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessTableOccupancyJob implements ShouldQueue
{
  use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

  public int $tries = 3;
  public int $timeout = 30;

  public function __construct(
    public int $eventId,
    public ?array $tablesPayload = null
  ) {
    $this->queue = 'default';
  }

  public function handle(): void
  {
    $event = Event::find($this->eventId);
    if (!$event) {
      return;
    }

    $payload = $this->tablesPayload;
    if ($payload === null) {
      $tables = EventTable::with('deviceLocks')->forEvent($event->id)->ordered()->get();
      $payload = EventTableResource::collection($tables)->resolve();
    }

    $eventCode = $event->code ?? config('xv.default_event_code', 'XV-NATALIA');

    broadcast(new TablesUpdated($eventCode, $payload));
  }

  public function failed(\Throwable $exception): void
  {
    Log::error("ProcessTableOccupancyJob failed for event #{$this->eventId}: " . $exception->getMessage(), [
      'exception' => $exception,
    ]);
  }
}
