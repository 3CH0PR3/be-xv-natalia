<?php

namespace App\Http\Requests\Api\V1;

use App\DTOs\Memory\IncrementShareData;
use Illuminate\Foundation\Http\FormRequest;

class IncrementMemoryShareRequest extends FormRequest
{
  public function authorize(): bool
  {
    return true;
  }

  public function rules(): array
  {
    return [
      'actorKey' => ['nullable', 'string', 'max:255'],
      'deviceId' => ['nullable', 'string', 'max:255'],
      'deviceLockId' => ['nullable', 'integer'],
    ];
  }

  public function toDto(string $externalId): IncrementShareData
  {
    $actorKey = trim((string) ($this->actorKey() ?: $this->input('actorKey', '')));
    $deviceId = trim((string) ($this->deviceId() ?: $this->input('deviceId', '')));
    $deviceLockId = $this->input('deviceLockId') ? (int) $this->input('deviceLockId') : null;

    return new IncrementShareData(
      externalId: $externalId,
      actorKey: $actorKey !== '' ? $actorKey : null,
      deviceId: $deviceId !== '' ? $deviceId : null,
      deviceLockId: $deviceLockId,
    );
  }
}
