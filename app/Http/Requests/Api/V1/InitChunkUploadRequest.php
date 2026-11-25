<?php

namespace App\Http\Requests\Api\V1;

use App\DTOs\ChunkUpload\InitChunkUploadData;
use Illuminate\Foundation\Http\FormRequest;

class InitChunkUploadRequest extends FormRequest
{
  public function authorize(): bool
  {
    return true;
  }

  public function rules(): array
  {
    return [
      'filename' => ['required', 'string', 'min:1', 'max:255'],
      'mimeType' => ['nullable', 'string', 'max:120'],
      'totalSize' => ['required', 'integer', 'min:1'],
      'totalChunks' => ['required', 'integer', 'min:1', 'max:100000'],
    ];
  }

  public function toDto(): InitChunkUploadData
  {
    return InitChunkUploadData::fromArray([
      ...$this->validated(),
      'actorKey' => $this->actorKey() ?: null,
      'deviceId' => $this->deviceId() ?: null,
    ]);
  }
}
