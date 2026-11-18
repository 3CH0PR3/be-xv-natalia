<?php

namespace App\Http\Requests\Api\V1;

use App\DTOs\Comment\ToggleCommentLikeData;
use Illuminate\Foundation\Http\FormRequest;

class ToggleCommentLikeRequest extends FormRequest
{
  public function authorize(): bool
  {
    return true;
  }

  public function rules(): array
  {
    return [
      'actorKey' => ['required', 'string', 'max:128'],
      'deviceId' => ['nullable', 'string', 'max:128'],
      'liked' => ['nullable', 'boolean'],
    ];
  }

  public function toDto(string $memoryExternalId, string $commentExternalId): ToggleCommentLikeData
  {
    return new ToggleCommentLikeData(
      memoryExternalId: $memoryExternalId,
      commentExternalId: $commentExternalId,
      actorKey: trim((string) $this->input('actorKey')),
      deviceId: $this->filled('deviceId') ? trim((string) $this->input('deviceId')) : null,
      liked: $this->has('liked') ? $this->boolean('liked') : null,
    );
  }
}
