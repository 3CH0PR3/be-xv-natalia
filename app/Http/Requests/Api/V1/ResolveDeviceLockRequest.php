<?php

namespace App\Http\Requests\Api\V1;

use App\DTOs\DeviceLock\ResolveDeviceLockData;
use Illuminate\Foundation\Http\FormRequest;

class ResolveDeviceLockRequest extends FormRequest
{
  public function authorize(): bool
  {
    return true;
  }

  public function rules(): array
  {
    return [
      'eventCode' => ['nullable', 'string', 'max:48'],
      'deviceId' => ['nullable', 'string', 'max:128'],
      'hwFingerprint' => ['nullable', 'string', 'max:128'],
      'candidateFingerprints' => ['nullable', 'array', 'max:10'],
      'candidateFingerprints.*' => ['string', 'max:128'],
      'hardwareSummary' => ['nullable', 'array'],
    ];
  }

  protected function prepareForValidation(): void
  {
    if (!$this->has('deviceId') && $this->deviceId() !== '') {
      $this->merge(['deviceId' => $this->deviceId()]);
    }
    if (!$this->has('eventCode') && $this->eventCode() !== '') {
      $this->merge(['eventCode' => $this->eventCode()]);
    }
  }

  public function toDto(): ResolveDeviceLockData
  {
    $candidates = array_values(array_filter(array_unique(array_merge(
      [$this->input('hwFingerprint')],
      $this->input('candidateFingerprints', [])
    ))));

    return new ResolveDeviceLockData(
      eventCode: $this->eventCode(),
      deviceId: $this->deviceId(),
      hwFingerprint: $this->filled('hwFingerprint') ? trim((string) $this->input('hwFingerprint')) : null,
      candidateFingerprints: $candidates,
      hardwareSummary: $this->input('hardwareSummary'),
    );
  }
}
