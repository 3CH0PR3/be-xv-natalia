<?php

namespace App\Actions\Memory;

use App\DTOs\Shared\ViewerContextData;
use App\Events\MemoryDeleted;
use App\Exceptions\DomainRuleException;
use App\Models\Memory;
use Illuminate\Support\Facades\DB;

class DeleteMemoryAction
{
  public function execute(string $externalId, ViewerContextData $viewer): array
  {
    return DB::transaction(function () use ($externalId, $viewer): array {
      $memory = Memory::with('event')->where('external_id', $externalId)->lockForUpdate()->first();

      if (!$memory) {
        return [
          'id' => $externalId,
          'externalId' => $externalId,
          'deleted' => true,
          'alreadyDeleted' => true,
          'deletedAt' => now()->toIso8601String(),
        ];
      }

      if (!$viewer->isHost() && !$memory->isOwnedBy($viewer->deviceId, $viewer->actorKey)) {
        throw new DomainRuleException(
          'FORBIDDEN_MEMORY_DELETE',
          'No autorizado para eliminar este recuerdo.',
          403
        );
      }

      $eventCode = $memory->event->code;
      $deletedData = [
        'id' => $memory->external_id,
        'externalId' => $memory->external_id,
        'caption' => $memory->caption,
        'authorName' => $memory->author_name,
        'tableCode' => $memory->table_code,
        'mediaItemsCount' => (int) $memory->media_items_count,
        'deleted' => true,
        'deletedAt' => now()->toIso8601String(),
      ];

      // MemoryObserver handles physical file deletion and Redis cache clearing
      $memory->delete();

      DB::afterCommit(static function () use ($eventCode, $externalId): void {
        broadcast(new MemoryDeleted($eventCode, $externalId))->toOthers();
      });

      return $deletedData;
    });
  }
}
