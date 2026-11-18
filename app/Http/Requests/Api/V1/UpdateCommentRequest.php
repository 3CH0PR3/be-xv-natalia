<?php

namespace App\Http\Requests\Api\V1;

use App\DTOs\Comment\UpdateCommentData;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCommentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'text' => ['required', 'string', 'min:1', 'max:500'],
        ];
    }

    public function toDto(string $memoryExternalId, string $commentExternalId): UpdateCommentData
    {
        return new UpdateCommentData(
            memoryExternalId: $memoryExternalId,
            commentExternalId: $commentExternalId,
            text: trim((string) $this->input('text')),
        );
    }
}
