<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PartyCelebrationResource extends JsonResource
{
  public function toArray(Request $request): array
  {
    return [
      'count' => $this->resource['count'],
      'reacted' => $this->resource['reacted'],
      'reactedByMe' => $this->resource['reactedByMe'] ?? $this->resource['reacted'],
      'eventCode' => $this->resource['eventCode'],
    ];
  }
}
