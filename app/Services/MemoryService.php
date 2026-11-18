<?php

namespace App\Services;

use App\Actions\Memory\CreateMemoryAction;
use App\Actions\Memory\DeleteMemoryAction;
use App\Actions\Memory\DeleteMemoryMediaItemAction;
use App\Actions\Memory\IncrementMemoryShareAction;
use App\Actions\Memory\ToggleMemoryReactionAction;
use App\Actions\Memory\UpdateMemoryCaptionAction;
use App\DTOs\Memory\CreateMemoryData;
use App\DTOs\Memory\MemoryFilterData;
use App\DTOs\Memory\ToggleReactionData;
use App\DTOs\Memory\UpdateCaptionData;
use App\DTOs\Shared\ViewerContextData;
use App\Models\Event;
use App\Models\Memory;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class MemoryService
{
    public function __construct(
        private readonly CreateMemoryAction $createMemory,
        private readonly UpdateMemoryCaptionAction $updateCaption,
        private readonly DeleteMemoryAction $deleteMemory,
        private readonly DeleteMemoryMediaItemAction $deleteMediaItem,
        private readonly ToggleMemoryReactionAction $toggleReaction,
        private readonly IncrementMemoryShareAction $incrementShare,
    ) {}

    public function listMemories(MemoryFilterData $filter, ViewerContextData $viewer): LengthAwarePaginator
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

    public function getMemory(string $externalId, ViewerContextData $viewer): Memory
    {
        return Memory::withWallRelations($viewer->actorKey, $viewer->deviceId)
            ->where('external_id', $externalId)
            ->firstOrFail();
    }

    public function publishMemory(CreateMemoryData $data): Memory
    {
        $event = Event::byCode($data->eventCode)->firstOrFail();
        return $this->createMemory->execute($event, $data);
    }

    public function updateCaption(UpdateCaptionData $data, ViewerContextData $viewer): Memory
    {
        return $this->updateCaption->execute($data, $viewer);
    }

    public function deleteMemory(string $externalId, ViewerContextData $viewer): array
    {
        return $this->deleteMemory->execute($externalId, $viewer);
    }

    public function deleteMediaItem(string $externalId, string $mediaItemId, ViewerContextData $viewer): array
    {
        return $this->deleteMediaItem->execute($externalId, $mediaItemId, $viewer);
    }

    public function toggleReaction(ToggleReactionData $data): array
    {
        return $this->toggleReaction->execute($data);
    }

    public function incrementShare(string $externalId): Memory
    {
        return $this->incrementShare->execute($externalId);
    }
}
