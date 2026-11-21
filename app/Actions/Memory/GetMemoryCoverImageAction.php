<?php

namespace App\Actions\Memory;

use App\Models\Memory;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class GetMemoryCoverImageAction
{
  public function execute(string $externalId): BinaryFileResponse
  {
    $memory = Memory::with('mediaItems')
      ->where('external_id', $externalId)
      ->first();

    if (!$memory) {
      throw new NotFoundHttpException('Recuerdo no encontrado.');
    }

    $firstMedia = $memory->mediaItems->first(function ($m) {
      return $m->media_type === 'image' || !empty($m->poster_path);
    }) ?: $memory->mediaItems->first();

    if (!$firstMedia) {
      throw new NotFoundHttpException('El recuerdo no contiene archivos multimedia.');
    }

    $diskName = $firstMedia->disk ?: config('xv.media_disk', 'public');
    $disk = Storage::disk($diskName);

    $isVideo = $firstMedia->media_type === 'video';
    $relativeCandidate = $isVideo ? $firstMedia->poster_path : ($firstMedia->thumb_path ?: $firstMedia->file_path);

    if (!$relativeCandidate || !$disk->exists($relativeCandidate)) {
      throw new NotFoundHttpException('Archivo de imagen de portada no disponible en el almacenamiento.');
    }

    $fullPath = $disk->path($relativeCandidate);
    $detectedMime = is_file($fullPath) ? (mime_content_type($fullPath) ?: null) : null;
    $mime = $detectedMime ?: 'image/jpeg';

    return response()->file($fullPath, [
      'Content-Type' => $mime,
      'Access-Control-Allow-Origin' => '*',
      'Access-Control-Allow-Methods' => 'GET, OPTIONS',
      'Access-Control-Allow-Headers' => '*',
      'Cache-Control' => 'public, max-age=86400, stale-while-revalidate=604800',
    ]);
  }
}
