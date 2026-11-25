<?php

namespace App\Http\Requests\Api\V1;

use App\DTOs\UploadOperation\UploadOperationActorData;
use Illuminate\Foundation\Http\FormRequest;

class UploadOperationContextRequest extends FormRequest
{
  public function authorize(): bool
  {
    return true;
  }

  public function rules(): array
  {
    return [];
  }

  public function toDto(?string $operationKey = null): UploadOperationActorData
  {
    return UploadOperationActorData::fromArray([
      'operationKey' => $operationKey,
      'eventCode' => $this->eventCode(),
      'deviceId' => $this->deviceId() ?: null,
      'actorKey' => $this->actorKey() ?: null,
      'hostPin' => $this->hostPin() ?: null,
    ]);
  }
}
