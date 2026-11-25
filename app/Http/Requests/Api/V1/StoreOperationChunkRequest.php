<?php

namespace App\Http\Requests\Api\V1;

use App\DTOs\UploadOperation\StoreOperationChunkData;
use Illuminate\Foundation\Http\FormRequest;

class StoreOperationChunkRequest extends FormRequest
{
  public function authorize(): bool
  {
    return true;
  }

  public function rules(): array
  {
    return [
      'chunkIndex' => ['required', 'integer', 'min:0', 'max:100000'],
      'chunk' => ['required', 'file', 'max:25600'],
    ];
  }

  public function toDto(string $operationKey, string $fileKey): StoreOperationChunkData
  {
    return StoreOperationChunkData::fromArray([
      'operationKey' => $operationKey,
      'fileKey' => $fileKey,
      'chunkIndex' => $this->validated('chunkIndex'),
      'chunkFile' => $this->file('chunk'),
      'eventCode' => $this->eventCode(),
      'deviceId' => $this->deviceId() ?: null,
      'actorKey' => $this->actorKey() ?: null,
      'hostPin' => $this->hostPin() ?: null,
    ]);
  }
}
