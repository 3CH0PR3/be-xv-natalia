<?php

namespace App\Actions\Table;

use App\Models\Event;
use App\Models\EventTable;
use App\Support\Cache\Keys\EventKeys;
use App\Traits\Cache\CacheableCache;
use Illuminate\Support\Collection;

class ListTablesAction
{
  use CacheableCache;

  public function execute(string $eventCode): Collection
  {
    $event = Event::byCode($eventCode)->firstOrFail();

    return $this->remember(
      EventKeys::tables($event->id),
      60,
      fn () => EventTable::with('deviceLocks')
        ->forEvent($event->id)
        ->ordered()
        ->get()
    );
  }
}
