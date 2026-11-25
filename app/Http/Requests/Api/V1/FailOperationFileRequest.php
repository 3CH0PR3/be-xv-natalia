<?php

namespace App\Http\Requests\Api\V1;

use App\DTOs\UploadOperation\FailOperationFileData;
use Illuminate\Foundation\Http\FormRequest;

class FailOperationFileRequest extends FormRequest
{
  public function authorize(): bool
  {
    return true;
  }

  public function rules(): array
  {
    return [
      'errorCode' => ['nullable', 'string', 'max:64'],
      'errorMessage' => ['required', 'string', 'min:1', 'max:1000'],
    ];
  }

  public function toDto(string $operationKey, string $fileKey): FailOperationFileData
  {
    return FailOperationFileData::fromArray([
      'operationKey' => $operationKey,
      'fileKey' => $fileKey,
      'errorCode' => $this->validated('errorCode'),
      'errorMessage' => $this->validated('errorMessage'),
      'eventCode' => $this->eventCode(),
      'deviceId' => $this->deviceId() ?: null,
      'actorKey' => $this->actorKey() ?: null,
      'hostPin' => $this->hostPin() ?: null,
    ]);
  }
}
