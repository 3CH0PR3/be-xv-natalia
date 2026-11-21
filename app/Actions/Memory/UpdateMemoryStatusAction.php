<?php

namespace App\Actions\Memory;

use App\DTOs\Moderation\UpdateMemoryStatusData;
use App\Events\MemoryUpdated;
use App\Http\Resources\Api\V1\MemoryResource;
use App\Models\Memory;
use Illuminate\Support\Facades\DB;

class UpdateMemoryStatusAction
{
  public function execute(UpdateMemoryStatusData|string $dtoOrExternalId, ?string $status = null): Memory
  {
    $externalId = $dtoOrExternalId instanceof UpdateMemoryStatusData
      ? $dtoOrExternalId->externalId
      : $dtoOrExternalId;

    $targetStatus = $dtoOrExternalId instanceof UpdateMemoryStatusData
      ? $dtoOrExternalId->status
      : (string) $status;

    return DB::transaction(function () use ($externalId, $targetStatus): Memory {
      $memory = Memory::with('event')->where('external_id', $externalId)->lockForUpdate()->firstOrFail();
      $memory->update(['status' => $targetStatus]);
      $fresh = $memory->fresh(['mediaItems', 'reactions', 'comments.commentLikes']);

      DB::afterCommit(static function () use ($memory, $fresh): void {
        $payload = (new MemoryResource($fresh))->resolve();
        broadcast(new MemoryUpdated($memory->event->code, $payload))->toOthers();
      });

      return $fresh;
    });
  }
}
