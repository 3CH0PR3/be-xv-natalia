<?php

namespace App\Actions\UploadOperation;

use App\DTOs\UploadOperation\UploadOperationActorData;
use App\Models\UploadOperation;
use App\Support\Storage\UploadOperationHelper;

final readonly class FindUploadOperationAction
{
  public function __construct(
    private UploadOperationHelper $helper,
  ) {}

  public function execute(UploadOperationActorData $data): UploadOperation
  {
    $event = $this->helper->resolveEvent($data->eventCode);

    return $this->helper->findOperationForActor(
      operationKey: (string) $data->operationKey,
      event: $event,
      deviceId: $data->deviceId,
      actorKey: $data->actorKey,
      hostPin: $data->hostPin,
    );
  }
}
