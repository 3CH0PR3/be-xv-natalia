<?php

namespace App\Http\Requests\Api\V1;

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
}
