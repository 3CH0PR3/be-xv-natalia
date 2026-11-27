<?php

namespace App\Actions\Moderation;

use App\Exceptions\DomainRuleException;
use App\Models\Event;
use App\Models\Memory;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use ZipArchive;

class ExportApprovedAlbumZipAction
{
  public function execute(Event $event): string
  {
    $memories = Memory::with('mediaItems')->forEvent($event->id)->approved()->get();
    $tmpZip = tempnam(sys_get_temp_dir(), 'xv_album_') . '.zip';
    $zip = new ZipArchive();

    if ($zip->open($tmpZip, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
      throw new DomainRuleException('ZIP_CREATE_FAILED', 'No se pudo generar el archivo ZIP.', 500);
    }

    $tempFiles = [];
    try {
      foreach ($memories as $memory) {
        $folder = Str::slug("{$memory->table_code}-{$memory->guest_name}-{$memory->external_id}");
        foreach ($memory->mediaItems as $idx => $item) {
          $diskName = $item->disk ?: (string) config('xv.media_disk', 'public');
          $driver = (string) (config("filesystems.disks.{$diskName}.driver") ?? 'local');
          $disk = Storage::disk($diskName);
          $ext = pathinfo((string) $item->file_path, PATHINFO_EXTENSION) ?: 'jpg';
          $entryName = "{$folder}/archivo-" . ($idx + 1) . ".{$ext}";

          if ($driver === 'local') {
            $fullPath = $disk->path($item->file_path);
            if (is_file($fullPath)) {
              $zip->addFile($fullPath, $entryName);
            }
            continue;
          }

          if (!$disk->exists($item->file_path)) {
            continue;
          }
          $stream = $disk->readStream($item->file_path);
          if ($stream === false) {
            continue;
          }
          $tmpItem = tempnam(sys_get_temp_dir(), 'xv_zip_item_');
          if ($tmpItem === false) {
            if (is_resource($stream)) {
              fclose($stream);
            }
            continue;
          }
          $out = fopen($tmpItem, 'wb');
          try {
            if ($out !== false) {
              stream_copy_to_stream($stream, $out);
            }
          } finally {
            if (is_resource($out)) {
              fclose($out);
            }
            if (is_resource($stream)) {
              fclose($stream);
            }
          }
          $tempFiles[] = $tmpItem;
          $zip->addFile($tmpItem, $entryName);
        }
      }

      $zip->close();
    } finally {
      foreach ($tempFiles as $tmpFile) {
        if (is_file($tmpFile)) {
          @unlink($tmpFile);
        }
      }
    }

    return $tmpZip;
  }
}
