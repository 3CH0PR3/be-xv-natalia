<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MemoryCommentLikeResource extends JsonResource
{
  public function toArray(Request $request): array
  {
    return [
      'likes' => $this->resource['likes'],
      'liked' => $this->resource['liked'],
    ];
  }
}
