<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ModerationMetricsResource extends JsonResource
{
  public function toArray(Request $request): array
  {
    return [
      'publishedCount' => (int) ($this['publishedCount'] ?? 0),
      'totalFiles' => (int) ($this['totalFiles'] ?? 0),
      'totalComments' => (int) ($this['totalComments'] ?? 0),
      'totalMemories' => (int) ($this['totalMemories'] ?? 0),
      'approvedMemories' => (int) ($this['approvedMemories'] ?? 0),
      'connectedGuests' => (int) ($this['connectedGuests'] ?? 0),
      'partyCelebrations' => (int) ($this['partyCelebrations'] ?? 0),
    ];
  }
}
