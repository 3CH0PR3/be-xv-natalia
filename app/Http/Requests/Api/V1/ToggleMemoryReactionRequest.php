<?php

namespace App\Http\Requests\Api\V1;

use App\DTOs\Memory\ToggleReactionData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ToggleMemoryReactionRequest extends FormRequest
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
      'choice' => ['nullable', 'string', Rule::in(['', 'likes', 'fires', 'cheers'])],
    ];
  }

  public function toDto(string $externalId): ToggleReactionData
  {
    return new ToggleReactionData(
      externalId: $externalId,
      actorKey: trim((string) $this->input('actorKey')),
      deviceId: $this->filled('deviceId') ? trim((string) $this->input('deviceId')) : null,
      choice: (string) ($this->input('choice') ?? ''),
    );
  }
}
