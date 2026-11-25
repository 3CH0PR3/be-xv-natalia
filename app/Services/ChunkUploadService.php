<?php

namespace App\Services;

use App\Exceptions\DomainRuleException;
use App\Support\Validation\MediaFilePolicy;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ChunkUploadService
{
  private const CHUNK_DISK = 'local';
  private const BASE_DIR = 'chunk_uploads';
  private const DEFAULT_CHUNK_SIZE = 5242880;

  public function initUpload(
    string $filename,
    string $mimeType,
    int $totalSize,
    int $totalChunks,
    ?string $actorKey,
    ?string $deviceId
  ): array {
    $policyError = MediaFilePolicy::validateFilenameAndMime($filename, $mimeType);
    if ($policyError !== null) {
      throw new DomainRuleException($policyError, 422);
    }

    $uploadKey = 'ul_' . Str::lower(Str::random(24));
    $extension = strtolower((string) pathinfo($filename, PATHINFO_EXTENSION));
    $meta = [
      'uploadKey' => $uploadKey,
      'filename' => $filename,
      'extension' => $extension,
      'mimeType' => strtolower(trim($mimeType)) ?: 'application/octet-stream',
      'totalSize' => $totalSize,
      'totalChunks' => $totalChunks,
      'actorKey' => $actorKey,
      'deviceId' => $deviceId,
      'createdAt' => time(),
    ];

    $disk = Storage::disk(self::CHUNK_DISK);
    $disk->makeDirectory($this->chunksDir($uploadKey));
    $disk->put($this->metaPath($uploadKey), json_encode($meta, JSON_THROW_ON_ERROR));

    return [
      'uploadKey' => $uploadKey,
      'chunkSize' => self::DEFAULT_CHUNK_SIZE,
    ];
  }

  public function storeChunk(
    string $uploadKey,
    int $chunkIndex,
    UploadedFile $chunkFile,
    ?string $actorKey,
    ?string $deviceId
  ): array {
    $meta = $this->readAndAuthorizeMeta($uploadKey, $actorKey, $deviceId);
    $totalChunks = (int) ($meta['totalChunks'] ?? 0);

    if ($chunkIndex < 0 || $chunkIndex >= $totalChunks) {
      throw new DomainRuleException('Índice de fragmento fuera de rango.', 422);
    }

    $disk = Storage::disk(self::CHUNK_DISK);
    $chunkPath = $this->chunkFilePath($uploadKey, $chunkIndex);
    $stream = fopen($chunkFile->getRealPath(), 'rb');
    if ($stream === false) {
      throw new DomainRuleException('No se pudo leer el fragmento recibido.', 500);
    }

    try {
      $disk->put($chunkPath, $stream);
    } finally {
      if (is_resource($stream)) {
        fclose($stream);
      }
    }

    return [
      'uploadKey' => $uploadKey,
      'chunkIndex' => $chunkIndex,
      'received' => true,
      'bytesReceived' => (int) ($chunkFile->getSize() ?: 0),
    ];
  }

  public function assembleToTargetDisk(
    string $uploadKey,
    string $targetDisk,
    string $destDir,
    string $blobKey,
    ?string $actorKey,
    ?string $deviceId
  ): array {
    $meta = $this->readAndAuthorizeMeta($uploadKey, $actorKey, $deviceId);
    $totalChunks = (int) ($meta['totalChunks'] ?? 0);
    $chunkDisk = Storage::disk(self::CHUNK_DISK);

    for ($i = 0; $i < $totalChunks; $i += 1) {
      if (!$chunkDisk->exists($this->chunkFilePath($uploadKey, $i))) {
        throw new DomainRuleException("Falta el fragmento #{$i} para completar el archivo.", 422);
      }
    }

    $rawExt = strtolower((string) ($meta['extension'] ?? ''));
    $metaMime = strtolower((string) ($meta['mimeType'] ?? 'application/octet-stream'));
    $isVideo = MediaFilePolicy::isVideoDescriptor($rawExt, $metaMime);
    $ext = $rawExt !== '' ? $rawExt : ($isVideo ? 'mp4' : 'jpg');
    $targetFilename = "{$blobKey}.{$ext}";
    $relativeDestPath = trim($destDir, '/') . '/' . $targetFilename;

    $targetStorage = Storage::disk($targetDisk);
    $targetStorage->makeDirectory($destDir);
    $targetDriver = config("filesystems.disks.{$targetDisk}.driver");

    if ($targetDriver === 'local') {
      $fullDestPath = $targetStorage->path($relativeDestPath);
      $outHandle = fopen($fullDestPath, 'wb');
      if ($outHandle === false) {
        throw new DomainRuleException('No se pudo crear el archivo final en almacenamiento.', 500);
      }

      try {
        for ($i = 0; $i < $totalChunks; $i += 1) {
          $chunkFullPath = $chunkDisk->path($this->chunkFilePath($uploadKey, $i));
          $inHandle = fopen($chunkFullPath, 'rb');
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

      $detectedMime = @mime_content_type($fullDestPath) ?: $metaMime;
      $byteSize = @filesize($fullDestPath) ?: (int) ($meta['totalSize'] ?? 0);
    } else {
      $assembledTempRel = $this->uploadDir($uploadKey) . '/assembled.part';
      $assembledTempFull = $chunkDisk->path($assembledTempRel);
      $outHandle = fopen($assembledTempFull, 'wb');
      if ($outHandle === false) {
        throw new DomainRuleException('No se pudo ensamblar el archivo temporal.', 500);
      }

      try {
        for ($i = 0; $i < $totalChunks; $i += 1) {
          $chunkFullPath = $chunkDisk->path($this->chunkFilePath($uploadKey, $i));
          $inHandle = fopen($chunkFullPath, 'rb');
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
    }

    $chunkDisk->deleteDirectory($this->uploadDir($uploadKey));

    $finalMime = strtolower((string) $detectedMime);
    $finalIsVideo = MediaFilePolicy::isVideoDescriptor($ext, $finalMime);

    return [
      'filePath' => $relativeDestPath,
      'mimeType' => $finalMime,
      'byteSize' => (int) $byteSize,
      'isVideo' => $finalIsVideo,
    ];
  }

  private function readAndAuthorizeMeta(string $uploadKey, ?string $actorKey, ?string $deviceId): array
  {
    if (!preg_match('/^ul_[a-z0-9]{16,40}$/', $uploadKey)) {
      throw new DomainRuleException('Identificador de carga inválido.', 422);
    }

    $disk = Storage::disk(self::CHUNK_DISK);
    $metaPath = $this->metaPath($uploadKey);
    if (!$disk->exists($metaPath)) {
      throw new DomainRuleException('La sesión de carga por fragmentos no existe o expiró.', 404);
    }

    $raw = $disk->get($metaPath);
    $meta = json_decode((string) $raw, true);
    if (!is_array($meta)) {
      throw new DomainRuleException('Metadatos de carga corruptos.', 422);
    }

    $ownerActor = $meta['actorKey'] ?? null;
    $ownerDevice = $meta['deviceId'] ?? null;

    if ($ownerActor && $actorKey && $ownerActor !== $actorKey && $ownerDevice && $deviceId && $ownerDevice !== $deviceId) {
      throw new DomainRuleException('No tienes permiso para modificar esta sesión de carga.', 403);
    }

    return $meta;
  }

  private function uploadDir(string $uploadKey): string
  {
    return self::BASE_DIR . '/' . $uploadKey;
  }

  private function chunksDir(string $uploadKey): string
  {
    return $this->uploadDir($uploadKey) . '/chunks';
  }

  private function metaPath(string $uploadKey): string
  {
    return $this->uploadDir($uploadKey) . '/meta.json';
  }

  private function chunkFilePath(string $uploadKey, int $index): string
  {
    return $this->chunksDir($uploadKey) . "/{$index}.part";
  }
}
