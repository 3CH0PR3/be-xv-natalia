<?php

namespace App\Http\Resources\Api\V1;

use App\Models\DeviceLock;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DeviceStatusResource extends JsonResource
{
  /**
   * @return array<string, mixed>
   */
  public function toArray(Request $request): array
  {
    /** @var DeviceLock|null $lock */
    $lock = $this->resource;
    $exists = (bool) $lock;

    return [
      'exists' => $exists,
      'expelled' => !$exists,
      'confirmedMissingOnServer' => !$exists,
      'lock' => $lock ? new DeviceLockResource($lock) : null,
    ];
  }
}
