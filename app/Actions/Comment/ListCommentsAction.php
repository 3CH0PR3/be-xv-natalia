<?php

namespace App\Actions\Comment;

use App\Models\Memory;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class ListCommentsAction
{
  public function execute(string $memoryExternalId): Collection
  {
    $memory = Memory::where('external_id', $memoryExternalId)->firstOrFail();

    return Cache::store('redis')->remember(
      "memory:comments:{$memory->id}",
      120,
      fn () => $memory->comments()
        ->with(['deviceLock:id,guest_name,avatar_path,cover_path,actor_key,device_id,updated_at', 'commentLikes'])
        ->ordered()
        ->get()
    );
  }
}
