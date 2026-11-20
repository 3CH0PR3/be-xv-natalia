<?php

namespace App\Actions\Comment;

use App\DTOs\Shared\ViewerContextData;
use App\Events\CommentDeleted;
use App\Exceptions\DomainRuleException;
use App\Models\Memory;
use App\Models\MemoryComment;
use Illuminate\Support\Facades\DB;

class DeleteCommentAction
{
  public function execute(string $memoryExternalId, string $commentExternalId, ViewerContextData $viewer): array
  {
    return DB::transaction(function () use ($memoryExternalId, $commentExternalId, $viewer): array {
      $memory = Memory::with('event')->where('external_id', $memoryExternalId)->lockForUpdate()->first();
      if (!$memory) {
        return [
          'id' => $commentExternalId,
          'externalId' => $commentExternalId,
          'memoryId' => $memoryExternalId,
          'deleted' => true,
          'alreadyDeleted' => true,
          'deletedAt' => now()->toIso8601String(),
        ];
      }

      $comment = MemoryComment::where('memory_id', $memory->id)
        ->where('external_id', $commentExternalId)
        ->lockForUpdate()
        ->first();

      if (!$comment) {
        return [
          'id' => $commentExternalId,
          'externalId' => $commentExternalId,
          'memoryId' => $memoryExternalId,
          'deleted' => true,
          'alreadyDeleted' => true,
          'deletedAt' => now()->toIso8601String(),
        ];
      }

      if (!$viewer->isHost() && !$comment->isOwnedBy($viewer->deviceId, $viewer->actorKey)) {
        throw new DomainRuleException(
          'FORBIDDEN_COMMENT_DELETE',
          'No autorizado para eliminar este comentario.',
          403
        );
      }

      $eventCode = $memory->event->code;
      $deletedData = [
        'id' => $comment->external_id,
        'externalId' => $comment->external_id,
        'memoryId' => $memory->external_id,
        'authorName' => $comment->author_name,
        'text' => $comment->text,
        'deleted' => true,
        'deletedAt' => now()->toIso8601String(),
      ];

      app(\App\Services\CommentLikeCounterService::class)->clear($comment->id);
      $comment->delete();
      $newCount = MemoryComment::where('memory_id', $memory->id)->count();

      DB::afterCommit(static function () use ($eventCode, $memoryExternalId, $commentExternalId, $newCount): void {
        broadcast(new CommentDeleted($eventCode, $memoryExternalId, $commentExternalId, $newCount))->toOthers();
      });

      return $deletedData;
    });
  }
}
