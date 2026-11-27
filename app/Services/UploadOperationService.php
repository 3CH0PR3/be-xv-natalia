<?php

namespace App\Services;

use App\Actions\UploadOperation\AttachOperationPosterAction;
use App\Actions\UploadOperation\CreateUploadOperationAction;
use App\Actions\UploadOperation\DismissUploadOperationAction;
use App\Actions\UploadOperation\FindUploadOperationAction;
use App\Actions\UploadOperation\ListUploadOperationsAction;
use App\Actions\UploadOperation\MarkOperationFileFailedAction;
use App\Actions\UploadOperation\StoreOperationChunkAction;
use App\Actions\UploadOperation\UpdateOperationFileMetaAction;
use App\DTOs\UploadOperation\AttachOperationPosterData;
use App\DTOs\UploadOperation\CreateUploadOperationData;
use App\DTOs\UploadOperation\FailOperationFileData;
use App\DTOs\UploadOperation\StoreOperationChunkData;
use App\DTOs\UploadOperation\UpdateOperationFileMetaData;
use App\DTOs\UploadOperation\UploadOperationActorData;
use App\Models\Event;
use App\Models\Memory;
use App\Models\UploadOperation;
use App\Models\UploadOperationFile;
use App\Support\Storage\UploadOperationHelper;
use Illuminate\Database\Eloquent\Collection;

final readonly class UploadOperationService
{
  public const CHUNK_DISK = UploadOperationHelper::CHUNK_DISK;

  public function __construct(
    private UploadOperationHelper $helper,
    private ListUploadOperationsAction $listAction,
    private FindUploadOperationAction $findAction,
    private CreateUploadOperationAction $createAction,
    private StoreOperationChunkAction $storeChunkAction,
    private AttachOperationPosterAction $attachPosterAction,
    private UpdateOperationFileMetaAction $updateMetaAction,
    private MarkOperationFileFailedAction $markFailedAction,
    private DismissUploadOperationAction $dismissAction,
  ) {}

  public function listOperations(UploadOperationActorData $data): Collection
  {
    return $this->listAction->execute($data);
  }

  public function findOperationForActor(UploadOperationActorData $data): UploadOperation
  {
    return $this->findAction->execute($data);
  }

  public function createOperation(CreateUploadOperationData $data): UploadOperation
  {
    return $this->createAction->execute($data);
  }

  public function storeChunk(StoreOperationChunkData $data): UploadOperation
  {
    return $this->storeChunkAction->executeMultipart($data);
  }

  public function storeBinaryChunk(StoreOperationChunkData $data): array
  {
    return $this->storeChunkAction->executeStream($data, false);
  }

  public function attachPoster(AttachOperationPosterData $data): UploadOperation
  {
    return $this->attachPosterAction->execute($data);
  }

  public function updateFileMetadata(UpdateOperationFileMetaData $data): UploadOperation
  {
    return $this->updateMetaAction->execute($data);
  }

  public function markFileFailed(FailOperationFileData $data): UploadOperation
  {
    return $this->markFailedAction->execute($data);
  }

  public function markFileFailedById(int|string $fileId, string $errorCode, string $errorMessage): UploadOperation
  {
    return $this->markFailedAction->executeById($fileId, $errorCode, $errorMessage);
  }

  public function dismissOperation(UploadOperationActorData $data): void
  {
    $this->dismissAction->execute($data);
  }

  public function stagingPathFor(Event $event, UploadOperation $operation, UploadOperationFile $file): string
  {
    return $this->helper->stagingPathFor($event, $operation, $file);
  }

  public function finalPathFor(Event $event, UploadOperation $operation, UploadOperationFile $file): string
  {
    return $this->helper->finalPathFor($event, $operation, $file);
  }

  public function recalculateOperation(UploadOperation $operation): UploadOperation
  {
    return $this->helper->recalculateOperation($operation);
  }

  public function broadcastOperation(UploadOperation $operation, bool $toOthers = false, ?UploadOperationFile $changedFile = null): void
  {
    $this->helper->broadcastOperation($operation, $toOthers, $changedFile);
  }

  public function clearRedisChunkKeys(int|string $fileId): void
  {
    $this->helper->clearRedisChunkKeys($fileId);
  }

  public function invalidateMemoryCaches(Memory $memory): void
  {
    $this->helper->invalidateMemoryCaches($memory);
  }

  public function broadcastMemoryChange(Memory $memory, ?string $requestOrigin, bool $isCreated): void
  {
    $this->helper->broadcastMemoryChange($memory, $requestOrigin, $isCreated);
  }
}
