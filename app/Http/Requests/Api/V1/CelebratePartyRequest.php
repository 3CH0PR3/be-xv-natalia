<?php

namespace App\Http\Requests\Api\V1;

use App\DTOs\Event\TogglePartyCelebrateData;
use App\Models\DeviceLock;
use Illuminate\Foundation\Http\FormRequest;

class CelebratePartyRequest extends FormRequest
{
  public function authorize(): bool
  {
    return true;
  }

  public function rules(): array
  {
    return [
      'actorKey' => ['nullable', 'string', 'max:128'],
      'deviceId' => ['nullable', 'string', 'max:128'],
      'eventCode' => ['nullable', 'string', 'max:48'],
      'delta' => ['nullable', 'numeric'],
    ];
  }

  public function toDto(): TogglePartyCelebrateData
  {
    $deviceId = trim((string) ($this->input('deviceId') ?: $this->header('X-Device-Id') ?: ''));
    $actorKey = trim((string) ($this->input('actorKey') ?: $this->header('X-Actor-Key') ?: ''));

    if ($actorKey === '' && $deviceId !== '') {
      $actorKey = (string) (DeviceLock::where('device_id', $deviceId)->value('actor_key') ?: $deviceId);
    }

    return new TogglePartyCelebrateData(
      eventCode: trim((string) ($this->input('eventCode') ?: config('xv.default_event_code', 'XV-NATALIA'))),
      actorKey: $actorKey ?: 'anonymous',
      deviceId: $deviceId ?: null,
    );
  }
}
