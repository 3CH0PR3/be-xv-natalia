<?php

namespace App\Actions\Profile;

use App\DTOs\Profile\ListProfilesData;
use App\Models\DeviceLock;
use App\Models\Event;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator as ConcretePaginator;

final readonly class ListProfilesAction
{
  public function execute(ListProfilesData $data): LengthAwarePaginator
  {
    $eventCode = $data->eventCode ?: config('xv.default_event_code', 'XV-NATALIA');
    $event = Event::where('code', $eventCode)->first();
    if (!$event) {
      return new ConcretePaginator([], 0, $data->limit, 1);
    }

    $query = DeviceLock::where('event_id', $event->id)->with('table');
    $this->applySearch($query, $data->search);
    $this->applyOrdering($query, $data);

    return $query->paginate($data->limit, ['*'], 'page', $data->page);
  }

  private function applySearch(Builder $query, ?string $search): void
  {
    if ($search === null || trim($search) === '') {
      return;
    }

    $term = trim($search);
    $cleanSlug = DeviceLock::normalizeSlug($term);
    $query->where(function (Builder $q) use ($term, $cleanSlug): void {
      $q->where('guest_name', 'like', "%{$term}%")
        ->orWhere('guest_name_slug', 'like', "%{$cleanSlug}%")
        ->orWhere('id', $term);
      $q->orWhereHas('table', function (Builder $tq) use ($term): void {
        $tq->where('code', 'like', "%{$term}%")
          ->orWhere('number', 'like', "%{$term}%");
      });
    });
  }

  private function applyOrdering(Builder $query, ListProfilesData $data): void
  {
    if ($data->viewerActorKey || $data->viewerDeviceId) {
      $query->orderByRaw(
        'CASE WHEN (actor_key = ? OR device_id = ?) THEN 0 ELSE 1 END',
        [(string) $data->viewerActorKey, (string) $data->viewerDeviceId]
      );
    }

    $search = $data->search;
    $isTableContext = $search !== null && (
      stripos($search, 'mesa') !== false ||
      stripos($search, 'table') !== false ||
      stripos($search, 'principal') !== false
    );

    if ($isTableContext) {
      $query->orderBy('locked_at', 'asc')->orderBy('id', 'asc');
      return;
    }

    $query->orderByRaw("CASE WHEN avatar_path IS NOT NULL AND avatar_path != '' THEN 0 ELSE 1 END")
      ->orderByDesc('locked_at')
      ->orderByDesc('id');
  }
}
