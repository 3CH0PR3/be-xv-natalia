<?php

namespace App\Actions\Memory;

use App\DTOs\Memory\MemoryFilterData;
use App\DTOs\Shared\ViewerContextData;
use App\Models\Event;
use App\Models\Memory;
use Illuminate\Contracts\Pagination\CursorPaginator;


class ListMemoriesAction
{
  private const FIRST_PAGE_TTL = 30; // segundos

  public function execute(MemoryFilterData $filter, ViewerContextData $viewer): CursorPaginator
  {
    $event = Event::byCode($filter->eventCode)->firstOrFail();
    $isFirstPage = $filter->cursor === null;

    $canCache = $isFirstPage
      && !$filter->search
      && !$filter->ownerDeviceId
      && $filter->status === null
      && $filter->category === null
      && !$filter->includeAllStatuses;

    // IMPORTANTE: NO cachear el CursorPaginator en Redis —
    // el objeto contiene closures que no se pueden serializar correctamente.
    // El caché de Redis en versiones anteriores devolvía un paginator corrupto
    // con nextCursor()=null aunque hubiera más páginas, rompiendo el scroll infinito.
    // Quitamos el remember() y dejamos que la query corra siempre.
    return $this->query($event->id, $filter, $viewer);
  }

  private function query(int|string $eventId, MemoryFilterData $filter, ViewerContextData $viewer): CursorPaginator
  {
    $paginator = Memory::forEvent($eventId)
      ->withWallRelations($viewer->actorKey, $viewer->deviceId)
      ->filterStatus($filter->status, $filter->includeAllStatuses)
      ->when($filter->ownerDeviceId, fn ($q) => $q->ownedBy($filter->ownerDeviceId))
      ->filterCategory($filter->category)
      ->search($filter->search)
      ->mostEngaged()
      ->cursorPaginate(
        $filter->perPage,
        ['memories.*'],
        'cursor',
        $filter->cursor ? \Illuminate\Pagination\Cursor::fromEncoded($filter->cursor) : null
      );

    return $paginator;
  }
}
