<?php

namespace App\Jobs;

use App\Events\MemoryCreated;
use App\Http\Resources\Api\V1\MemoryResource;
use App\Models\Memory;
use App\Traits\Cache\InvalidatesCache;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ProcessMemoryJob implements ShouldQueue
{
  use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, InvalidatesCache;

  public int $tries = 3;
  public int $timeout = 60;

  public function __construct(
    public int $memoryId
  ) {
    $this->queue = 'default';
  }

  public function handle(): void
  {
    $memory = Memory::with(['event', 'mediaItems', 'reactions', 'comments.commentLikes'])->find($this->memoryId);
    if (!$memory) {
      return;
    }

    $event = $memory->event;
    $eventCode = $event?->code ?? (string) config('xv.default_event_code', 'XV-NATALIA');

    foreach ($memory->mediaItems as $mediaItem) {
      ProcessMediaOptimizationJob::dispatch($mediaItem->id);
    }

    Cache::store('redis')->forget("memory:{$memory->external_id}");
    $this->scanAndDelete("memories:feed:event:{$memory->event_id}:*");

    $payload = (new MemoryResource($memory))->resolve();
    broadcast(new MemoryCreated($eventCode, $payload));
  }

  public function failed(\Throwable $exception): void
  {
    Log::error("ProcessMemoryJob failed for memory #{$this->memoryId}: " . $exception->getMessage(), [
      'exception' => $exception,
    ]);
  }
}
