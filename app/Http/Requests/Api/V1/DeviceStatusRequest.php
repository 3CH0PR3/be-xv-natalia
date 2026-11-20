<?php

namespace App\Http\Requests\Api\V1;

use App\DTOs\DeviceLock\DeviceStatusData;
use Illuminate\Foundation\Http\FormRequest;

class DeviceStatusRequest extends FormRequest
{
  public function authorize(): bool
  {
    return true;
  }

  public function rules(): array
  {
    return [
      'eventCode' => ['nullable', 'string', 'max:50'],
    ];
  }

  public function toDto(string $deviceId): DeviceStatusData
  {
    return DeviceStatusData::fromRequest($this, $deviceId);
  }
}
