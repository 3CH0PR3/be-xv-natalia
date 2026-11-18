<?php

namespace App\Models;

use App\Observers\DeviceLockObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[ObservedBy([DeviceLockObserver::class])]
class DeviceLock extends Model
{
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
    'locked_at',
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

  public function scopeForEvent(Builder $query, int $eventId): Builder
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

  public function belongsToTable(int $tableId): bool
  {
    return (int) $this->event_table_id === $tableId;
  }
}
