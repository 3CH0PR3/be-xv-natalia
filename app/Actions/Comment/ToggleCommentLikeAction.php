<?php

namespace App\Actions\Comment;

use App\DTOs\Comment\ToggleCommentLikeData;
use App\Events\CommentLikeUpdated;
use App\Jobs\ProcessCommentLikeJob;
use App\Models\Memory;
use App\Models\MemoryComment;
use App\Models\MemoryCommentLike;
use App\Services\CommentLikeCounterService;
use App\Support\Cache\Keys\EventKeys;
use App\Traits\Cache\InvalidatesCache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ToggleCommentLikeAction
{
  use InvalidatesCache;
  public function __construct(
    private readonly CommentLikeCounterService $counterService,
  ) {}

  public function execute(ToggleCommentLikeData $data): array
  {
    return DB::transaction(function () use ($data): array {
      $memory = Memory::with('event')->where('external_id', $data->memoryExternalId)->firstOrFail();
      $comment = MemoryComment::where('memory_id', $memory->id)
        ->where('external_id', $data->commentExternalId)
        ->firstOrFail();

      $existing = $this->findExistingLike($comment->id, $data->actorKey, $data->deviceId);
      $targetLiked = $data->liked !== null ? $data->liked : !$existing;

      $likesCount = $this->applyLikeMutation($comment, $existing, $targetLiked, $data);

      $this->forgetItem(EventKeys::memoryComments($memory->id));

      $eventCode = $memory->event?->code ?? config('xv.default_event_code', 'XV-NATALIA');

      DB::afterCommit(static function () use ($comment, $data, $likesCount, $targetLiked, $eventCode, $memory): void {
        try {
          broadcast(new CommentLikeUpdated(
            $eventCode,
            $memory->external_id,
            $comment->external_id,
            $likesCount,
            $data->actorKey,
            $targetLiked,
            $data->deviceId
          ))->toOthers();
        } catch (\Throwable $e) {
          Log::warning('Direct broadcast of CommentLikeUpdated failed: ' . $e->getMessage());
        }
        ProcessCommentLikeJob::dispatch($comment->id, $data->actorKey, $likesCount, $targetLiked, $data->deviceId, false);
      });

      return [
        'likes' => $likesCount,
        'liked' => $targetLiked,
      ];
    });
  }

  private function findExistingLike(int $commentId, string $actorKey, ?string $deviceId): ?MemoryCommentLike
  {
    $query = MemoryCommentLike::where('comment_id', $commentId);
    if ($deviceId) {
      return $query->where(static function ($q) use ($actorKey, $deviceId): void {
        $q->where('actor_key', $actorKey)->orWhere('device_id', $deviceId);
      })->first();
    }

    return $query->where('actor_key', $actorKey)->first();
  }

  private function applyLikeMutation(
    MemoryComment $comment,
    ?MemoryCommentLike $existing,
    bool $targetLiked,
    ToggleCommentLikeData $data
  ): int {
    $fallbackCount = (int) ($comment->likes_count ?? 0);

    if ($targetLiked && !$existing) {
      MemoryCommentLike::create([
        'comment_id' => $comment->id,
        'actor_key' => $data->actorKey,
        'device_id' => $data->deviceId,
      ]);
      return $this->counterService->increment($comment->id, $fallbackCount);
    }

    if (!$targetLiked && $existing) {
      $existing->delete();
      return $this->counterService->decrement($comment->id, $fallbackCount);
    }

    return $this->counterService->get($comment->id, $fallbackCount);
  }
}
