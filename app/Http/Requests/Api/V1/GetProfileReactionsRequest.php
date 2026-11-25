<?php

namespace App\Http\Requests\Api\V1;

use App\DTOs\Profile\ProfileReactionsQueryData;
use Illuminate\Foundation\Http\FormRequest;

class GetProfileReactionsRequest extends FormRequest
{
  public function authorize(): bool
  {
    return true;
  }

  public function rules(): array
  {
    return [
      'eventCode' => ['nullable', 'string', 'max:48'],
      'event_code' => ['nullable', 'string', 'max:48'],
      'page' => ['nullable', 'integer', 'min:1'],
      'per_page' => ['nullable', 'integer', 'min:1', 'max:30'],
      'perPage' => ['nullable', 'integer', 'min:1', 'max:30'],
    ];
  }

  public function toDto(?string $targetIdentifier = null): ProfileReactionsQueryData
  {
    return ProfileReactionsQueryData::fromArray([
      'targetIdentifier' => $targetIdentifier,
      'viewerDeviceId' => $this->deviceId() ?: null,
      'viewerActorKey' => $this->actorKey() ?: null,
      'eventCode' => $this->eventCode() ?: null,
      'page' => $this->query('page', 1),
      'perPage' => $this->query('per_page', $this->query('perPage', 7)),
    ]);
  }
}
