<?php

namespace App\Http\Requests\Api\V1;

use App\DTOs\DeviceLock\ExpelGuestData;
use Illuminate\Foundation\Http\FormRequest;

class ExpelGuestRequest extends FormRequest
{
  public function authorize(): bool
  {
    return true;
  }

  public function rules(): array
  {
    return [
      'eventCode' => ['nullable', 'string', 'max:48'],
      'tableId' => [$this->route('tableId') ? 'nullable' : 'required_without:tableCode', 'string', 'max:80'],
      'tableCode' => ['nullable', 'string', 'max:80'],
      'deviceId' => ['nullable', 'string', 'max:128'],
      'hwFingerprint' => ['nullable', 'string', 'max:128'],
      'pin' => ['nullable', 'string', 'max:32'],
      'hostPin' => ['nullable', 'string', 'max:32'],
    ];
  }

  protected function prepareForValidation(): void
  {
    if (!$this->has('deviceId') && $this->deviceId() !== '') {
      $this->merge(['deviceId' => $this->deviceId()]);
    }
    if (!$this->has('hostPin') && $this->hostPin() !== '') {
      $this->merge(['hostPin' => $this->hostPin()]);
    }
    if (!$this->has('eventCode') && $this->eventCode() !== '') {
      $this->merge(['eventCode' => $this->eventCode()]);
    }
  }

  public function toDto(): ExpelGuestData
  {
    $rawTable = (string) (
      $this->route('tableId')
      ?: $this->input('tableId')
      ?: $this->input('tableCode', '')
    );

    return new ExpelGuestData(
      eventCode: $this->eventCode(),
      tableId: trim($rawTable),
      deviceId: $this->filled('deviceId') ? trim((string) $this->input('deviceId')) : ($this->deviceId() ?: null),
      hwFingerprint: $this->filled('hwFingerprint') ? trim((string) $this->input('hwFingerprint')) : null,
    );
  }
}
