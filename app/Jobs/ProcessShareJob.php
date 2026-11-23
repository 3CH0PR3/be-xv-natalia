<?php

namespace App\Jobs;

use App\Events\MemoryShareUpdated;
use App\Events\MemoryShared;
use App\Models\Memory;
use App\Support\Cache\Keys\EventKeys;
use App\Traits\Cache\InvalidatesCache;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ProcessShareJob implements ShouldQueue
{
  use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, InvalidatesCache;

  public int $tries = 3;
  public int $timeout = 30;

  public function __construct(
    public int $memoryId,
    public ?int $sharesCount = null
  ) {
    $this->queue = 'default';
  }

  public function handle(): void
  {
    $memory = Memory::with('event')->find($this->memoryId);
    if (!$memory) {
      return;
    }

    $shares = $this->sharesCount;
    if ($shares === null) {
      $memory->increment('shares_count');
      $shares = (int) $memory->fresh()->shares_count;
    }

    $this->forgetItem(EventKeys::memory($memory->external_id));
    $this->scanAndDelete(EventKeys::feedPattern($memory->event_id));

    $eventCode = $memory->event?->code ?? config('xv.default_event_code', 'XV-NATALIA');

    broadcast(new MemoryShared(
      $eventCode,
      $memory->external_id,
      $shares
    ));

    broadcast(new MemoryShareUpdated(
      $eventCode,
      $memory->external_id,
      $shares
    ));
  }

  public function failed(\Throwable $exception): void
  {
    Log::error("ProcessShareJob failed for memory #{$this->memoryId}: " . $exception->getMessage(), [
      'exception' => $exception,
    ]);
  }
}
