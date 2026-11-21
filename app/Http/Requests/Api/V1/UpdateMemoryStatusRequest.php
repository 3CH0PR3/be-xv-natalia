<?php

namespace App\Http\Requests\Api\V1;

use App\DTOs\Moderation\UpdateMemoryStatusData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMemoryStatusRequest extends FormRequest
{
  public function authorize(): bool
  {
    return true;
  }

  public function rules(): array
  {
    return [
      'status' => ['required', Rule::in(['approved', 'pending', 'rejected', 'hidden'])],
    ];
  }

  public function toDto(string $externalId): UpdateMemoryStatusData
  {
    return new UpdateMemoryStatusData(
      externalId: $externalId,
      status: (string) $this->input('status'),
    );
  }
}
