<?php

namespace App\Observers;

use App\Models\Memory;
use App\Traits\Cache\InvalidatesCache;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class MemoryObserver
{
  use InvalidatesCache;

  public function creating(Memory $memory): void
  {
    if (!$memory->timestamp_ms) {
      $memory->timestamp_ms = (int) round(microtime(true) * 1000);
    }

    if (!$memory->external_id) {
      $memory->external_id = 'xv-nat-' . $memory->timestamp_ms;
    }

    if (!$memory->moment_tag) {
      $memory->moment_tag = 'Fiesta en Vivo';
    }
  }

  public function created(Memory $memory): void
  {
    Cache::store('redis')->forget("memory:{$memory->external_id}");
    $this->scanAndDelete("memories:feed:event:{$memory->event_id}:*");
  }

  public function updated(Memory $memory): void
  {
    Cache::store('redis')->forget("memory:{$memory->external_id}");
    $this->scanAndDelete("memories:feed:event:{$memory->event_id}:*");
  }

  public function deleting(Memory $memory): void
  {
    Cache::store('redis')->forget("memory:{$memory->external_id}");
    $this->scanAndDelete("memories:feed:event:{$memory->event_id}:*");

    // Collect file paths before CASCADE delete from database
    $filesByDisk = $memory->mediaItems()
      ->get(['disk', 'file_path', 'thumb_path', 'poster_path'])
      ->groupBy(fn ($item) => $item->disk ?: config('xv.media_disk', 'public'))
      ->map(fn ($items) => $items->flatMap->storedPaths()->filter()->values()->all())
      ->all();

    if (empty($filesByDisk)) {
      return;
    }

    DB::afterCommit(static function () use ($filesByDisk): void {
      foreach ($filesByDisk as $diskName => $paths) {
        if (!empty($paths)) {
          Storage::disk($diskName)->delete($paths);
        }
      }
    });
  }
}
