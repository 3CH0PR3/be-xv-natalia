<?php

namespace App\Services;

use App\Actions\Comment\ManageCommentLikeCountersAction;

final readonly class CommentLikeCounterService
{
  public function __construct(
    private ManageCommentLikeCountersAction $counters,
  ) {}

  public function get(int|string $commentId, int $fallback = 0): int
  {
    return $this->counters->get($commentId, $fallback);
  }

  /**
   * @param array<int> $commentIds
   * @param array<int, int> $fallbacks
   * @return array<int, int>
   */
  public function getMultiple(array $commentIds, array $fallbacks = []): array
  {
    return $this->counters->getMultiple($commentIds, $fallbacks);
  }

  public function set(int|string $commentId, int $count): void
  {
    $this->counters->set($commentId, $count);
  }

  public function increment(int|string $commentId, int $initialFallback = 0): int
  {
    return $this->counters->increment($commentId, $initialFallback);
  }

  public function decrement(int|string $commentId, int $initialFallback = 0): int
  {
    return $this->counters->decrement($commentId, $initialFallback);
  }

  public function syncDirtyCounters(int $limit = 200): int
  {
    return $this->counters->syncDirtyCounters($limit);
  }

  public function clear(int|string $commentId): void
  {
    $this->counters->clear($commentId);
  }
}
