<?php

namespace App\Jobs;

use App\Events\MemoryReactionUpdated;
use App\Models\Memory;
use App\Models\MemoryReaction;
use App\Support\Cache\Keys\EventKeys;
use App\Traits\Cache\InvalidatesCache;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ProcessLikeJob implements ShouldQueue
{
  use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, InvalidatesCache;

  public int $tries = 3;
  public int $timeout = 30;

  public function __construct(
    public int $memoryId,
    public string $actorKey,
    public string $choice,
    public ?int $likes = null,
    public ?int $fires = null,
    public ?int $cheers = null,
    public ?string $deviceId = null,
    public bool $shouldBroadcast = true
  ) {
    $this->queue = 'default';
  }

  public function handle(): void
  {
    $memory = Memory::with('event')->find($this->memoryId);
    if (!$memory) {
      return;
    }

    $likes = $this->likes;
    $fires = $this->fires;
    $cheers = $this->cheers;

    if ($likes === null || $fires === null || $cheers === null) {
      $likes = MemoryReaction::where('memory_id', $memory->id)->where('choice', 'likes')->count();
      $fires = MemoryReaction::where('memory_id', $memory->id)->where('choice', 'fires')->count();
      $cheers = MemoryReaction::where('memory_id', $memory->id)->where('choice', 'cheers')->count();
    }

    $memory->update([
      'likes_count' => $likes,
      'fires_count' => $fires,
      'cheers_count' => $cheers,
    ]);

    $this->forgetItem(EventKeys::memory($memory->external_id));
    $this->scanAndDelete(EventKeys::feedPattern($memory->event_id));

    if ($this->shouldBroadcast) {
      $eventCode = $memory->event?->code ?? config('xv.default_event_code', 'XV-NATALIA');

      broadcast(new MemoryReactionUpdated(
        $eventCode,
        $memory->external_id,
        $likes,
        $fires,
        $cheers,
        $this->actorKey,
        $this->choice,
        $this->deviceId
      ));
    }
  }

  public function failed(\Throwable $exception): void
  {
    Log::error("ProcessLikeJob failed for memory #{$this->memoryId}: " . $exception->getMessage(), [
      'exception' => $exception,
    ]);
  }
}
