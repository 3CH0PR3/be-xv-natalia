<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\MemoryMediaItem */
class MemoryMediaItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $urls = $this->publicUrls();

        return [
            'id' => $this->external_id,
            'mediaType' => $this->media_type,
            'mediaBlobKey' => $this->media_blob_key ?? '',
            'imageUrl' => $urls['imageUrl'],
            'thumbUrl' => $urls['thumbUrl'],
            'posterUrl' => $urls['posterUrl'],
        ];
    }
}
