<?php

namespace App\Actions\Comment;

use App\DTOs\Comment\CreateCommentData;
use App\Events\CommentCreated;
use App\Http\Resources\Api\V1\MemoryCommentResource;
use App\Jobs\ProcessCommentJob;
use App\Models\Memory;
use App\Models\MemoryComment;
use Illuminate\Support\Facades\DB;

class CreateCommentAction
{
  public function execute(CreateCommentData $data): MemoryComment
  {
    return DB::transaction(function () use ($data): MemoryComment {
      $memory = Memory::with('event')->where('external_id', $data->memoryExternalId)->lockForUpdate()->firstOrFail();

      $comment = MemoryComment::create([
        'external_id' => $data->commentExternalId,
        'memory_id' => $memory->id,
        'author' => $data->author,
        'table_label' => $data->table,
        'text' => $data->text,
        'owner_device_id' => $data->ownerDeviceId,
        'owner_actor_key' => $data->ownerActorKey,
        'likes_count' => 0,
      ]);

      $fresh = $comment->fresh(['commentLikes']);
      $nextCount = (int) $memory->fresh()->comments_count;

      DB::afterCommit(static function () use ($comment): void {
        // Dispatch async job for notifications, caching & broadcasting
        ProcessCommentJob::dispatch($comment->id);
      });

      return $fresh;
    });
  }
}
