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

        foreach ($memories as $memory) {
            $folder = Str::slug("{$memory->table_code}-{$memory->guest_name}-{$memory->external_id}");
            foreach ($memory->mediaItems as $idx => $item) {
                $fullPath = Storage::disk($item->disk ?: config('xv.media_disk', 'public'))->path($item->file_path);
                if (is_file($fullPath)) {
                    $ext = pathinfo($fullPath, PATHINFO_EXTENSION) ?: 'jpg';
                    $zip->addFile($fullPath, "{$folder}/archivo-" . ($idx + 1) . ".{$ext}");
                }
            }
        }

        $zip->close();
        return $tmpZip;
    }
}
