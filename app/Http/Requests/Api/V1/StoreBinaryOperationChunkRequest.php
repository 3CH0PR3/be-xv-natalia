<?php

namespace App\Http\Requests\Api\V1;

use App\DTOs\UploadOperation\StoreOperationChunkData;
use Illuminate\Foundation\Http\FormRequest;

class StoreBinaryOperationChunkRequest extends FormRequest
{
  public function authorize(): bool
  {
    return true;
  }

  public function rules(): array
  {
    return [];
  }

  public function toDto(string $operationKey, string $fileKey): StoreOperationChunkData
  {
    $chunkIndex = (int) ($this->header('X-Chunk-Index') ?? $this->query('chunkIndex', -1));

    return StoreOperationChunkData::fromArray([
      'operationKey' => $operationKey,
      'fileKey' => $fileKey,
      'chunkIndex' => $chunkIndex,
      'rawBinaryBody' => app()->runningUnitTests() ? $this->getContent() : null,
      'eventCode' => $this->eventCode(),
      'deviceId' => $this->deviceId() ?: null,
      'actorKey' => $this->actorKey() ?: null,
      'hostPin' => $this->hostPin() ?: null,
    ]);
  }
}
