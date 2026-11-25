<?php

namespace App\Support\Storage;

use App\Exceptions\DomainRuleException;
use Illuminate\Support\Facades\Storage;

final class ChunkUploadStoragePolicy
{
  public const CHUNK_DISK = 'local';
  public const BASE_DIR = 'chunk_uploads';
  public const DEFAULT_CHUNK_SIZE = 5242880;

  public static function uploadDir(string $uploadKey): string
  {
    return self::BASE_DIR . '/' . $uploadKey;
  }

  public static function chunksDir(string $uploadKey): string
  {
    return self::uploadDir($uploadKey) . '/chunks';
  }

  public static function metaPath(string $uploadKey): string
  {
    return self::uploadDir($uploadKey) . '/meta.json';
  }

  public static function chunkFilePath(string $uploadKey, int $index): string
  {
    return self::chunksDir($uploadKey) . "/{$index}.part";
  }

  public static function readAndAuthorizeMeta(string $uploadKey, ?string $actorKey, ?string $deviceId): array
  {
    if (!preg_match('/^ul_[a-z0-9]{16,40}$/', $uploadKey)) {
      throw new DomainRuleException('Identificador de carga inválido.', 422);
    }

    $disk = Storage::disk(self::CHUNK_DISK);
    $metaPath = self::metaPath($uploadKey);
    if (!$disk->exists($metaPath)) {
      throw new DomainRuleException('La sesión de carga por fragmentos no existe o expiró.', 404);
    }

    $meta = json_decode((string) $disk->get($metaPath), true);
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
}
