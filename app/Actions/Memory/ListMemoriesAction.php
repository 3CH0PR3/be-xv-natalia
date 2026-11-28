<?php

namespace App\Actions\Memory;

use App\DTOs\Memory\MemoryFilterData;
use App\DTOs\Shared\ViewerContextData;
use App\Models\Event;
use App\Models\Memory;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Support\Facades\Cache;

class ListMemoriesAction
{
  private const FIRST_PAGE_TTL = 30; // segundos — Redis cachea la primera página

  public function execute(MemoryFilterData $filter, ViewerContextData $viewer): CursorPaginator
  {
    $event = Event::byCode($filter->eventCode)->firstOrFail();
    $isFirstPage = $filter->cursor === null;

    // Cachear solo la primera página (sin cursor, sin búsqueda, sin filtros especiales)
    // en Redis para aligerar la carga cuando muchos invitados abren la app al mismo tiempo
    $canCache = $isFirstPage
      && !$filter->search
      && !$filter->ownerDeviceId
      && $filter->status === null
      && $filter->category === null
      && !$filter->includeAllStatuses;

    $cacheKey = "memories:feed:event:{$event->id}:{$filter->perPage}";

    if ($canCache) {
      return Cache::store('redis')->remember($cacheKey, self::FIRST_PAGE_TTL, function () use ($event, $filter, $viewer): CursorPaginator {
        return $this->query($event->id, $filter, $viewer);
      });
    }

    return $this->query($event->id, $filter, $viewer);
  }

  private function query(int|string $eventId, MemoryFilterData $filter, ViewerContextData $viewer): CursorPaginator
  {
    return Memory::forEvent($eventId)
      ->withWallRelations($viewer->actorKey, $viewer->deviceId)
      ->filterStatus($filter->status, $filter->includeAllStatuses)
      ->when($filter->ownerDeviceId, fn ($q) => $q->ownedBy($filter->ownerDeviceId))
      ->filterCategory($filter->category)
      ->search($filter->search)
      ->newestFirst()
      ->cursorPaginate(
        $filter->perPage,
        ['memories.*'],
        'cursor',
        $filter->cursor ? \Illuminate\Pagination\Cursor::fromEncoded($filter->cursor) : null
      );
  }
}
