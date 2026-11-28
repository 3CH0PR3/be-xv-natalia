<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasVersion7Uuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class GuestNotification extends Model
{
  use HasVersion7Uuids;
  protected $fillable = [
    'event_code',
    'owner_device_id',
    'owner_actor_key',
    'type',
    'actor_name',
    'actor_key',
    'memory_external_id',
    'memory_caption',
    'media_thumb_url',
    'extra',
    'is_read',
  ];

  protected $casts = [
    'is_read' => 'boolean',
  ];

  public function scopeForOwner(Builder $query, string $deviceId, string $actorKey): Builder
  {
    return $query->where(function ($q) use ($deviceId, $actorKey): void {
      $q->where('owner_device_id', $deviceId)
        ->orWhere('owner_actor_key', $actorKey);
    });
  }

  public function scopeForEvent(Builder $query, string $eventCode): Builder
  {
    return $query->where('event_code', $eventCode);
  }

  public function scopeUnread(Builder $query): Builder
  {
    return $query->where('is_read', false);
  }
}
