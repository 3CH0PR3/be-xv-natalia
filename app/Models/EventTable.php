<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasVersion7Uuids;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class EventTable extends Model
{
  use HasVersion7Uuids;
  protected $fillable = [
    'event_id',
    'slug',
    'number',
    'code',
    'capacity',
    'qr_token',
    'qr_image_path',
    'qr_svg_path',
    'access_url',
    'qr_generated_at',
  ];

  public static function generateSecureQrToken(): string
  {
    return 'qrt_' . bin2hex(random_bytes(16));
  }

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

  public function scopeForEvent(Builder $query, int|string $eventId): Builder
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
    $paddedNumber = null;
    if (preg_match('/^(?:mesa[\s\-_]*)?0*(\d{1,2})\b/i', $clean, $matches)) {
      $paddedNumber = str_pad($matches[1], 2, '0', STR_PAD_LEFT);
    }

    return $query->where(function (Builder $q) use ($clean, $slug, $paddedNumber): void {
      $q->where('slug', $slug)
        ->orWhere('code', $clean)
        ->orWhere('number', $clean);
      if ($paddedNumber !== null) {
        $q->orWhere('number', $paddedNumber);
      }
    });
  }

  public function hasAvailableSlot(?int $currentOccupied = null): bool
  {
    $occupied = $currentOccupied ?? $this->deviceLocks()->count();
    return $occupied < $this->capacity;
  }
}
