<?php

namespace App\Http\Requests\Api\V1;

use App\DTOs\Memory\CreateMemoryData;
use Illuminate\Foundation\Http\FormRequest;

class StoreMemoryRequest extends FormRequest
{
  public function authorize(): bool
  {
    return true;
  }

  public function rules(): array
  {
    return [
      'id' => ['nullable', 'string', 'max:128'],
      'externalId' => ['nullable', 'string', 'max:128'],
      'deviceLockId' => ['nullable', 'integer'],
      'eventCode' => ['nullable', 'string', 'max:48'],
      'guestName' => ['required', 'string', 'min:1', 'max:80'],
      'tableCode' => ['required', 'string', 'min:1', 'max:80'],
      'ownerDeviceId' => ['nullable', 'string', 'max:128'],
      'ownerActorKey' => ['nullable', 'string', 'max:128'],
      'momentTag' => ['nullable', 'string', 'max:60'],
      'caption' => ['nullable', 'string', 'max:600'],
      'files' => ['required', 'array', 'min:1', 'max:30'],
      'files.*' => [
        'file',
        'mimetypes:image/jpeg,image/png,image/webp,image/heic,image/heif,image/x-heic,video/mp4,video/webm,video/quicktime,video/3gpp,video/x-msvideo,video/x-matroska,application/octet-stream',
        'max:102400',
      ],
      'posters' => ['nullable', 'array'],
      'posters.*' => ['nullable', 'file', 'max:5120'],
    ];
  }

  public function toDto(): CreateMemoryData
  {
    return new CreateMemoryData(
      eventCode: trim((string) $this->input('eventCode', config('xv.default_event_code', 'XV-NATALIA'))),
      guestName: trim((string) $this->input('guestName')),
      tableCode: trim((string) $this->input('tableCode')),
      ownerDeviceId: $this->filled('ownerDeviceId') ? trim((string) $this->input('ownerDeviceId')) : null,
      ownerActorKey: $this->filled('ownerActorKey') ? trim((string) $this->input('ownerActorKey')) : null,
      momentTag: trim((string) ($this->input('momentTag') ?: 'Fiesta en Vivo')),
      caption: trim((string) $this->input('caption', '')),
      files: $this->file('files', []),
      posters: $this->file('posters', []),
      externalId: $this->filled('id') ? trim((string) $this->input('id')) : ($this->filled('externalId') ? trim((string) $this->input('externalId')) : null),
      deviceLockId: $this->input('deviceLockId') ? (int) $this->input('deviceLockId') : null,
    );
  }
}
