<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class FailOperationFileRequest extends FormRequest
{
  public function authorize(): bool
  {
    return true;
  }

  public function rules(): array
  {
    return [
      'errorCode' => ['nullable', 'string', 'max:64'],
      'errorMessage' => ['required', 'string', 'min:1', 'max:1000'],
    ];
  }
}
