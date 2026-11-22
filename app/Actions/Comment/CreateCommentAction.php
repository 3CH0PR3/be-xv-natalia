<?php

namespace App\Actions\Comment;

use App\DTOs\Comment\CreateCommentData;
use App\Events\CommentCreated;
use App\Http\Resources\Api\V1\MemoryCommentResource;
use App\Jobs\ProcessCommentJob;
use App\Models\DeviceLock;
use App\Models\Memory;
use App\Models\MemoryComment;
use App\Services\CommentLikeCounterService;
use Illuminate\Support\Facades\DB;

class CreateCommentAction
{
  public function execute(CreateCommentData $data): MemoryComment
  {
    return DB::transaction(function () use ($data): MemoryComment {
      $memory = Memory::with('event')->where('external_id', $data->memoryExternalId)->lockForUpdate()->firstOrFail();

      $deviceLockId = $data->deviceLockId;
      $hasOwnerCredential = ($data->ownerActorKey || $data->ownerDeviceId);
      if (!$deviceLockId && $hasOwnerCredential) {
        $deviceLock = DeviceLock::forEvent($memory->event_id)
          ->where(function ($q) use ($data): void {
            if ($data->ownerActorKey) {
              $q->orWhere('actor_key', $data->ownerActorKey);
            }
            if ($data->ownerDeviceId) {
              $q->orWhere('device_id', $data->ownerDeviceId);
            }
          })
          ->first();
        $deviceLockId = $deviceLock?->id;
      }

      $comment = MemoryComment::create([
        'external_id' => $data->commentExternalId,
        'memory_id' => $memory->id,
        'device_lock_id' => $deviceLockId,
        'author' => $data->author,
        'table_label' => $data->table,
        'text' => $data->text,
        'owner_device_id' => $data->ownerDeviceId,
        'owner_actor_key' => $data->ownerActorKey,
        'likes_count' => 0,
      ]);

      app(CommentLikeCounterService::class)->set($comment->id, 0);

      $fresh = $comment->fresh(['deviceLock:id,guest_name,avatar_path,cover_path,actor_key,device_id,updated_at', 'commentLikes']);
      $nextCount = (int) $memory->fresh()->comments_count;

      DB::afterCommit(static function () use ($comment): void {
        // Dispatch async job for notifications, caching & broadcasting
        ProcessCommentJob::dispatch($comment->id);
      });

      return $fresh;
    });
  }
}
