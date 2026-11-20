<?php

namespace App\Actions\Comment;

use App\DTOs\Comment\ToggleCommentLikeData;
use App\Jobs\ProcessCommentLikeJob;
use App\Models\Memory;
use App\Models\MemoryComment;
use App\Models\MemoryCommentLike;
use App\Services\CommentLikeCounterService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ToggleCommentLikeAction
{
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

      Cache::store('redis')->forget("memory:comments:{$memory->id}");

      DB::afterCommit(static function () use ($comment, $data, $likesCount, $targetLiked): void {
        ProcessCommentLikeJob::dispatch($comment->id, $data->actorKey, $likesCount, $targetLiked, $data->deviceId);
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
