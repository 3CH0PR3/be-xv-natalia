<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class VerifyHostPinRequest extends FormRequest
{
  public function authorize(): bool
  {
    return true;
  }

  protected function prepareForValidation(): void
  {
    $pin = $this->input('pin')
      ?: $this->input('hostPin')
      ?: $this->input('host_pin')
      ?: $this->header('X-Host-Pin')
      ?: $this->header('Host-Pin');

    if ($pin !== null) {
      $this->merge(['pin' => trim((string) $pin)]);
    }
  }

  public function rules(): array
  {
    return [
      'pin' => ['required', 'string', 'max:32'],
    ];
  }
}
