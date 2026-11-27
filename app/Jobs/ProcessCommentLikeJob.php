<?php

namespace App\Jobs;

use App\Events\CommentLikeUpdated;
use App\Models\Memory;
use App\Models\MemoryComment;
use App\Support\Cache\Keys\EventKeys;
use App\Traits\Cache\InvalidatesCache;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessCommentLikeJob implements ShouldQueue
{
  use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, InvalidatesCache;

  public int $tries = 3;
  public int $timeout = 30;

  public function __construct(
    public int|string $commentId,
    public string $actorKey,
    public ?int $likesCount = null,
    public ?bool $liked = null,
    public ?string $deviceId = null,
    public bool $shouldBroadcast = true
  ) {
    $this->queue = 'default';
  }

  public function handle(): void
  {
    $comment = MemoryComment::with(['memory.event', 'commentLikes'])->find($this->commentId);
    if (!$comment) {
      return;
    }

    $memory = $comment->memory;
    if (!$memory) {
      return;
    }

    $likesCount = $this->likesCount;
    if ($likesCount === null) {
      $likesCount = $comment->commentLikes()->count();
    }
    $comment->update(['likes_count' => max(0, (int) $likesCount)]);

    $liked = $this->liked ?? $comment->commentLikes()->where('actor_key', $this->actorKey)->exists();

    $this->forgetItem(EventKeys::memoryComments($memory->id));
    $this->forgetItem(EventKeys::memory($memory->external_id));

    if ($this->shouldBroadcast) {
      $eventCode = $memory->event?->code ?? config('xv.default_event_code', 'XV-NATALIA');

      broadcast(new CommentLikeUpdated(
        $eventCode,
        $memory->external_id,
        $comment->external_id,
        $likesCount,
        $this->actorKey,
        $liked,
        $this->deviceId
      ));
    }
  }

  public function failed(\Throwable $exception): void
  {
    Log::error("ProcessCommentLikeJob failed for comment #{$this->commentId}: " . $exception->getMessage(), [
      'exception' => $exception,
    ]);
  }
}
