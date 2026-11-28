<?php

namespace App\Http\Requests\Api\V1;

use App\DTOs\Memory\MemoryFilterData;
use App\DTOs\Shared\ViewerContextData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListMemoriesRequest extends FormRequest
{
  public function authorize(): bool
  {
    return true;
  }

  public function rules(): array
  {
    return [
      'event_code' => ['nullable', 'string', 'max:48'],
      'status' => ['nullable', 'string', Rule::in(['all', 'approved', 'pending', 'rejected', 'hidden'])],
      'include_all_statuses' => ['nullable', 'boolean'],
      'owner_device_id' => ['nullable', 'string', 'max:128'],
      'category' => ['nullable', 'string', Rule::in(['all', 'comments', 'videos', 'albums'])],
      'search' => ['nullable', 'string', 'max:120'],
      'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
      'cursor'   => ['nullable', 'string', 'max:500'],
    ];
  }

  public function toDto(): MemoryFilterData
  {
    return new MemoryFilterData(
      eventCode: $this->eventCode(),
      status: $this->filled('status') ? (string) $this->input('status') : null,
      includeAllStatuses: $this->boolean('include_all_statuses'),
      ownerDeviceId: $this->filled('owner_device_id') ? trim((string) $this->input('owner_device_id')) : null,
      category: $this->filled('category') ? trim((string) $this->input('category')) : null,
      search: $this->filled('search') ? trim((string) $this->input('search')) : null,
      perPage: min(max((int) $this->input('per_page', 12), 1), 50),
      cursor: $this->filled('cursor') ? trim((string) $this->input('cursor')) : null,
    );
  }

  public function viewerContext(): ViewerContextData
  {
    return ViewerContextData::fromRequest($this);
  }
}
