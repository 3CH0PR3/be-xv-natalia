<?php

namespace App\Services;

use App\Actions\Memory\ManageMemoryReactionCountersAction;

final readonly class MemoryReactionCounterService
{
  public function __construct(
    private ManageMemoryReactionCountersAction $counters,
  ) {}

  /**
   * @param array{likes?: int, fires?: int, cheers?: int} $fallbacks
   * @return array{likes: int, fires: int, cheers: int}
   */
  public function getCounts(int|string $memoryId, array $fallbacks = []): array
  {
    return $this->counters->getCounts($memoryId, $fallbacks);
  }

  /**
   * @param array{likes?: int, fires?: int, cheers?: int} $fallbacks
   * @return array{likes: int, fires: int, cheers: int}
   */
  public function adjust(int|string $memoryId, ?string $prevChoice, ?string $nextChoice, array $fallbacks = []): array
  {
    return $this->counters->adjust($memoryId, $prevChoice, $nextChoice, $fallbacks);
  }

  public function syncDirtyCounters(int $limit = 200): int
  {
    return $this->counters->syncDirtyCounters($limit);
  }
}
