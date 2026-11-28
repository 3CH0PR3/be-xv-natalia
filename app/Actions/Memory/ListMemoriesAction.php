<?php

namespace App\Actions\Memory;

use App\DTOs\Memory\MemoryFilterData;
use App\DTOs\Shared\ViewerContextData;
use App\Models\Event;
use App\Models\Memory;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

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

    Log::info('WALL_FEED_CURSOR', [
      'cursor'       => $filter->cursor ?? 'null (primera pagina)',
      'per_page'     => $filter->perPage,
      'scope'        => $filter->ownerDeviceId ? 'mis-fotos' : 'muro',
      'event_id'     => $event->id,
      'can_cache'    => $canCache,
      'search'       => $filter->search,
      'status'       => $filter->status,
    ]);

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
      ->newestFirst()
      ->cursorPaginate(
        $filter->perPage,
        ['memories.*'],
        'cursor',
        $filter->cursor ? \Illuminate\Pagination\Cursor::fromEncoded($filter->cursor) : null
      );

    $items       = $paginator->items();
    $nextCursor  = $paginator->nextCursor()?->encode();
    $firstId     = !empty($items) ? $items[0]->id : null;
    $lastId      = !empty($items) ? $items[count($items) - 1]->id : null;

    Log::info('WALL_FEED_CURSOR_RESULT', [
      'total_returned' => count($items),
      'has_more'       => $paginator->hasMorePages(),
      'next_cursor'    => $nextCursor ?? 'null (no hay mas paginas)',
      'first_id'       => $firstId,
      'last_id'        => $lastId,
      'cursor_in'      => $filter->cursor ?? 'null',
    ]);

    return $paginator;
  }
}
