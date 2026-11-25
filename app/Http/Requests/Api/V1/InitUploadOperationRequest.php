<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class InitUploadOperationRequest extends FormRequest
{
  public function authorize(): bool
  {
    return true;
  }

  public function rules(): array
  {
    return [
      'operationKey' => ['nullable', 'string', 'max:128'],
      'memoryExternalId' => ['nullable', 'string', 'max:128'],
      'eventCode' => ['nullable', 'string', 'max:48'],
      'guestName' => ['nullable', 'string', 'min:1', 'max:80'],
      'tableCode' => ['nullable', 'string', 'min:1', 'max:80'],
      'momentTag' => ['nullable', 'string', 'max:60'],
      'caption' => ['nullable', 'string', 'max:600'],
      'files' => ['required', 'array', 'min:1', 'max:100'],
      'files.*.fileKey' => ['nullable', 'string', 'max:128'],
      'files.*.originalName' => ['required', 'string', 'min:1', 'max:255'],
      'files.*.mimeType' => ['nullable', 'string', 'max:120'],
      'files.*.byteSize' => ['required', 'integer', 'min:0'],
      'files.*.chunkSize' => ['nullable', 'integer', 'min:1024', 'max:33554432'],
      'files.*.totalChunks' => ['required', 'integer', 'min:1', 'max:100000'],
      'files.*.sortOrder' => ['nullable', 'integer', 'min:0', 'max:500'],
    ];
  }
}
