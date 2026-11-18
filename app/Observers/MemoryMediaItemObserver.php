<?php

namespace App\Observers;

use App\Models\MemoryMediaItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class MemoryMediaItemObserver
{
  public function deleted(MemoryMediaItem $item): void
  {
    $diskName = $item->disk ?: config('xv.media_disk', 'public');
    $paths = $item->storedPaths();

    if (empty($paths)) {
      return;
    }

    DB::afterCommit(static function () use ($diskName, $paths): void {
      Storage::disk($diskName)->delete($paths);
    });
  }
}
