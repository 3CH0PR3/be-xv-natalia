<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\AppRelease */
class AppReleaseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'version' => $this->version,
            'versionOrder' => $this->version_order,
            'type' => $this->type,
            'date' => $this->date_label,
            'title' => $this->title,
            'highlights' => $this->highlights ?? [],
            'commitSha' => $this->commit_sha ?? '',
            'buildId' => $this->build_id ?? '',
            'appliedAtMs' => $this->applied_at_ms,
        ];
    }
}
