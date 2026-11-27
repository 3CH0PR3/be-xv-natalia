<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasVersion7Uuids;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MemoryReaction extends Model
{
  use HasVersion7Uuids;
  public const CHOICES = ['likes', 'fires', 'cheers'];

  protected $fillable = [
    'memory_id',
    'actor_key',
    'device_id',
    'choice',
  ];

  public function memory(): BelongsTo
  {
    return $this->belongsTo(Memory::class);
  }

  public function scopeForActor(Builder $query, int|string $memoryId, string $actorKey, ?string $deviceId = null): Builder
  {
    return $query->where('memory_id', $memoryId)
      ->where(function (Builder $q) use ($actorKey, $deviceId): void {
        $q->where('actor_key', $actorKey);
        if ($deviceId) {
          $q->orWhere('device_id', $deviceId);
        }
      });
  }
}
