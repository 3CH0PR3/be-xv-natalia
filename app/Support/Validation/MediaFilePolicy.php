<?php

namespace App\Support\Validation;

use Illuminate\Http\UploadedFile;

final class MediaFilePolicy
{
  private const FORBIDDEN_EXTENSIONS = [
    'pdf', 'doc', 'docx', 'odt', 'rtf', 'txt',
    'xls', 'xlsx', 'csv', 'ods',
    'ppt', 'pptx', 'odp',
    'zip', 'rar', '7z', 'tar', 'gz', 'bz2',
    'exe', 'bat', 'cmd', 'sh', 'bin', 'msi',
    'php', 'js', 'html', 'htm', 'css', 'json', 'xml',
  ];

  private const VALID_IMAGE_EXTENSIONS = [
    'jpg', 'jpeg', 'jfif', 'pjpeg', 'pjp', 'png', 'apng', 'webp', 'avif',
    'heic', 'heif', 'bmp', 'svg', 'tif', 'tiff', 'ico', 'cur', 'jxl',
    'dng', 'raw', 'cr2', 'nef', 'arw',
  ];

  private const VALID_VIDEO_EXTENSIONS = [
    'mp4', 'mov', 'webm', '3gp', '3g2', 'm4v', 'mkv', 'avi', 'ogv', 'ogg', 'ts', 'mts', 'm2ts', 'wmv', 'flv',
  ];

  public static function validateUploadedFile(mixed $value): ?string
  {
    if (!($value instanceof UploadedFile)) {
      return 'El archivo subido no es válido.';
    }

    $extension = strtolower((string) ($value->getClientOriginalExtension() ?: ($value->extension() ?: '')));
    $mime = strtolower((string) ($value->getMimeType() ?: 'application/octet-stream'));

    return self::validateDescriptor($extension, $mime);
  }

  public static function validateFilenameAndMime(string $filename, string $mime): ?string
  {
    $extension = strtolower((string) pathinfo($filename, PATHINFO_EXTENSION));
    $cleanMime = strtolower(trim($mime)) ?: 'application/octet-stream';

    return self::validateDescriptor($extension, $cleanMime);
  }

  public static function validateDescriptor(string $extension, string $mime): ?string
  {
    if ($mime === 'image/gif' || $extension === 'gif') {
      return 'El formato GIF no está permitido. Solo se aceptan fotografías y videos.';
    }

    if ($extension !== '' && in_array($extension, self::FORBIDDEN_EXTENSIONS, true)) {
      return 'Solo se permiten fotos y videos. No se permiten documentos, hojas de cálculo ni archivos comprimidos.';
    }

    if (
      str_starts_with($mime, 'application/pdf') ||
      str_starts_with($mime, 'application/msword') ||
      str_starts_with($mime, 'application/vnd.') ||
      str_starts_with($mime, 'text/')
    ) {
      return 'Solo se permiten fotos y videos. No se permiten documentos ni hojas de cálculo.';
    }

    $isAllowedMime = str_starts_with($mime, 'image/') || str_starts_with($mime, 'video/');
    $isAllowedExtension = in_array($extension, self::VALID_IMAGE_EXTENSIONS, true) || in_array($extension, self::VALID_VIDEO_EXTENSIONS, true);

    if (!$isAllowedMime && !$isAllowedExtension) {
      return 'El tipo de archivo no es compatible. Sube una fotografía o video.';
    }

    return null;
  }

  public static function isVideoDescriptor(string $extension, string $mime): bool
  {
    $cleanMime = strtolower(trim($mime));
    $cleanExt = strtolower(trim($extension));

    return str_starts_with($cleanMime, 'video/') || in_array($cleanExt, self::VALID_VIDEO_EXTENSIONS, true);
  }
}
