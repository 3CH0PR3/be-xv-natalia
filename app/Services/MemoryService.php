<?php

namespace App\Services;

use App\Actions\Memory\CreateMemoryAction;
use App\Actions\Memory\DeleteMemoryAction;
use App\Actions\Memory\DeleteMemoryMediaItemAction;
use App\Actions\Memory\GetMemoryAction;
use App\Actions\Memory\GetMemoryPreviewAction;
use App\Actions\Memory\IncrementMemoryShareAction;
use App\Actions\Memory\ListMemoriesAction;
use App\Actions\Memory\ToggleMemoryReactionAction;
use App\Actions\Memory\UpdateMemoryCaptionAction;
use App\DTOs\Memory\CreateMemoryData;
use App\DTOs\Memory\IncrementShareData;
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
    private readonly ListMemoriesAction $listMemories,
    private readonly GetMemoryAction $getMemory,
    private readonly GetMemoryPreviewAction $getMemoryPreview,
    private readonly CreateMemoryAction $createMemory,
    private readonly UpdateMemoryCaptionAction $updateCaption,
    private readonly DeleteMemoryAction $deleteMemory,
    private readonly DeleteMemoryMediaItemAction $deleteMediaItem,
    private readonly ToggleMemoryReactionAction $toggleReaction,
    private readonly IncrementMemoryShareAction $incrementShare,
  ) {}

  public function listMemories(MemoryFilterData $filter, ViewerContextData $viewer): LengthAwarePaginator
  {
    return $this->listMemories->execute($filter, $viewer);
  }

  public function getMemory(string $externalId, ViewerContextData $viewer): Memory
  {
    return $this->getMemory->execute($externalId, $viewer);
  }

  /**
   * @return array{external_id: string, title: string, description: string, image_url: string}
   */
  public function getMemoryPreview(string $externalId): array
  {
    return $this->getMemoryPreview->execute($externalId);
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

  public function incrementShare(
    IncrementShareData|string $data,
    ?string $actorKey = null,
    ?string $deviceId = null,
    ?int $deviceLockId = null
  ): Memory {
    $dto = $data instanceof IncrementShareData
      ? $data
      : new IncrementShareData($data, $actorKey, $deviceId, $deviceLockId);

    return $this->incrementShare->execute($dto);
  }
}
