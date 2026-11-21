<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MemoryReactionResource extends JsonResource
{
  public function toArray(Request $request): array
  {
    return [
      'memoryId' => $this->resource['memoryId'],
      'likes' => $this->resource['likes'],
      'fires' => $this->resource['fires'],
      'cheers' => $this->resource['cheers'],
      'choice' => $this->resource['choice'],
    ];
  }
}
