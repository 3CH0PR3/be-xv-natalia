<?php

namespace App\Actions\Memory;

use App\Events\MemoryUpdated;
use App\Http\Resources\Api\V1\MemoryResource;
use App\Models\Memory;
use Illuminate\Support\Facades\DB;

class UpdateMemoryStatusAction
{
  public function execute(string $externalId, string $status): Memory
  {
    return DB::transaction(function () use ($externalId, $status): Memory {
      $memory = Memory::with('event')->where('external_id', $externalId)->lockForUpdate()->firstOrFail();
      $memory->update(['status' => $status]);
      $fresh = $memory->fresh(['mediaItems', 'reactions', 'comments.commentLikes']);

      DB::afterCommit(static function () use ($memory, $fresh): void {
        $payload = (new MemoryResource($fresh))->resolve();
        broadcast(new MemoryUpdated($memory->event->code, $payload))->toOthers();
      });

      return $fresh;
    });
  }
}
