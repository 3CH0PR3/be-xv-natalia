<?php

namespace App\Jobs;

use App\Services\MemoryReactionCounterService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncMemoryReactionCountersJob implements ShouldQueue
{
  use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

  public int $tries = 1;
  public int $timeout = 25;

  public function __construct(
    public int $limit = 200,
  ) {
    $this->queue = 'default';
  }

  public function handle(MemoryReactionCounterService $service): void
  {
    $service->syncDirtyCounters($this->limit);
  }
}
