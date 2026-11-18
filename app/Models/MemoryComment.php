<?php

namespace App\Models;

use App\Observers\MemoryCommentObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[ObservedBy([MemoryCommentObserver::class])]
class MemoryComment extends Model
{
  protected $fillable = [
    'external_id',
    'memory_id',
    'author',
    'table_label',
    'text',
    'owner_device_id',
    'owner_actor_key',
    'likes_count',
    'created_at_ms',
  ];

  protected function casts(): array
  {
    return [
      'likes_count' => 'integer',
      'created_at_ms' => 'integer',
    ];
  }

  public function memory(): BelongsTo
  {
    return $this->belongsTo(Memory::class);
  }

  public function commentLikes(): HasMany
  {
    return $this->hasMany(MemoryCommentLike::class, 'comment_id');
  }

  public function scopeOrdered(Builder $query): Builder
  {
    return $query->orderBy('created_at_ms');
  }

  public function isOwnedBy(?string $deviceId, ?string $actorKey = null): bool
  {
    if ($this->owner_device_id && $deviceId) {
      return hash_equals($this->owner_device_id, $deviceId);
    }

    if ($this->owner_actor_key && $actorKey) {
      return hash_equals($this->owner_actor_key, $actorKey);
    }

    return false;
  }

  public function isLikedByViewer(?string $actorKey, ?string $deviceId): bool
  {
    if (!$this->relationLoaded('commentLikes') || (!$actorKey && !$deviceId)) {
      return false;
    }

    return $this->commentLikes->contains(fn (MemoryCommentLike $like): bool =>
      ($actorKey && $like->actor_key === $actorKey)
      || ($deviceId && $like->device_id === $deviceId)
    );
  }
}
