<?php

namespace App\Jobs;

use App\Models\MemoryMediaItem;
use App\Traits\Cache\InvalidatesCache;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ProcessMediaOptimizationJob implements ShouldQueue
{
  use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, InvalidatesCache;

  public int $tries = 3;
  public int $timeout = 60;

  public function __construct(
    public int|string $mediaItemId
  ) {
    $this->queue = 'media-fast';
  }

  public function handle(): void
  {
    $item = MemoryMediaItem::with('memory')->find($this->mediaItemId);
    if (!$item) {
      return;
    }

    $disk = Storage::disk($item->disk ?: config('xv.media_disk', 'public'));
    if (!$disk->exists($item->file_path)) {
      return;
    }

    $byteSize = $disk->size($item->file_path);
    if ($byteSize !== $item->byte_size) {
      $item->update(['byte_size' => $byteSize]);
    }
  }

  public function failed(\Throwable $exception): void
  {
    Log::error("ProcessMediaOptimizationJob failed for media #{$this->mediaItemId}: " . $exception->getMessage(), [
      'exception' => $exception,
    ]);
  }
}
