<?php

namespace App\Http\Requests\Api\V1;

use App\DTOs\Memory\CreateMemoryData;
use App\Support\Validation\MediaFilePolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

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
      'deviceLockId' => ['nullable', 'string', 'max:64'],
      'eventCode' => ['nullable', 'string', 'max:48'],
      'guestName' => ['required', 'string', 'min:1', 'max:80'],
      'tableCode' => ['required', 'string', 'min:1', 'max:80'],
      'ownerDeviceId' => ['nullable', 'string', 'max:128'],
      'ownerActorKey' => ['nullable', 'string', 'max:128'],
      'momentTag' => ['nullable', 'string', 'max:60'],
      'caption' => ['nullable', 'string', 'max:600'],
      'files' => ['nullable', 'array', 'max:30'],
      'files.*' => [
        'file',
        static function (string $attribute, mixed $value, \Closure $fail): void {
          $error = MediaFilePolicy::validateUploadedFile($value);
          if ($error !== null) {
            $fail($error);
          }
        },
      ],
      'uploadedKeys' => ['nullable', 'array', 'max:30'],
      'uploadedKeys.*' => ['string', 'max:128'],
      'fileOrder' => ['nullable', 'array', 'max:30'],
      'fileOrder.*' => ['string', 'max:140'],
      'posters' => ['nullable', 'array'],
      'posters.*' => ['nullable', 'file', 'max:10240'],
    ];
  }

  public function withValidator(Validator $validator): void
  {
    $validator->after(function (Validator $v): void {
      $files = $this->file('files', []);
      $uploadedKeys = $this->input('uploadedKeys', []);
      $filesCount = is_array($files) ? count($files) : 0;
      $keysCount = is_array($uploadedKeys) ? count($uploadedKeys) : 0;
      $total = $filesCount + $keysCount;

      if ($total < 1) {
        $v->errors()->add('files', 'Debes adjuntar al menos una fotografía o video.');
      } elseif ($total > 30) {
        $v->errors()->add('files', 'Solo puedes subir hasta 30 archivos por recuerdo.');
      }
    });
  }

  public function toDto(): CreateMemoryData
  {
    $ownerDeviceId = trim((string) ($this->input('ownerDeviceId') ?: $this->deviceId()));
    $ownerActorKey = trim((string) ($this->input('ownerActorKey') ?: $this->actorKey()));
    $rawKeys = $this->input('uploadedKeys', []);
    $rawOrder = $this->input('fileOrder', []);

    return new CreateMemoryData(
      eventCode: $this->eventCode(),
      guestName: trim((string) $this->input('guestName')),
      tableCode: trim((string) $this->input('tableCode')),
      ownerDeviceId: $ownerDeviceId !== '' ? $ownerDeviceId : null,
      ownerActorKey: $ownerActorKey !== '' ? $ownerActorKey : null,
      momentTag: trim((string) ($this->input('momentTag') ?: 'Fiesta en Vivo')),
      caption: trim((string) $this->input('caption', '')),
      files: $this->file('files', []) ?: [],
      posters: $this->file('posters', []) ?: [],
      externalId: $this->filled('id') ? trim((string) $this->input('id')) : ($this->filled('externalId') ? trim((string) $this->input('externalId')) : null),
      deviceLockId: $this->input('deviceLockId') ? trim((string) $this->input('deviceLockId')) : null,
      uploadedKeys: is_array($rawKeys) ? array_values(array_filter(array_map('strval', $rawKeys))) : [],
      fileOrder: is_array($rawOrder) ? array_values(array_map('strval', $rawOrder)) : [],
    );
  }
}
