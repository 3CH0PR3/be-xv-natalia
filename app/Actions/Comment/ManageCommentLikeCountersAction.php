<?php

namespace App\Actions\Comment;

use App\Models\MemoryComment;
use Illuminate\Support\Facades\Redis;
use Throwable;

final readonly class ManageCommentLikeCountersAction
{
  private const KEY_PREFIX = 'comment:likes:';
  private const DIRTY_SET = 'comments:dirty_likes';
  private const TTL_SECONDS = 86400;

  public function get(int|string $commentId, int $fallback = 0): int
  {
    try {
      $val = Redis::get(self::KEY_PREFIX . $commentId);
      if ($val !== null && $val !== false) {
        return max(0, (int) $val);
      }
      $this->set($commentId, $fallback);

      return max(0, $fallback);
    } catch (Throwable) {
      return max(0, $fallback);
    }
  }

  /**
   * @param array<int> $commentIds
   * @param array<int, int> $fallbacks
   * @return array<int, int>
   */
  public function getMultiple(array $commentIds, array $fallbacks = []): array
  {
    if (empty($commentIds)) {
      return [];
    }

    try {
      $keys = array_map(static fn (int|string $id): string => self::KEY_PREFIX . $id, $commentIds);
      $values = Redis::mGet($keys);
      $result = [];

      foreach ($commentIds as $idx => $id) {
        $val = $values[$idx] ?? null;
        if ($val !== null && $val !== false) {
          $result[$id] = max(0, (int) $val);
          continue;
        }
        $fallback = $fallbacks[$id] ?? 0;
        $result[$id] = max(0, $fallback);
        $this->set($id, $fallback);
      }

      return $result;
    } catch (Throwable) {
      $result = [];
      foreach ($commentIds as $id) {
        $result[$id] = max(0, $fallbacks[$id] ?? 0);
      }

      return $result;
    }
  }

  public function set(int|string $commentId, int $count): void
  {
    try {
      Redis::setex(self::KEY_PREFIX . $commentId, self::TTL_SECONDS, max(0, $count));
    } catch (Throwable) {
    }
  }

  public function increment(int|string $commentId, int $initialFallback = 0): int
  {
    try {
      $key = self::KEY_PREFIX . $commentId;
      if (!Redis::exists($key)) {
        $this->set($commentId, $initialFallback);
      }
      $next = (int) Redis::incr($key);
      Redis::expire($key, self::TTL_SECONDS);
      Redis::sAdd(self::DIRTY_SET, $commentId);

      return max(0, $next);
    } catch (Throwable) {
      return max(0, $initialFallback + 1);
    }
  }

  public function decrement(int|string $commentId, int $initialFallback = 0): int
  {
    try {
      $key = self::KEY_PREFIX . $commentId;
      if (!Redis::exists($key)) {
        $this->set($commentId, $initialFallback);
      }
      $next = (int) Redis::decr($key);
      if ($next < 0) {
        $this->set($commentId, 0);
        $next = 0;
      }
      Redis::expire($key, self::TTL_SECONDS);
      Redis::sAdd(self::DIRTY_SET, $commentId);

      return max(0, $next);
    } catch (Throwable) {
      return max(0, $initialFallback - 1);
    }
  }

  public function syncDirtyCounters(int $limit = 200): int
  {
    try {
      $dirtyIds = Redis::sMembers(self::DIRTY_SET);
      if (empty($dirtyIds)) {
        return 0;
      }

      $batch = array_slice($dirtyIds, 0, $limit);
      $syncedCount = 0;

      foreach ($batch as $id) {
        $commentId = (string) $id;
        $countVal = Redis::get(self::KEY_PREFIX . $commentId);
        if ($countVal !== null && $countVal !== false) {
          MemoryComment::where('id', $commentId)->update(['likes_count' => max(0, (int) $countVal)]);
          $syncedCount++;
        }
        Redis::sRem(self::DIRTY_SET, $id);
      }

      return $syncedCount;
    } catch (Throwable) {
      return 0;
    }
  }

  public function clear(int|string $commentId): void
  {
    try {
      Redis::del(self::KEY_PREFIX . $commentId);
      Redis::sRem(self::DIRTY_SET, $commentId);
    } catch (Throwable) {
    }
  }
}
