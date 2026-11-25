<?php

namespace App\Actions\ChunkUpload;

use App\Exceptions\DomainRuleException;
use App\Support\Storage\ChunkUploadStoragePolicy;
use App\Support\Validation\MediaFilePolicy;
use Illuminate\Support\Facades\Storage;

final readonly class AssembleChunkUploadAction
{
  public function execute(
    string $uploadKey,
    string $targetDisk,
    string $destDir,
    string $blobKey,
    ?string $actorKey,
    ?string $deviceId,
  ): array {
    $meta = ChunkUploadStoragePolicy::readAndAuthorizeMeta($uploadKey, $actorKey, $deviceId);
    $totalChunks = (int) ($meta['totalChunks'] ?? 0);
    $chunkDisk = Storage::disk(ChunkUploadStoragePolicy::CHUNK_DISK);

    for ($i = 0; $i < $totalChunks; $i += 1) {
      if (!$chunkDisk->exists(ChunkUploadStoragePolicy::chunkFilePath($uploadKey, $i))) {
        throw new DomainRuleException("Falta el fragmento #{$i} para completar el archivo.", 422);
      }
    }

    $rawExt = strtolower((string) ($meta['extension'] ?? ''));
    $metaMime = strtolower((string) ($meta['mimeType'] ?? 'application/octet-stream'));
    $isVideo = MediaFilePolicy::isVideoDescriptor($rawExt, $metaMime);
    $ext = $rawExt !== '' ? $rawExt : ($isVideo ? 'mp4' : 'jpg');
    $relativeDestPath = trim($destDir, '/') . '/' . "{$blobKey}.{$ext}";

    $targetStorage = Storage::disk($targetDisk);
    $targetStorage->makeDirectory($destDir);
    $targetDriver = config("filesystems.disks.{$targetDisk}.driver");

    [$detectedMime, $byteSize] = $targetDriver === 'local'
      ? $this->assembleToLocal($chunkDisk, $targetStorage, $uploadKey, $totalChunks, $relativeDestPath, $metaMime, $meta)
      : $this->assembleToRemote($chunkDisk, $targetStorage, $uploadKey, $totalChunks, $relativeDestPath, $metaMime, $meta);

    $chunkDisk->deleteDirectory(ChunkUploadStoragePolicy::uploadDir($uploadKey));

    $finalMime = strtolower((string) $detectedMime);

    return [
      'filePath' => $relativeDestPath,
      'mimeType' => $finalMime,
      'byteSize' => (int) $byteSize,
      'isVideo' => MediaFilePolicy::isVideoDescriptor($ext, $finalMime),
    ];
  }

  private function assembleToLocal($chunkDisk, $targetStorage, string $uploadKey, int $totalChunks, string $relativeDestPath, string $metaMime, array $meta): array
  {
    $fullDestPath = $targetStorage->path($relativeDestPath);
    $this->writeChunksToFile($chunkDisk, $uploadKey, $totalChunks, $fullDestPath, 'No se pudo crear el archivo final en almacenamiento.');

    return [
      @mime_content_type($fullDestPath) ?: $metaMime,
      @filesize($fullDestPath) ?: (int) ($meta['totalSize'] ?? 0),
    ];
  }

  private function assembleToRemote($chunkDisk, $targetStorage, string $uploadKey, int $totalChunks, string $relativeDestPath, string $metaMime, array $meta): array
  {
    $assembledTempFull = $chunkDisk->path(ChunkUploadStoragePolicy::uploadDir($uploadKey) . '/assembled.part');
    $this->writeChunksToFile($chunkDisk, $uploadKey, $totalChunks, $assembledTempFull, 'No se pudo ensamblar el archivo temporal.');

    $detectedMime = @mime_content_type($assembledTempFull) ?: $metaMime;
    $byteSize = @filesize($assembledTempFull) ?: (int) ($meta['totalSize'] ?? 0);
    $readStream = fopen($assembledTempFull, 'rb');
    try {
      $targetStorage->put($relativeDestPath, $readStream);
    } finally {
      if (is_resource($readStream)) {
        fclose($readStream);
      }
    }

    return [$detectedMime, $byteSize];
  }

  private function writeChunksToFile($chunkDisk, string $uploadKey, int $totalChunks, string $destFullPath, string $openError): void
  {
    $outHandle = fopen($destFullPath, 'wb');
    if ($outHandle === false) {
      throw new DomainRuleException($openError, 500);
    }

    try {
      for ($i = 0; $i < $totalChunks; $i += 1) {
        $inHandle = fopen($chunkDisk->path(ChunkUploadStoragePolicy::chunkFilePath($uploadKey, $i)), 'rb');
        if ($inHandle === false) {
          throw new DomainRuleException("No se pudo leer el fragmento #{$i}.", 500);
        }
        try {
          stream_copy_to_stream($inHandle, $outHandle);
        } finally {
          fclose($inHandle);
        }
      }
    } finally {
      fclose($outHandle);
    }
  }
}
