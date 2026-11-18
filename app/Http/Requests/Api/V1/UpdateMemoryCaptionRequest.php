<?php

namespace App\Http\Requests\Api\V1;

use App\DTOs\Memory\UpdateCaptionData;
use Illuminate\Foundation\Http\FormRequest;

class UpdateMemoryCaptionRequest extends FormRequest
{
  public function authorize(): bool
  {
    return true;
  }

  public function rules(): array
  {
    return [
      'caption' => ['required', 'string', 'min:1', 'max:600'],
    ];
  }

  public function toDto(string $externalId): UpdateCaptionData
  {
    return new UpdateCaptionData(
      externalId: $externalId,
      caption: trim((string) $this->input('caption')),
    );
  }
}
