<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Memory */
class MemoryResource extends JsonResource
{
  public function toArray(Request $request): array
  {
    $actorKey = trim((string) $request->header('X-Actor-Key', $request->query('actor_key', '')));
    $deviceId = trim((string) $request->header('X-Device-Id', $request->query('device_id', '')));

    $items = $this->relationLoaded('mediaItems')
      ? MemoryMediaItemResource::collection($this->mediaItems)->resolve($request)
      : [];
    $cover = $items[0] ?? null;
    $myChoice = $this->resolveReactionForViewer($actorKey, $deviceId);

    return [
      'id' => $this->external_id,
      'guestName' => $this->guest_name,
      'tableCode' => $this->table_code,
      'ownerDeviceId' => $this->owner_device_id ?? '',
      'ownerActorKey' => $this->owner_actor_key ?? '',
      'momentTag' => $this->moment_tag,
      'caption' => $this->caption ?? '',
      'mediaType' => $cover['mediaType'] ?? $this->media_type,
      'mediaBlobKey' => $cover['mediaBlobKey'] ?? '',
      'imageUrl' => $cover['imageUrl'] ?? '',
      'thumbUrl' => $cover['thumbUrl'] ?? '',
      'posterUrl' => $cover['posterUrl'] ?? '',
      'mediaItems' => $items,
      'status' => $this->status,
      'likes' => $this->likes_count,
      'fires' => $this->fires_count,
      'cheers' => $this->cheers_count,
      'shares' => $this->shares_count,
      'sharesCount' => $this->shares_count,
      'likedByMe' => $myChoice === 'likes',
      'firedByMe' => $myChoice === 'fires',
      'cheeredByMe' => $myChoice === 'cheers',
      'commentsCount' => $this->comments_count,
      'comments' => $this->relationLoaded('comments')
        ? MemoryCommentResource::collection($this->comments)->resolve($request)
        : [],
      'timestampMs' => $this->timestamp_ms,
      'createdAt' => optional($this->created_at)->toIso8601String(),
    ];
  }
}
