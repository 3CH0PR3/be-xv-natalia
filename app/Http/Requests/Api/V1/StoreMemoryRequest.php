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
        'max:102400',
        static function (string $attribute, mixed $value, \Closure $fail): void {
          if (!($value instanceof \Illuminate\Http\UploadedFile)) {
            $fail('El archivo subido no es válido.');
            return;
          }

          $extension = strtolower((string) ($value->getClientOriginalExtension() ?: ($value->extension() ?: '')));
          $mime = strtolower((string) ($value->getMimeType() ?: 'application/octet-stream'));

          // 1. Prohibir GIFs explícitamente tanto por MIME como por extensión
          if ($mime === 'image/gif' || $extension === 'gif') {
            $fail('El formato GIF no está permitido. Solo se aceptan fotografías y videos.');
            return;
          }

          // 2. Prohibir documentos, PDFs, hojas de cálculo, archivos ejecutables y comprimidos
          $forbiddenExtensions = [
            'pdf', 'doc', 'docx', 'odt', 'rtf', 'txt',
            'xls', 'xlsx', 'csv', 'ods',
            'ppt', 'pptx', 'odp',
            'zip', 'rar', '7z', 'tar', 'gz', 'bz2',
            'exe', 'bat', 'cmd', 'sh', 'bin', 'msi',
            'php', 'js', 'html', 'htm', 'css', 'json', 'xml',
          ];
          if (in_array($extension, $forbiddenExtensions, true)) {
            $fail('Solo se permiten fotos y videos. No se permiten documentos, hojas de cálculo ni archivos comprimidos.');
            return;
          }

          if (
            str_starts_with($mime, 'application/pdf') ||
            str_starts_with($mime, 'application/msword') ||
            str_starts_with($mime, 'application/vnd.') ||
            str_starts_with($mime, 'text/')
          ) {
            $fail('Solo se permiten fotos y videos. No se permiten documentos ni hojas de cálculo.');
            return;
          }

          // 3. Extensiones y MIMEs válidos para fotografías y videos
          $validImageExtensions = [
            'jpg', 'jpeg', 'jfif', 'pjpeg', 'pjp', 'png', 'apng', 'webp', 'avif',
            'heic', 'heif', 'bmp', 'svg', 'tif', 'tiff', 'ico', 'cur', 'jxl',
            'dng', 'raw', 'cr2', 'nef', 'arw',
          ];
          $validVideoExtensions = [
            'mp4', 'mov', 'webm', '3gp', '3g2', 'm4v', 'mkv', 'avi', 'ogv', 'ogg', 'ts', 'mts', 'm2ts', 'wmv', 'flv',
          ];

          $isAllowedMime = str_starts_with($mime, 'image/') || str_starts_with($mime, 'video/');
          $isAllowedExtension = in_array($extension, $validImageExtensions, true) || in_array($extension, $validVideoExtensions, true);

          if (!$isAllowedMime && !$isAllowedExtension) {
            $fail('El tipo de archivo no es compatible. Sube una fotografía o video.');
          }
        },
      ],
      'posters' => ['nullable', 'array'],
      'posters.*' => ['nullable', 'file', 'max:5120'],
    ];
  }

  public function toDto(): CreateMemoryData
  {
    $ownerDeviceId = trim((string) ($this->input('ownerDeviceId') ?: $this->input('deviceId') ?: $this->header('X-Device-Id', '')));
    $ownerActorKey = trim((string) ($this->input('ownerActorKey') ?: $this->input('actorKey') ?: $this->header('X-Actor-Key', '')));

    return new CreateMemoryData(
      eventCode: trim((string) $this->input('eventCode', config('xv.default_event_code', 'XV-NATALIA'))),
      guestName: trim((string) $this->input('guestName')),
      tableCode: trim((string) $this->input('tableCode')),
      ownerDeviceId: $ownerDeviceId !== '' ? $ownerDeviceId : null,
      ownerActorKey: $ownerActorKey !== '' ? $ownerActorKey : null,
      momentTag: trim((string) ($this->input('momentTag') ?: 'Fiesta en Vivo')),
      caption: trim((string) $this->input('caption', '')),
      files: $this->file('files', []),
      posters: $this->file('posters', []),
      externalId: $this->filled('id') ? trim((string) $this->input('id')) : ($this->filled('externalId') ? trim((string) $this->input('externalId')) : null),
      deviceLockId: $this->input('deviceLockId') ? (int) $this->input('deviceLockId') : null,
    );
  }
}
