<?php

namespace App\Http\Requests\Api\V1;

use App\DTOs\Profile\UpdateProfileData;
use Illuminate\Foundation\Http\FormRequest;

class UpdateProfileRequest extends FormRequest
{
  public function authorize(): bool
  {
    return true;
  }

  public function rules(): array
  {
    $imageValidator = static function (string $attribute, mixed $value, \Closure $fail): void {
      if (!($value instanceof \Illuminate\Http\UploadedFile)) {
        return;
      }

      $extension = strtolower((string) ($value->getClientOriginalExtension() ?: ($value->extension() ?: '')));
      $mime = strtolower((string) ($value->getMimeType() ?: 'application/octet-stream'));

      // 1. Prohibir GIFs
      if ($mime === 'image/gif' || $extension === 'gif') {
        $fail('El formato GIF no está permitido. Solo se aceptan fotografías.');
        return;
      }

      // 2. Prohibir documentos, PDFs, hojas de cálculo, ejecutables y comprimidos
      $forbiddenExtensions = [
        'pdf', 'doc', 'docx', 'odt', 'rtf', 'txt',
        'xls', 'xlsx', 'csv', 'ods',
        'ppt', 'pptx', 'odp',
        'zip', 'rar', '7z', 'tar', 'gz', 'bz2',
        'exe', 'bat', 'cmd', 'sh', 'bin', 'msi',
        'php', 'js', 'html', 'htm', 'css', 'json', 'xml',
      ];
      if (in_array($extension, $forbiddenExtensions, true)) {
        $fail('Solo se permiten imágenes. No se permiten documentos ni hojas de cálculo.');
        return;
      }

      if (
        str_starts_with($mime, 'application/pdf') ||
        str_starts_with($mime, 'application/msword') ||
        str_starts_with($mime, 'application/vnd.') ||
        str_starts_with($mime, 'text/')
      ) {
        $fail('Solo se permiten imágenes. No se permiten documentos ni hojas de cálculo.');
        return;
      }

      // 3. Extensiones y MIMEs válidos para imágenes
      $validImageExtensions = [
        'jpg', 'jpeg', 'jfif', 'pjpeg', 'pjp', 'png', 'apng', 'webp', 'avif',
        'heic', 'heif', 'bmp', 'svg', 'tif', 'tiff', 'ico', 'cur', 'jxl',
        'dng', 'raw', 'cr2', 'nef', 'arw',
      ];

      $isAllowedMime = str_starts_with($mime, 'image/');
      $isAllowedExtension = in_array($extension, $validImageExtensions, true);

      if (!$isAllowedMime && !$isAllowedExtension) {
        $fail('El tipo de archivo no es compatible. Sube una fotografía válida.');
      }
    };

    return [
      'guestName' => [
        'nullable',
        'string',
        'min:3',
        'max:80',
        static function (string $attribute, mixed $value, \Closure $fail): void {
          if ($value === null || trim((string) $value) === '') return;
          $trimmed = trim((string) $value);
          if (preg_match('/[\p{So}\p{Sk}\p{Cs}\x{1F300}-\x{1F9FF}\x{2600}-\x{26FF}\x{2700}-\x{27BF}]/u', $trimmed)) {
            $fail('Por favor ingresa un nombre real sin emojis.');
            return;
          }
          if (preg_match('/[0-9*#@$%^&+=~`|<>\\/{}\[\]_!¡?¿;:]/u', $trimmed)) {
            $fail('Por favor ingresa un nombre real sin números ni símbolos especiales.');
            return;
          }
          $words = preg_split('/\s+/u', $trimmed, -1, PREG_SPLIT_NO_EMPTY);
          if (count($words) < 2) {
            $fail('Por favor ingresa tu nombre y apellido completo (mínimo dos palabras).');
          }
        },
      ],
      'bio' => ['nullable', 'string', 'max:255'],
      'location' => ['nullable', 'string', 'max:100'],
      'avatar' => ['nullable', 'file', 'max:10240', $imageValidator],
      'cover' => ['nullable', 'file', 'max:15360', $imageValidator],
    ];
  }

  public function toDto(): UpdateProfileData
  {
    $ownerDeviceId = trim((string) ($this->input('ownerDeviceId') ?: $this->deviceId()));
    $ownerActorKey = trim((string) ($this->input('ownerActorKey') ?: $this->actorKey()));

    return new UpdateProfileData(
      guestName: $this->filled('guestName') ? trim((string) $this->input('guestName')) : null,
      bio: $this->has('bio') ? trim((string) $this->input('bio')) : null,
      location: $this->has('location') ? trim((string) $this->input('location')) : null,
      avatar: $this->file('avatar'),
      cover: $this->file('cover'),
      ownerDeviceId: $ownerDeviceId !== '' ? $ownerDeviceId : null,
      ownerActorKey: $ownerActorKey !== '' ? $ownerActorKey : null,
    );
  }
}
