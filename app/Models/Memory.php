<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasVersion7Uuids;
use App\Observers\MemoryObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[ObservedBy([MemoryObserver::class])]
class Memory extends Model
{
  use HasVersion7Uuids;
  public const STATUS_APPROVED = 'approved';
  public const STATUS_PENDING = 'pending';
  public const STATUS_REJECTED = 'rejected';
  public const STATUS_HIDDEN = 'hidden';

  protected $fillable = [
    'external_id',
    'event_id',
    'event_table_id',
    'device_lock_id',
    'guest_name',
    'table_code',
    'owner_device_id',
    'owner_actor_key',
    'moment_tag',
    'caption',
    'caption_edited_at',
    'media_type',
    'status',
    'likes_count',
    'fires_count',
    'cheers_count',
    'comments_count',
    'shares_count',
    'engagement_score',
    'timestamp_ms',
  ];

  protected function casts(): array
  {
    return [
      'likes_count' => 'integer',
      'fires_count' => 'integer',
      'cheers_count' => 'integer',
      'comments_count' => 'integer',
      'shares_count' => 'integer',
      'engagement_score' => 'integer',
      'timestamp_ms' => 'integer',
      'caption_edited_at' => 'datetime',
    ];
  }

  public function event(): BelongsTo
  {
    return $this->belongsTo(Event::class);
  }

  public function table(): BelongsTo
  {
    return $this->belongsTo(EventTable::class, 'event_table_id');
  }

  public function deviceLock(): BelongsTo
  {
    return $this->belongsTo(DeviceLock::class);
  }

  public function getResolvedGuestNameAttribute(): string
  {
    return $this->deviceLock?->guest_name ?? $this->guest_name ?? '';
  }

  public function getResolvedAvatarUrlAttribute(): ?string
  {
    if ($this->relationLoaded('deviceLock') && $this->deviceLock?->avatar_url) {
      return $this->deviceLock->avatar_url;
    }
    if ($this->device_lock_id && $this->deviceLock?->avatar_url) {
      return $this->deviceLock->avatar_url;
    }
    if ($this->owner_actor_key || $this->owner_device_id || $this->guest_name) {
      $lock = DeviceLock::forEvent($this->event_id ?: 1)
        ->where(function (Builder $q): void {
          if ($this->owner_actor_key) {
            $q->orWhere('actor_key', $this->owner_actor_key);
          }
          if ($this->owner_device_id) {
            $q->orWhere('device_id', $this->owner_device_id);
          }
          if ($this->guest_name) {
            $q->orWhere('guest_name', $this->guest_name);
          }
        })
        ->whereNotNull('avatar_path')
        ->latest('id')
        ->first();

      if ($lock?->avatar_url) {
        return $lock->avatar_url;
      }
    }
    return null;
  }

  public function mediaItems(): HasMany
  {
    return $this->hasMany(MemoryMediaItem::class)->orderBy('sort_order');
  }

  public function reactions(): HasMany
  {
    return $this->hasMany(MemoryReaction::class);
  }

  public function comments(): HasMany
  {
    return $this->hasMany(MemoryComment::class)->orderBy('created_at_ms');
  }

  public function shares(): HasMany
  {
    return $this->hasMany(MemoryShare::class);
  }

  // --- Query Scopes ---

  public function scopeForEvent(Builder $query, int|string $eventId): Builder
  {
    return $query->where('event_id', $eventId);
  }

  public function scopeApproved(Builder $query): Builder
  {
    return $query->where('status', self::STATUS_APPROVED);
  }

  public function scopePublished(Builder $query): Builder
  {
    return $query->where('status', '!=', self::STATUS_REJECTED);
  }

  public function scopeFilterStatus(Builder $query, ?string $status, bool $includeAll = false): Builder
  {
    if ($status && $status !== 'all') {
      return $query->where('status', $status);
    }

    return $includeAll ? $query : $query->approved();
  }

  public function scopeOwnedBy(Builder $query, ?string $deviceId, ?string $actorKey = null): Builder
  {
    return $query->where(function (Builder $q) use ($deviceId, $actorKey): void {
      if ($deviceId) {
        $q->orWhere('owner_device_id', $deviceId);
      }
      if ($actorKey) {
        $q->orWhere('owner_actor_key', $actorKey);
      }
    });
  }

  public function scopeSearch(Builder $query, ?string $term): Builder
  {
    $clean = trim((string) $term);
    if ($clean === '') {
      return $query;
    }

    $like = '%' . $clean . '%';
    return $query->where(function (Builder $q) use ($like): void {
      $q->where('guest_name', 'like', $like)
        ->orWhere('table_code', 'like', $like)
        ->orWhere('caption', 'like', $like)
        ->orWhere('moment_tag', 'like', $like);
    });
  }

  public function scopeFilterCategory(Builder $query, ?string $category): Builder
  {
    return match ($category) {
      'comments' => $query->where('comments_count', '>', 0),
      'videos' => $query->where(function (Builder $q): void {
        $q->where('media_type', 'video')
          ->orWhereHas('mediaItems', static fn (Builder $m) => $m->where('media_type', 'video'));
      }),
      'albums' => $query->has('mediaItems', '>', 1),
      default => $query,
    };
  }

  public function scopeNewestFirst(Builder $query): Builder
  {
    return $query->orderByDesc('created_at')->orderByDesc('id');
  }

  public function scopeMostEngaged(Builder $query): Builder
  {
    // engagement_score es una columna real (likes+fires+cheers+comments) mantenida
    // por ProcessLikeJob y MemoryCommentObserver. Al ser columna real el cursor
    // de Laravel puede serializar/deserializar las 3 columnas correctamente.
    return $query->orderByDesc('engagement_score')->orderByDesc('created_at')->orderByDesc('id');
  }

  public function recalculateEngagementScore(): void
  {
    $this->engagement_score = $this->likes_count + $this->fires_count + $this->cheers_count + $this->comments_count;
  }

  public function scopeWithWallRelations(Builder $query, ?string $viewerActorKey = null, ?string $viewerDeviceId = null): Builder
  {
    return $query->with([
      'deviceLock:id,guest_name,avatar_path,cover_path,actor_key,device_id,updated_at',
      'mediaItems',
      'comments.deviceLock:id,guest_name,avatar_path,cover_path,actor_key,device_id,updated_at',
      'comments.commentLikes' => static function ($q): void {
        $q->select(['id', 'comment_id', 'actor_key', 'device_id']);
      },
      'reactions' => function ($q) use ($viewerActorKey, $viewerDeviceId): void {
        if (!$viewerActorKey && !$viewerDeviceId) {
          $q->whereRaw('1 = 0');
          return;
        }
        $q->where(function (Builder $sub) use ($viewerActorKey, $viewerDeviceId): void {
          if ($viewerActorKey) {
            $sub->orWhere('actor_key', $viewerActorKey);
          }
          if ($viewerDeviceId) {
            $sub->orWhere('device_id', $viewerDeviceId);
          }
        });
      },
    ]);
  }

  // --- Helpers de Dominio ---

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

    return false;
  }

  public function resolveReactionForViewer(?string $actorKey, ?string $deviceId): string
  {
    if (!$this->relationLoaded('reactions') || (!$actorKey && !$deviceId)) {
      return '';
    }

    $match = $this->reactions->first(function (MemoryReaction $r) use ($actorKey, $deviceId): bool {
      return ($actorKey && $r->actor_key === $actorKey)
        || ($deviceId && $r->device_id === $deviceId);
    });

    return $match?->choice ?? '';
  }
}
