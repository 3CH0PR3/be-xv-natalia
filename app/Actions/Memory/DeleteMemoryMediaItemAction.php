<?php

namespace App\Actions\Memory;

use App\DTOs\Shared\ViewerContextData;
use App\Events\MemoryDeleted;
use App\Events\MemoryUpdated;
use App\Exceptions\DomainRuleException;
use App\Http\Resources\Api\V1\MemoryResource;
use App\Models\Memory;
use Illuminate\Support\Facades\DB;

class DeleteMemoryMediaItemAction
{
  /** @return array{deletedParent: bool, memory: ?Memory} */
  public function execute(string $externalId, string $mediaItemId, ViewerContextData $viewer): array
  {
    return DB::transaction(function () use ($externalId, $mediaItemId, $viewer): array {
      $memory = Memory::with(['event', 'mediaItems'])->where('external_id', $externalId)->lockForUpdate()->first();

      if (!$memory) {
        return ['deletedParent' => true, 'memory' => null];
      }

      if (!$viewer->isHost() && !$memory->isOwnedBy($viewer->deviceId, $viewer->actorKey)) {
        throw new DomainRuleException(
          'FORBIDDEN_ALBUM_EDIT',
          'No autorizado para editar este álbum.',
          403
        );
      }

      $targetItem = $memory->mediaItems->firstWhere('external_id', $mediaItemId);
      if (!$targetItem) {
        return ['deletedParent' => false, 'memory' => $memory];
      }

      $eventCode = $memory->event->code;
      $targetItem->delete(); // Observer deletes storage file upon commit

      $remaining = $memory->mediaItems()->orderBy('sort_order')->get();
      if ($remaining->isEmpty()) {
        $memory->delete();
        DB::afterCommit(static function () use ($eventCode, $externalId): void {
          broadcast(new MemoryDeleted($eventCode, $externalId))->toOthers();
        });

        return ['deletedParent' => true, 'memory' => null];
      }

      $memory->update(['media_type' => $remaining->first()->media_type]);
      $fresh = $memory->fresh(['mediaItems', 'reactions', 'comments.commentLikes']);

      DB::afterCommit(static function () use ($eventCode, $fresh): void {
        $payload = (new MemoryResource($fresh))->resolve();
        broadcast(new MemoryUpdated($eventCode, $payload))->toOthers();
      });

      return ['deletedParent' => false, 'memory' => $fresh];
    });
  }
}
