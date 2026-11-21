<?php

namespace App\Http\Requests\Api\V1;

use App\DTOs\Moderation\VerifyHostPinData;
use Illuminate\Foundation\Http\FormRequest;

class VerifyHostPinRequest extends FormRequest
{
  public function authorize(): bool
  {
    return true;
  }

  public function rules(): array
  {
    return [
      'pin' => ['nullable', 'string', 'max:32'],
      'hostPin' => ['nullable', 'string', 'max:32'],
    ];
  }

  public function toPin(): string
  {
    return $this->hostPin();
  }

  public function toDto(): VerifyHostPinData
  {
    return new VerifyHostPinData(
      pin: $this->toPin()
    );
  }
}
