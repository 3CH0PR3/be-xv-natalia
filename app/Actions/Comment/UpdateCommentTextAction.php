<?php

namespace App\Actions\Comment;

use App\DTOs\Comment\UpdateCommentData;
use App\DTOs\Shared\ViewerContextData;
use App\Events\CommentUpdated;
use App\Exceptions\DomainRuleException;
use App\Http\Resources\Api\V1\MemoryCommentResource;
use App\Models\Memory;
use App\Models\MemoryComment;
use App\Support\Cache\Keys\EventKeys;
use App\Traits\Cache\InvalidatesCache;
use Illuminate\Support\Facades\DB;

class UpdateCommentTextAction
{
  use InvalidatesCache;

  public function execute(UpdateCommentData $data, ViewerContextData $viewer): MemoryComment
  {
    return DB::transaction(function () use ($data, $viewer): MemoryComment {
      $memory = Memory::with('event')->where('external_id', $data->memoryExternalId)->firstOrFail();
      $comment = MemoryComment::with('deviceLock')->where('memory_id', $memory->id)
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

      $comment->update([
        'text' => $data->text,
        'edited_at' => now(),
      ]);
      $fresh = $comment->fresh(['deviceLock:id,guest_name,avatar_path,cover_path,actor_key,device_id,updated_at', 'commentLikes']);

      $this->forgetItem(EventKeys::memoryComments($memory->id));
      $this->forgetItem(EventKeys::memory($memory->external_id));

      DB::afterCommit(static function () use ($memory, $fresh): void {
        $payload = (new MemoryCommentResource($fresh))->resolve();
        unset($payload['likedByMe']);
        broadcast(new CommentUpdated($memory->event->code, $memory->external_id, $payload))->toOthers();
      });

      return $fresh;
    });
  }
}
