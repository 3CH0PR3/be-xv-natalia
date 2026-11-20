<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Memory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read Memory $resource
 */
class MemoryShareResource extends JsonResource
{
  /**
   * @return array<string, mixed>
   */
  public function toArray(Request $request): array
  {
    $shares = $this->resource->shares_count ?? 0;

    return [
      'id' => $this->resource->external_id,
      'externalId' => $this->resource->external_id,
      'shares' => $shares,
      'sharesCount' => $shares,
    ];
  }
}
