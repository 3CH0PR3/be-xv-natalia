<?php

namespace App\Http\Requests\Api\V1;

use App\DTOs\DeviceLock\CheckinGuestData;
use Illuminate\Foundation\Http\FormRequest;

class CheckinGuestRequest extends FormRequest
{
  public function authorize(): bool
  {
    return true;
  }

  public function rules(): array
  {
    return [
      'eventCode' => ['nullable', 'string', 'max:48'],
      'tableCode' => ['required', 'string', 'max:80'],
      'guestName' => ['required', 'string', 'min:1', 'max:80'],
      'deviceId' => ['required', 'string', 'max:128'],
      'hwFingerprint' => ['nullable', 'string', 'max:128'],
      'candidateFingerprints' => ['nullable', 'array', 'max:10'],
      'candidateFingerprints.*' => ['string', 'max:128'],
      'hardwareSummary' => ['nullable', 'array'],
    ];
  }

  public function toDto(): CheckinGuestData
  {
    $candidates = array_values(array_filter(array_unique(array_merge(
      [$this->input('hwFingerprint')],
      $this->input('candidateFingerprints', [])
    ))));

    return new CheckinGuestData(
      eventCode: trim((string) ($this->input('eventCode') ?: config('xv.default_event_code', 'XV-NATALIA'))),
      tableCode: trim((string) $this->input('tableCode')),
      guestName: trim((string) $this->input('guestName')),
      deviceId: trim((string) $this->input('deviceId')),
      hwFingerprint: $this->filled('hwFingerprint') ? trim((string) $this->input('hwFingerprint')) : null,
      candidateFingerprints: $candidates,
      hardwareSummary: $this->input('hardwareSummary'),
    );
  }
}
