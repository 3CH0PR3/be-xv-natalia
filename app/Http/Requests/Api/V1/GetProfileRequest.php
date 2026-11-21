<?php

namespace App\Http\Requests\Api\V1;

use App\DTOs\Profile\ViewProfileData;
use Illuminate\Foundation\Http\FormRequest;

class GetProfileRequest extends FormRequest
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
    ];
  }

  public function toDto(?string $targetIdentifier = null): ViewProfileData
  {
    return new ViewProfileData(
      targetIdentifier: $targetIdentifier,
      viewerDeviceId: $this->deviceId() ?: null,
      viewerActorKey: $this->actorKey() ?: null,
      eventCode: $this->eventCode() ?: null,
    );
  }
}
