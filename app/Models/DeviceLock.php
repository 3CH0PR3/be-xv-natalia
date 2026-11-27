<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasVersion7Uuids;
use App\Observers\DeviceLockObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

#[ObservedBy([DeviceLockObserver::class])]
class DeviceLock extends Model
{
  use HasVersion7Uuids;
  protected $fillable = [
    'event_id',
    'event_table_id',
    'device_id',
    'hw_fingerprint',
    'fingerprint_candidates',
    'hardware_summary',
    'guest_name',
    'guest_name_slug',
    'actor_key',
    'avatar_path',
    'cover_path',
    'bio',
    'location',
    'locked_at',
  ];

  protected $appends = [
    'avatar_url',
    'cover_url',
  ];

  protected function casts(): array
  {
    return [
      'fingerprint_candidates' => 'array',
      'hardware_summary' => 'array',
      'locked_at' => 'datetime',
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

  public function fingerprintIndexes(): HasMany
  {
    return $this->hasMany(DeviceFingerprintIndex::class);
  }

  public function memories(): HasMany
  {
    return $this->hasMany(Memory::class);
  }

  public function comments(): HasMany
  {
    return $this->hasMany(MemoryComment::class);
  }

  public function shares(): HasMany
  {
    return $this->hasMany(MemoryShare::class);
  }

  public function scopeForEvent(Builder $query, int|string $eventId): Builder
  {
    return $query->where('event_id', $eventId);
  }

  public function scopeByDeviceId(Builder $query, string $deviceId): Builder
  {
    return $query->where('device_id', trim($deviceId));
  }

  public function scopeMatchingGuestIdentity(Builder $query, ?string $deviceId, ?string $hwFingerprint): Builder
  {
    return $query->where(function (Builder $q) use ($deviceId, $hwFingerprint): void {
      if ($deviceId) {
        $q->orWhere('device_id', trim($deviceId));
      }
      if ($hwFingerprint) {
        $q->orWhere('hw_fingerprint', trim($hwFingerprint));
      }
    });
  }

  public static function normalizeSlug(string $value, int $maxLen = 80): string
  {
    return Str::of($value)
      ->ascii()
      ->lower()
      ->replaceMatches('/[^a-z0-9]+/', '-')
      ->trim('-')
      ->limit($maxLen, '')
      ->toString();
  }

  public function belongsToTable(int|string $tableId): bool
  {
    return (int) $this->event_table_id === $tableId;
  }

  public function getAvatarUrlAttribute(): ?string
  {
    if (!$this->avatar_path) return null;
    $diskName = (string) config('xv.media_disk', 'public');
    $driver = (string) (config("filesystems.disks.{$diskName}.driver") ?? 'local');
    $v = $this->updated_at?->timestamp ?? $this->created_at?->timestamp;
    $query = $v ? "?v={$v}" : '';
    if ($driver !== 'local') {
      return Storage::disk($diskName)->url($this->avatar_path) . $query;
    }
    $identifier = $this->actor_key ?: $this->id;
    if (app()->bound('request') && ($req = app('request')) instanceof \Illuminate\Http\Request) {
      return rtrim($req->getSchemeAndHttpHost(), '/') . "/api/v1/profile/{$identifier}/avatar{$query}";
    }
    return Storage::disk($diskName)->url($this->avatar_path) . $query;
  }

  public function getCoverUrlAttribute(): ?string
  {
    if (!$this->cover_path) return null;
    $diskName = (string) config('xv.media_disk', 'public');
    $driver = (string) (config("filesystems.disks.{$diskName}.driver") ?? 'local');
    $v = $this->updated_at?->timestamp ?? $this->created_at?->timestamp;
    $query = $v ? "?v={$v}" : '';
    if ($driver !== 'local') {
      return Storage::disk($diskName)->url($this->cover_path) . $query;
    }
    $identifier = $this->actor_key ?: $this->id;
    if (app()->bound('request') && ($req = app('request')) instanceof \Illuminate\Http\Request) {
      return rtrim($req->getSchemeAndHttpHost(), '/') . "/api/v1/profile/{$identifier}/cover{$query}";
    }
    return Storage::disk($diskName)->url($this->cover_path) . $query;
  }
}
