<?php

namespace App\Actions\Memory;

use App\Models\Memory;
use App\Models\MemoryReaction;
use Illuminate\Support\Facades\Redis;
use Throwable;

final readonly class ManageMemoryReactionCountersAction
{
  private const PREFIX = 'memory:reactions:';
  private const DIRTY_SET = 'memories:dirty_reactions';
  private const TTL_SECONDS = 86400;

  /**
   * @param array{likes?: int, fires?: int, cheers?: int} $fallbacks
   * @return array{likes: int, fires: int, cheers: int}
   */
  public function getCounts(int|string $memoryId, array $fallbacks = []): array
  {
    $defaultLikes = max(0, (int) ($fallbacks['likes'] ?? 0));
    $defaultFires = max(0, (int) ($fallbacks['fires'] ?? 0));
    $defaultCheers = max(0, (int) ($fallbacks['cheers'] ?? 0));

    try {
      $keys = [
        self::PREFIX . $memoryId . ':likes',
        self::PREFIX . $memoryId . ':fires',
        self::PREFIX . $memoryId . ':cheers',
      ];
      $vals = Redis::mGet($keys);

      $isRedisEmpty = ($vals[0] === null || $vals[0] === false)
        && ($vals[1] === null || $vals[1] === false)
        && ($vals[2] === null || $vals[2] === false);

      if ($isRedisEmpty) {
        if ($defaultLikes === 0 && $defaultFires === 0 && $defaultCheers === 0 && (string) $memoryId !== '') {
          $defaultLikes = MemoryReaction::where('memory_id', $memoryId)->where('choice', 'likes')->count();
          $defaultFires = MemoryReaction::where('memory_id', $memoryId)->where('choice', 'fires')->count();
          $defaultCheers = MemoryReaction::where('memory_id', $memoryId)->where('choice', 'cheers')->count();
        }

        try {
          Redis::setEx(self::PREFIX . $memoryId . ':likes', self::TTL_SECONDS, $defaultLikes);
          Redis::setEx(self::PREFIX . $memoryId . ':fires', self::TTL_SECONDS, $defaultFires);
          Redis::setEx(self::PREFIX . $memoryId . ':cheers', self::TTL_SECONDS, $defaultCheers);
        } catch (Throwable) {
        }

        return ['likes' => $defaultLikes, 'fires' => $defaultFires, 'cheers' => $defaultCheers];
      }

      return [
        'likes' => $vals[0] !== null && $vals[0] !== false ? max(0, (int) $vals[0]) : $defaultLikes,
        'fires' => $vals[1] !== null && $vals[1] !== false ? max(0, (int) $vals[1]) : $defaultFires,
        'cheers' => $vals[2] !== null && $vals[2] !== false ? max(0, (int) $vals[2]) : $defaultCheers,
      ];
    } catch (Throwable) {
      return ['likes' => $defaultLikes, 'fires' => $defaultFires, 'cheers' => $defaultCheers];
    }
  }

  /**
   * @param array{likes?: int, fires?: int, cheers?: int} $fallbacks
   * @return array{likes: int, fires: int, cheers: int}
   */
  public function adjust(int|string $memoryId, ?string $prevChoice, ?string $nextChoice, array $fallbacks = []): array
  {
    $current = $this->getCounts($memoryId, $fallbacks);
    if ($prevChoice === $nextChoice) {
      return $current;
    }

    try {
      $this->decrementChoice($memoryId, $prevChoice, $current);
      $this->incrementChoice($memoryId, $nextChoice, $current);
      Redis::sAdd(self::DIRTY_SET, $memoryId);

      return $this->getCounts($memoryId, $current);
    } catch (Throwable) {
      return $this->computeLocalAdjustment($current, $prevChoice, $nextChoice);
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
        $memoryId = (string) $id;
        $counts = $this->getCounts($memoryId);

        Memory::where('id', $memoryId)->update([
          'likes_count' => $counts['likes'],
          'fires_count' => $counts['fires'],
          'cheers_count' => $counts['cheers'],
        ]);

        Redis::sRem(self::DIRTY_SET, $id);
        $syncedCount++;
      }

      return $syncedCount;
    } catch (Throwable) {
      return 0;
    }
  }

  private function decrementChoice(int|string $memoryId, ?string $choice, array &$current): void
  {
    if (!$choice || !in_array($choice, ['likes', 'fires', 'cheers'], true)) {
      return;
    }

    $key = self::PREFIX . $memoryId . ':' . $choice;
    if (!Redis::exists($key)) {
      Redis::setex($key, self::TTL_SECONDS, max(0, (int) ($current[$choice] ?? 0)));
    }

    $val = (int) Redis::decr($key);
    if ($val < 0) {
      Redis::setex($key, self::TTL_SECONDS, 0);
      $val = 0;
    }
    Redis::expire($key, self::TTL_SECONDS);
    $current[$choice] = $val;
  }

  private function incrementChoice(int|string $memoryId, ?string $choice, array &$current): void
  {
    if (!$choice || !in_array($choice, ['likes', 'fires', 'cheers'], true)) {
      return;
    }

    $key = self::PREFIX . $memoryId . ':' . $choice;
    if (!Redis::exists($key)) {
      Redis::setex($key, self::TTL_SECONDS, max(0, (int) ($current[$choice] ?? 0)));
    }

    $val = (int) Redis::incr($key);
    Redis::expire($key, self::TTL_SECONDS);
    $current[$choice] = max(0, $val);
  }

  /**
   * @param array{likes: int, fires: int, cheers: int} $current
   * @return array{likes: int, fires: int, cheers: int}
   */
  private function computeLocalAdjustment(array $current, ?string $prev, ?string $next): array
  {
    if ($prev && isset($current[$prev])) {
      $current[$prev] = max(0, $current[$prev] - 1);
    }
    if ($next && isset($current[$next])) {
      $current[$next] = max(0, $current[$next] + 1);
    }

    return $current;
  }
}
