<?php

namespace App\Actions\Memory;

use App\Jobs\ProcessShareJob;
use App\Models\Memory;
use Illuminate\Support\Facades\DB;

class IncrementMemoryShareAction
{
  public function execute(string $externalId): Memory
  {
    return DB::transaction(function () use ($externalId): Memory {
      $memory = Memory::with('event')
        ->where('external_id', $externalId)
        ->lockForUpdate()
        ->firstOrFail();

      $memory->increment('shares_count');
      $fresh = $memory->fresh(['mediaItems', 'reactions', 'comments']);

      DB::afterCommit(static function () use ($fresh): void {
        ProcessShareJob::dispatch($fresh->id, (int) $fresh->shares_count);
      });

      return $fresh;
    });
  }
}
