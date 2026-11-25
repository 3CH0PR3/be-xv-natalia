<?php

namespace App\Actions\UploadOperation;

use App\DTOs\UploadOperation\UploadOperationActorData;
use App\Support\Storage\UploadOperationHelper;

final readonly class DismissUploadOperationAction
{
  public function __construct(
    private UploadOperationHelper $helper,
  ) {}

  public function execute(UploadOperationActorData $data): void
  {
    $event = $this->helper->resolveEvent($data->eventCode);
    $operation = $this->helper->findOperationForActor(
      operationKey: (string) $data->operationKey,
      event: $event,
      deviceId: $data->deviceId,
      actorKey: $data->actorKey,
      hostPin: $data->hostPin,
    );
    $operation->update(['dismissed_at' => now()]);
  }
}
