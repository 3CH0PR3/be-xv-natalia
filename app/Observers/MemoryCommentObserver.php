<?php

namespace App\Observers;

use App\Models\Memory;
use App\Models\MemoryComment;
use App\Support\Cache\Keys\EventKeys;
use App\Traits\Cache\InvalidatesCache;
use Illuminate\Support\Str;

class MemoryCommentObserver
{
  use InvalidatesCache;

  public function creating(MemoryComment $comment): void
  {
    if (!$comment->created_at_ms) {
      $comment->created_at_ms = (int) round(microtime(true) * 1000);
    }

    if (!$comment->external_id) {
      $comment->external_id = 'c-' . $comment->created_at_ms . '-' . Str::lower(Str::random(4));
    }
  }

  public function created(MemoryComment $comment): void
  {
    $this->recountComments($comment->memory_id);
    $this->forgetItem(EventKeys::memoryComments($comment->memory_id));
  }

  public function updated(MemoryComment $comment): void
  {
    $this->forgetItem(EventKeys::memoryComments($comment->memory_id));
  }

  public function deleted(MemoryComment $comment): void
  {
    $this->recountComments($comment->memory_id);
    $this->forgetItem(EventKeys::memoryComments($comment->memory_id));
  }

  private function recountComments(int|string $memoryId): void
  {
    $exactCount = MemoryComment::where('memory_id', $memoryId)->count();
    $memory = Memory::whereKey($memoryId)->first(['id', 'likes_count', 'fires_count', 'cheers_count']);
    if (!$memory) {
      return;
    }
    $score = ($memory->likes_count ?? 0) + ($memory->fires_count ?? 0) + ($memory->cheers_count ?? 0) + $exactCount;
    Memory::whereKey($memoryId)->update([
      'comments_count' => $exactCount,
      'engagement_score' => $score,
    ]);
  }
}
