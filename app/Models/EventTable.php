<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class EventTable extends Model
{
  protected $fillable = [
    'event_id',
    'slug',
    'number',
    'code',
    'zone',
    'centerpiece',
    'capacity',
    'qr_image_path',
    'qr_svg_path',
    'access_url',
    'qr_generated_at',
  ];

  protected function casts(): array
  {
    return [
      'capacity' => 'integer',
      'qr_generated_at' => 'datetime',
    ];
  }

  public function event(): BelongsTo
  {
    return $this->belongsTo(Event::class);
  }

  public function deviceLocks(): HasMany
  {
    return $this->hasMany(DeviceLock::class)->orderBy('locked_at');
  }

  public function memories(): HasMany
  {
    return $this->hasMany(Memory::class);
  }

  public function scopeForEvent(Builder $query, int $eventId): Builder
  {
    return $query->where('event_id', $eventId);
  }

  public function scopeOrdered(Builder $query): Builder
  {
    return $query->orderBy('number');
  }

  public function scopeMatchingIdentifier(Builder $query, string $identifier): Builder
  {
    $clean = trim($identifier);
    $slug = Str::of($clean)->ascii()->lower()->replaceMatches('/[^a-z0-9]+/', '-')->trim('-')->toString();

    return $query->where(function (Builder $q) use ($clean, $slug): void {
      $q->where('slug', $slug)
        ->orWhere('code', $clean)
        ->orWhere('number', $clean);
    });
  }

  public function hasAvailableSlot(?int $currentOccupied = null): bool
  {
    $occupied = $currentOccupied ?? $this->deviceLocks()->count();
    return $occupied < $this->capacity;
  }
}
