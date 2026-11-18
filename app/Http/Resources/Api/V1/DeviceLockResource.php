<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\DeviceLock */
class DeviceLockResource extends JsonResource
{
    public function __construct(
        $resource,
        private readonly bool $restoredByFingerprint = false,
    ) {
        parent::__construct($resource);
    }

    public function toArray(Request $request): array
    {
        return [
            'deviceId' => $this->device_id,
            'hwFingerprint' => $this->hw_fingerprint ?? '',
            'fingerprintCandidates' => $this->fingerprint_candidates ?? [],
            'hardwareSummary' => $this->hardware_summary,
            'tableId' => $this->table?->slug ?? '',
            'tableCode' => $this->table?->code ?? '',
            'guestName' => $this->guest_name,
            'actorKey' => $this->actor_key,
            'lockedAt' => optional($this->locked_at)->toIso8601String(),
            'updatedAt' => optional($this->updated_at)->toIso8601String(),
            'restoredByFingerprint' => $this->restoredByFingerprint,
        ];
    }
}
