<?php

namespace App\Actions\Comment;

use App\Models\Memory;
use App\Support\Cache\Keys\EventKeys;
use App\Traits\Cache\CacheableCache;
use Illuminate\Support\Collection;

class ListCommentsAction
{
  use CacheableCache;

  public function execute(string $memoryExternalId): Collection
  {
    $memory = Memory::where('external_id', $memoryExternalId)->firstOrFail();

    if (app()->environment('testing')) {
      return $memory->comments()
        ->with(['deviceLock:id,guest_name,avatar_path,cover_path,actor_key,device_id,updated_at', 'commentLikes'])
        ->ordered()
        ->get();
    }

    return $this->remember(
      EventKeys::memoryComments($memory->id),
      120,
      fn () => $memory->comments()
        ->with(['deviceLock:id,guest_name,avatar_path,cover_path,actor_key,device_id,updated_at', 'commentLikes'])
        ->ordered()
        ->get()
    );
  }
}
