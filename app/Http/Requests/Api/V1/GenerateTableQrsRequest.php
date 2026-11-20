<?php

namespace App\Http\Requests\Api\V1;

use App\DTOs\Table\GenerateTableQrsData;
use Illuminate\Foundation\Http\FormRequest;

class GenerateTableQrsRequest extends FormRequest
{
  public function authorize(): bool
  {
    return true;
  }

  public function rules(): array
  {
    return [
      'baseUrl' => ['nullable', 'string', 'max:500'],
      'tableCount' => ['nullable', 'integer', 'min:1', 'max:50'],
      'eventCode' => ['nullable', 'string', 'max:50'],
    ];
  }

  public function toDto(): GenerateTableQrsData
  {
    return GenerateTableQrsData::fromRequest($this);
  }
}
