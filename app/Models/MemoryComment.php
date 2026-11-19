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
    'device_lock_id',
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

  public function deviceLock(): BelongsTo
  {
    return $this->belongsTo(DeviceLock::class);
  }

  public function getResolvedAuthorAttribute(): string
  {
    return $this->deviceLock?->guest_name ?? $this->author ?? '';
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
    $cleanDevice = trim((string) $deviceId);
    $cleanActor = trim((string) $actorKey);

    $isDirectDeviceMatch = $this->owner_device_id && $cleanDevice !== '' && hash_equals($this->owner_device_id, $cleanDevice);
    if ($isDirectDeviceMatch) {
      return true;
    }

    $isDirectActorMatch = $this->owner_actor_key && $cleanActor !== '' && hash_equals($this->owner_actor_key, $cleanActor);
    if ($isDirectActorMatch) {
      return true;
    }

    $hasLoadedLock = $this->device_lock_id && $this->relationLoaded('deviceLock') && $this->deviceLock;
    $isLockDeviceMatch = $hasLoadedLock && $cleanDevice !== '' && hash_equals((string) $this->deviceLock->device_id, $cleanDevice);
    if ($isLockDeviceMatch) {
      return true;
    }

    $isLockActorMatch = $hasLoadedLock && $cleanActor !== '' && hash_equals((string) $this->deviceLock->actor_key, $cleanActor);
    if ($isLockActorMatch) {
      return true;
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
