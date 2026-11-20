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

    $firstMedia = $memory->mediaItems->first();
    if (!$firstMedia) {
      throw new NotFoundHttpException('El recuerdo no contiene archivos multimedia.');
    }

    $diskName = $firstMedia->disk ?: config('xv.media_disk', 'public');
    $disk = Storage::disk($diskName);

    $relativeCandidate = $firstMedia->thumb_path ?: $firstMedia->file_path;
    $isVideoWithPoster = $firstMedia->media_type === 'video' && !empty($firstMedia->poster_path);
    $relativeCandidate = $isVideoWithPoster ? $firstMedia->poster_path : $relativeCandidate;

    if (!$relativeCandidate || !$disk->exists($relativeCandidate)) {
      throw new NotFoundHttpException('Archivo de imagen no disponible en el almacenamiento.');
    }

    $fullPath = $disk->path($relativeCandidate);
    $mime = $firstMedia->mime_type ?: (mime_content_type($fullPath) ?: 'image/jpeg');

    return response()->file($fullPath, [
      'Content-Type' => $mime,
      'Access-Control-Allow-Origin' => '*',
      'Access-Control-Allow-Methods' => 'GET, OPTIONS',
      'Access-Control-Allow-Headers' => '*',
      'Cache-Control' => 'public, max-age=86400, stale-while-revalidate=604800',
    ]);
  }
}
