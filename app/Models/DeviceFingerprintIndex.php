<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeviceFingerprintIndex extends Model
{
  protected $table = 'device_fingerprint_index';

  protected $fillable = [
    'event_id',
    'device_lock_id',
    'fingerprint',
  ];

  public function event(): BelongsTo
  {
    return $this->belongsTo(Event::class);
  }

  public function deviceLock(): BelongsTo
  {
    return $this->belongsTo(DeviceLock::class);
  }

  public function scopeForCandidates(Builder $query, int $eventId, array $candidates): Builder
  {
    return $query->where('event_id', $eventId)
      ->whereIn('fingerprint', array_values($candidates));
  }
}
