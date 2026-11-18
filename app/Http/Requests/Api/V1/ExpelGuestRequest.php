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

    public function toDto(): ExpelGuestData
    {
        $rawTable = (string) (
            $this->route('tableId')
            ?: $this->input('tableId')
            ?: $this->input('tableCode', '')
        );

        return new ExpelGuestData(
            eventCode: trim((string) ($this->input('eventCode') ?: config('xv.default_event_code', 'XV-NATALIA'))),
            tableId: trim($rawTable),
            deviceId: $this->filled('deviceId') ? trim((string) $this->input('deviceId')) : null,
            hwFingerprint: $this->filled('hwFingerprint') ? trim((string) $this->input('hwFingerprint')) : null,
        );
    }
}
