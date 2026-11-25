<?php

namespace App\Http\Requests\Api\V1;

use App\DTOs\UploadOperation\AttachOperationPosterData;
use Illuminate\Foundation\Http\FormRequest;

class AttachOperationPosterRequest extends FormRequest
{
  public function authorize(): bool
  {
    return true;
  }

  public function rules(): array
  {
    return [
      'poster' => ['required', 'file', 'max:10240'],
    ];
  }

  public function toDto(string $operationKey, string $fileKey): AttachOperationPosterData
  {
    return AttachOperationPosterData::fromArray([
      'operationKey' => $operationKey,
      'fileKey' => $fileKey,
      'posterFile' => $this->file('poster'),
      'eventCode' => $this->eventCode(),
      'deviceId' => $this->deviceId() ?: null,
      'actorKey' => $this->actorKey() ?: null,
      'hostPin' => $this->hostPin() ?: null,
    ]);
  }
}
