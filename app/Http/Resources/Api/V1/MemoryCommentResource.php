<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\MemoryComment */
class MemoryCommentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $actorKey = trim((string) $request->header('X-Actor-Key', $request->query('actor_key', '')));
        $deviceId = trim((string) $request->header('X-Device-Id', $request->query('device_id', '')));

        $likedBy = $this->relationLoaded('commentLikes')
            ? $this->commentLikes->pluck('actor_key')->values()->all()
            : [];

        return [
            'id' => $this->external_id,
            'author' => $this->author,
            'table' => $this->table_label,
            'text' => $this->text,
            'likes' => $this->likes_count,
            'likedBy' => $likedBy,
            'likedByMe' => $this->isLikedByViewer($actorKey, $deviceId),
            'ownerDeviceId' => $this->owner_device_id ?? '',
            'ownerActorKey' => $this->owner_actor_key ?? '',
            'createdAtMs' => $this->created_at_ms,
        ];
    }
}
