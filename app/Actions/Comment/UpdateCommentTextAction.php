<?php

namespace App\Actions\Comment;

use App\DTOs\Comment\UpdateCommentData;
use App\DTOs\Shared\ViewerContextData;
use App\Events\CommentUpdated;
use App\Exceptions\DomainRuleException;
use App\Http\Resources\Api\V1\MemoryCommentResource;
use App\Models\Memory;
use App\Models\MemoryComment;
use Illuminate\Support\Facades\DB;

class UpdateCommentTextAction
{
  public function execute(UpdateCommentData $data, ViewerContextData $viewer): MemoryComment
  {
    return DB::transaction(function () use ($data, $viewer): MemoryComment {
      $memory = Memory::with('event')->where('external_id', $data->memoryExternalId)->firstOrFail();
      $comment = MemoryComment::where('memory_id', $memory->id)
        ->where('external_id', $data->commentExternalId)
        ->lockForUpdate()
        ->firstOrFail();

      if (!$viewer->isHost() && !$comment->isOwnedBy($viewer->deviceId, $viewer->actorKey)) {
        throw new DomainRuleException(
          'FORBIDDEN_COMMENT_EDIT',
          'Solo el autor original puede editar este comentario.',
          403
        );
      }

      $comment->update(['text' => $data->text]);
      $fresh = $comment->fresh(['commentLikes']);

      DB::afterCommit(static function () use ($memory, $fresh): void {
        $payload = (new MemoryCommentResource($fresh))->resolve();
        broadcast(new CommentUpdated($memory->event->code, $memory->external_id, $payload))->toOthers();
      });

      return $fresh;
    });
  }
}
