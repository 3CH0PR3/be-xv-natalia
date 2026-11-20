<?php

namespace App\Actions\Memory;

use App\DTOs\Memory\MemoryFilterData;
use App\DTOs\Shared\ViewerContextData;
use App\Models\Event;
use App\Models\Memory;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ListMemoriesAction
{
  public function execute(MemoryFilterData $filter, ViewerContextData $viewer): LengthAwarePaginator
  {
    $event = Event::byCode($filter->eventCode)->firstOrFail();

    return Memory::forEvent($event->id)
      ->withWallRelations($viewer->actorKey, $viewer->deviceId)
      ->filterStatus($filter->status, $filter->includeAllStatuses)
      ->when($filter->ownerDeviceId, fn ($q) => $q->ownedBy($filter->ownerDeviceId))
      ->filterCategory($filter->category)
      ->search($filter->search)
      ->newestFirst()
      ->paginate($filter->perPage);
  }
}
