<?php

namespace App\Http\Resources\Api\V1;

use App\Models\DeviceLock;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\EventTable */
class EventTableResource extends JsonResource
{
  public function toArray(Request $request): array
  {
    $guests = $this->relationLoaded('deviceLocks')
      ? $this->deviceLocks->map(static fn (DeviceLock $lock): array => [
        'deviceId' => $lock->device_id,
        'hwFingerprint' => $lock->hw_fingerprint ?? '',
        'guestName' => $lock->guest_name,
        'actorKey' => $lock->actor_key,
        'avatarUrl' => $lock->avatar_url,
        'joinedAt' => optional($lock->locked_at ?? $lock->created_at)->toIso8601String(),
      ])->values()->all()
      : [];

    $resolveStorageUrl = static function (?string $path, ?string $token) use ($request): string {
      if (!$path) {
        return '';
      }
      $base = $request->getSchemeAndHttpHost();
      $url = rtrim($base, '/') . '/storage/' . ltrim($path, '/');
      $suffix = $token ? '?v=' . substr($token, 4, 8) : '';
      return $url . $suffix;
    };

    return [
      'id' => $this->slug,
      'number' => $this->number,
      'code' => $this->code,
      'zone' => $this->zone,
      'centerpiece' => $this->centerpiece,
      'capacity' => min(10, (int) ($this->capacity ?: 10)),
      'guests' => $guests,
      'guestsConnected' => count($guests),
      'qrImageUrl' => $resolveStorageUrl($this->qr_image_path, $this->qr_token),
      'qrSvgUrl' => $resolveStorageUrl($this->qr_svg_path, $this->qr_token),
      'qrToken' => $this->qr_token ?? '',
      'accessUrl' => $this->access_url ?? '',
      'qrGeneratedAt' => $this->qr_generated_at?->toIso8601String(),
    ];
  }
}
