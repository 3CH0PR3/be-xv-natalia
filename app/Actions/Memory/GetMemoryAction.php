<?php

namespace App\Actions\Memory;

use App\DTOs\Shared\ViewerContextData;
use App\Models\Memory;

class GetMemoryAction
{
  public function execute(string $externalId, ViewerContextData $viewer): Memory
  {
    return Memory::withWallRelations($viewer->actorKey, $viewer->deviceId)
      ->where('external_id', $externalId)
      ->firstOrFail();
  }
}
