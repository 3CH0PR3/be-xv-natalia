<?php

namespace App\Actions\Table;

use App\Models\Event;
use App\Models\EventTable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class ListTablesAction
{
  public function execute(string $eventCode): Collection
  {
    $event = Event::byCode($eventCode)->firstOrFail();

    return Cache::store('redis')->remember(
      "tables:event:{$event->id}",
      60,
      fn () => EventTable::with('deviceLocks')
        ->forEvent($event->id)
        ->ordered()
        ->get()
    );
  }
}
