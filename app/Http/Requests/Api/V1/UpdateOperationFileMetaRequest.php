<?php

namespace App\Http\Requests\Api\V1;

use App\DTOs\UploadOperation\UpdateOperationFileMetaData;
use Illuminate\Foundation\Http\FormRequest;

class UpdateOperationFileMetaRequest extends FormRequest
{
  public function authorize(): bool
  {
    return true;
  }

  public function rules(): array
  {
    return [
      'originalName' => ['nullable', 'string', 'min:1', 'max:255'],
      'mimeType' => ['nullable', 'string', 'max:120'],
      'byteSize' => ['required', 'integer', 'min:1'],
      'chunkSize' => ['required', 'integer', 'min:1024', 'max:33554432'],
      'totalChunks' => ['required', 'integer', 'min:1', 'max:100000'],
    ];
  }

  public function toDto(string $operationKey, string $fileKey): UpdateOperationFileMetaData
  {
    return UpdateOperationFileMetaData::fromArray([
      'operationKey' => $operationKey,
      'fileKey' => $fileKey,
      'meta' => $this->validated(),
      'eventCode' => $this->eventCode(),
      'deviceId' => $this->deviceId() ?: null,
      'actorKey' => $this->actorKey() ?: null,
      'hostPin' => $this->hostPin() ?: null,
    ]);
  }
}
