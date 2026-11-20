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
      'qrToken' => ['nullable', 'string', 'max:128'],
      'token' => ['nullable', 'string', 'max:128'],
      'guestName' => [
        'required',
        'string',
        'min:3',
        'max:80',
        function ($attribute, $value, $fail) {
          $clean = trim((string) $value);
          if ($clean === '') {
            $fail('Debes ingresar tu nombre y apellido.');
            return;
          }
          if (!preg_match('/^[a-zA-ZáéíóúÁÉÍÓÚñÑüÜ\s]+$/u', $clean)) {
            $fail('Debe ser un nombre real (solo letras, sin números, emojis ni símbolos).');
            return;
          }
          $parts = preg_split('/\s+/', $clean, -1, PREG_SPLIT_NO_EMPTY);
          if (count($parts) < 2) {
            $fail('Debes ingresar al menos nombre y apellido (mínimo dos palabras).');
            return;
          }
          foreach ($parts as $part) {
            if (mb_strlen($part, 'UTF-8') < 2) {
              $fail('Cada parte del nombre debe tener al menos 2 letras.');
              return;
            }
          }
        },
      ],
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
      qrToken: $this->filled('qrToken') ? trim((string) $this->input('qrToken')) : ($this->filled('token') ? trim((string) $this->input('token')) : null),
    );
  }
}
