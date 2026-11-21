<?php

namespace App\Http\Requests\Api\V1;

use App\DTOs\Shared\ViewerContextData;
use Illuminate\Foundation\Http\FormRequest;

class GetMemoryRequest extends FormRequest
{
  public function authorize(): bool
  {
    return true;
  }

  public function rules(): array
  {
    return [];
  }

  public function toDto(): ViewerContextData
  {
    return ViewerContextData::fromRequest($this);
  }
}
