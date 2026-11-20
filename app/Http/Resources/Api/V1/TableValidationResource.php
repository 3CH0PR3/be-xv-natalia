<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TableValidationResource extends JsonResource
{
  /**
   * @return array<string, mixed>
   */
  public function toArray(Request $request): array
  {
    $table = $this->resource['table'] ?? null;

    return [
      'isValid' => (bool) ($this->resource['isValid'] ?? false),
      'code' => $this->resource['code'] ?? '',
      'table' => $table ? new EventTableResource($table) : null,
    ];
  }
}
