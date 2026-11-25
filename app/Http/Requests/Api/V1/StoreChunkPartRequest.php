<?php

namespace App\Http\Requests\Api\V1;

use App\DTOs\ChunkUpload\StoreChunkPartData;
use Illuminate\Foundation\Http\FormRequest;

class StoreChunkPartRequest extends FormRequest
{
  public function authorize(): bool
  {
    return true;
  }

  public function rules(): array
  {
    return [
      'chunkIndex' => ['required', 'integer', 'min:0', 'max:100000'],
      'chunk' => ['required', 'file', 'max:20480'],
    ];
  }

  public function toDto(string $uploadKey): StoreChunkPartData
  {
    return StoreChunkPartData::fromArray([
      'uploadKey' => $uploadKey,
      'chunkIndex' => $this->validated('chunkIndex'),
      'chunkFile' => $this->file('chunk'),
      'actorKey' => $this->actorKey() ?: null,
      'deviceId' => $this->deviceId() ?: null,
    ]);
  }
}
