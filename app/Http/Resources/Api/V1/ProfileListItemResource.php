<?php

namespace App\Http\Resources\Api\V1;

use App\Models\DeviceLock;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProfileListItemResource extends JsonResource
{
  /** @return array<string, mixed> */
  public function toArray(Request $request): array
  {
    return [
      'id' => $this->id,
      'guestName' => $this->guest_name,
      'slug' => $this->guest_name_slug ?: DeviceLock::normalizeSlug($this->guest_name),
      'actorKey' => $this->actor_key,
      'deviceId' => $this->device_id,
      'avatarUrl' => $this->avatar_url,
      'coverUrl' => $this->cover_url,
      'bio' => $this->bio,
      'location' => $this->location,
      'tableCode' => $this->table?->code,
      'tableNumber' => $this->table?->number,
      'tableZone' => $this->table?->zone,
      'joinedAt' => optional($this->locked_at ?? $this->created_at)->toIso8601String(),
    ];
  }
}
