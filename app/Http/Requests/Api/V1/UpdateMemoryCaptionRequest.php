<?php

namespace App\Http\Requests\Api\V1;

use App\DTOs\Memory\UpdateCaptionData;
use App\DTOs\Shared\ViewerContextData;
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

  public function viewerContext(): ViewerContextData
  {
    return ViewerContextData::fromRequest($this);
  }
}
