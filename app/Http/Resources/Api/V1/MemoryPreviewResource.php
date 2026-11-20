<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MemoryPreviewResource extends JsonResource
{
  public static $wrap = null;

  /**
   * @return array<string, mixed>
   */
  public function toArray(Request $request): array
  {
    $data = is_array($this->resource) ? $this->resource : (array) $this->resource;

    return [
      'external_id' => $data['external_id'] ?? '',
      'title' => $data['title'] ?? '',
      'description' => $data['description'] ?? '',
      'image_url' => $data['image_url'] ?? '',
    ];
  }
}
