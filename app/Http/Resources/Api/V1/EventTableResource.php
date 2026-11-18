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
        'joinedAt' => optional($lock->locked_at ?? $lock->created_at)->toIso8601String(),
      ])->values()->all()
      : [];

    $resolveStorageUrl = static function (?string $path) use ($request): string {
      if (!$path) {
        return '';
      }
      $base = $request->getSchemeAndHttpHost();
      return rtrim($base, '/') . '/storage/' . ltrim($path, '/');
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
      'qrImageUrl' => $resolveStorageUrl($this->qr_image_path),
      'qrSvgUrl' => $resolveStorageUrl($this->qr_svg_path),
      'accessUrl' => $this->access_url ?? '',
      'qrGeneratedAt' => $this->qr_generated_at?->toIso8601String(),
    ];
  }
}
