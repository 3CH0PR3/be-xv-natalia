<?php

namespace App\Http\Requests\Api\V1;

use App\DTOs\Profile\ListProfilesData;
use Illuminate\Foundation\Http\FormRequest;

class ListProfilesRequest extends FormRequest
{
  public function authorize(): bool
  {
    return true;
  }

  public function rules(): array
  {
    return [
      'search' => ['nullable', 'string', 'max:120'],
      'q' => ['nullable', 'string', 'max:120'],
      'limit' => ['nullable', 'integer', 'min:1', 'max:30'],
      'per_page' => ['nullable', 'integer', 'min:1', 'max:30'],
      'page' => ['nullable', 'integer', 'min:1'],
      'event_code' => ['nullable', 'string', 'max:48'],
    ];
  }

  public function toDto(): ListProfilesData
  {
    return ListProfilesData::fromArray([
      'search' => $this->query('search') ?: $this->query('q'),
      'limit' => $this->query('limit', $this->query('per_page', 10)),
      'page' => $this->query('page', 1),
      'eventCode' => $this->query('event_code') ?: config('xv.default_event_code', 'XV-NATALIA'),
      'viewerDeviceId' => $this->deviceId() ?: null,
      'viewerActorKey' => $this->actorKey() ?: null,
    ]);
  }
}
