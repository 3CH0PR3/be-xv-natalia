<?php

namespace App\Jobs;

use App\Events\CommentCreated;
use App\Http\Resources\Api\V1\MemoryCommentResource;
use App\Models\Memory;
use App\Models\MemoryComment;
use App\Traits\Cache\InvalidatesCache;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ProcessCommentJob implements ShouldQueue
{
  use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, InvalidatesCache;

  public int $tries = 3;
  public int $timeout = 30;

  public function __construct(
    public int $commentId
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

    $commentsCount = MemoryComment::where('memory_id', $memory->id)->count();
    $memory->update(['comments_count' => $commentsCount]);

    Cache::store('redis')->forget("memory:{$memory->external_id}");
    Cache::store('redis')->forget("memory:comments:{$memory->id}");
    $this->scanAndDelete("memories:feed:event:{$memory->event_id}:*");

    $eventCode = $memory->event?->code ?? config('xv.default_event_code', 'XV-NATALIA');
    $payload = (new MemoryCommentResource($comment))->resolve();

    broadcast(new CommentCreated($eventCode, $memory->external_id, $payload, $commentsCount));
  }

  public function failed(\Throwable $exception): void
  {
    Log::error("ProcessCommentJob failed for comment #{$this->commentId}: " . $exception->getMessage(), [
      'exception' => $exception,
    ]);
  }
}
