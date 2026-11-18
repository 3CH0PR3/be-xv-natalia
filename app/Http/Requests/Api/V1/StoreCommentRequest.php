<?php

namespace App\Http\Requests\Api\V1;

use App\DTOs\Comment\CreateCommentData;
use Illuminate\Foundation\Http\FormRequest;

class StoreCommentRequest extends FormRequest
{
  public function authorize(): bool
  {
    return true;
  }

  public function rules(): array
  {
    return [
      'id' => ['nullable', 'string', 'max:128'],
      'author' => ['required', 'string', 'min:1', 'max:80'],
      'table' => ['required', 'string', 'min:1', 'max:80'],
      'text' => ['required', 'string', 'min:1', 'max:500'],
      'ownerDeviceId' => ['nullable', 'string', 'max:128'],
      'ownerActorKey' => ['nullable', 'string', 'max:128'],
    ];
  }

  public function toDto(string $memoryExternalId): CreateCommentData
  {
    return new CreateCommentData(
      memoryExternalId: $memoryExternalId,
      commentExternalId: $this->filled('id') ? trim((string) $this->input('id')) : null,
      author: trim((string) $this->input('author')),
      table: trim((string) $this->input('table')),
      text: trim((string) $this->input('text')),
      ownerDeviceId: $this->filled('ownerDeviceId') ? trim((string) $this->input('ownerDeviceId')) : null,
      ownerActorKey: $this->filled('ownerActorKey') ? trim((string) $this->input('ownerActorKey')) : null,
    );
  }
}
