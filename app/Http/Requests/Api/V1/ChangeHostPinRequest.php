<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class ChangeHostPinRequest extends FormRequest
{
  public function authorize(): bool
  {
    return true;
  }

  public function rules(): array
  {
    return [
      'currentPin' => ['required', 'string', 'min:3', 'max:32'],
      'newPin' => ['required', 'string', 'min:4', 'max:32'],
    ];
  }
}
