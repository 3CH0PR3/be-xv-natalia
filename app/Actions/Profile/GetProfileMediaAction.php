<?php

namespace App\Actions\Profile;

use App\Models\DeviceLock;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class GetProfileMediaAction
{
  public function execute(string $id, string $type = 'avatar'): BinaryFileResponse|StreamedResponse
  {
    $trimmed = trim($id);
    $lock = DeviceLock::where(function (Builder $q) use ($trimmed): void {
      $q->orWhere('id', $trimmed)
        ->orWhere('actor_key', $trimmed)
        ->orWhere('device_id', $trimmed)
        ->orWhere('guest_name_slug', DeviceLock::normalizeSlug($trimmed))
        ->orWhere('guest_name', $trimmed);
    })->first();

    if (!$lock) {
      throw new NotFoundHttpException('Perfil no encontrado.');
    }

    $relativePath = $type === 'cover' ? $lock->cover_path : $lock->avatar_path;
    if (!$relativePath) {
      throw new NotFoundHttpException("El perfil no cuenta con imagen de {$type}.");
    }

    $diskName = (string) config('xv.media_disk', 'public');
    $driver = (string) (config("filesystems.disks.{$diskName}.driver") ?? 'local');
    $disk = Storage::disk($diskName);

    if (!$disk->exists($relativePath)) {
      throw new NotFoundHttpException("El archivo de {$type} no existe en el almacenamiento.");
    }

    $headers = [
      'Access-Control-Allow-Origin' => '*',
      'Access-Control-Allow-Methods' => 'GET, OPTIONS',
      'Access-Control-Allow-Headers' => '*',
      'Cache-Control' => 'public, max-age=86400, stale-while-revalidate=604800',
    ];

    if ($driver !== 'local') {
      $mime = $disk->mimeType($relativePath) ?: 'image/jpeg';
      return response()->stream(static function () use ($disk, $relativePath): void {
        $stream = $disk->readStream($relativePath);
        if ($stream === false) {
          return;
        }
        try {
          fpassthru($stream);
        } finally {
          if (is_resource($stream)) {
            fclose($stream);
          }
        }
      }, 200, array_merge($headers, ['Content-Type' => $mime]));
    }

    $fullPath = $disk->path($relativePath);
    $detectedMime = is_file($fullPath) ? (mime_content_type($fullPath) ?: null) : null;
    $mime = $detectedMime ?: 'image/jpeg';

    return response()->file($fullPath, array_merge($headers, ['Content-Type' => $mime]));
  }
}
