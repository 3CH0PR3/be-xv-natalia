<?php

namespace App\Actions\Memory;

use App\DTOs\Memory\CreateMemoryData;
use App\Http\Resources\Api\V1\MemoryResource;
use App\Jobs\ProcessMemoryJob;
use App\Models\Event;
use App\Models\EventTable;
use App\Models\Memory;
use App\Models\MemoryMediaItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateMemoryAction
{
    public function execute(Event $event, CreateMemoryData $data): Memory
    {
        $table = EventTable::forEvent($event->id)->where('code', $data->tableCode)->first();
        $nowMs = (int) round(microtime(true) * 1000);
        $externalId = $data->externalId ?: ('xv-nat-' . $nowMs);
        $disk = config('xv.media_disk', 'public');

        return DB::transaction(function () use ($event, $table, $data, $nowMs, $externalId, $disk): Memory {
            $firstMime = $data->files[0]->getMimeType() ?? 'image/jpeg';
            $primaryType = str_starts_with($firstMime, 'video/') ? 'video' : 'image';

            $memory = Memory::updateOrCreate(
                ['external_id' => $externalId],
                [
                    'event_id' => $event->id,
                    'event_table_id' => $table?->id,
                    'guest_name' => $data->guestName,
                    'table_code' => $data->tableCode,
                    'owner_device_id' => $data->ownerDeviceId,
                    'owner_actor_key' => $data->ownerActorKey,
                    'moment_tag' => $data->momentTag,
                    'caption' => $data->caption,
                    'media_type' => $primaryType,
                    'status' => Memory::STATUS_APPROVED,
                    'timestamp_ms' => $nowMs,
                ]
            );

            $mediaItemIds = [];
            foreach ($data->files as $idx => $uploadedFile) {
                $mime = $uploadedFile->getMimeType() ?? 'application/octet-stream';
                $isVideo = str_starts_with($mime, 'video/');
                $ext = $uploadedFile->extension() ?: ($isVideo ? 'mp4' : 'jpg');
                $blobKey = 'media-' . $nowMs . '-' . Str::lower(Str::random(6));
                $dir = "memories/{$event->code}/{$externalId}";
                $filePath = $uploadedFile->storeAs($dir, "{$blobKey}.{$ext}", $disk);

                $posterPath = null;
                if (isset($data->posters[$idx]) && $data->posters[$idx]) {
                    $posterPath = $data->posters[$idx]->storeAs($dir, "{$blobKey}_poster.jpg", $disk);
                }

                $mediaItem = MemoryMediaItem::create([
                    'memory_id' => $memory->id,
                    'external_id' => "{$externalId}-{$idx}",
                    'media_blob_key' => $blobKey,
                    'media_type' => $isVideo ? 'video' : 'image',
                    'disk' => $disk,
                    'file_path' => $filePath,
                    'thumb_path' => $posterPath ?: ($isVideo ? null : $filePath),
                    'poster_path' => $isVideo ? $posterPath : null,
                    'mime_type' => $mime,
                    'byte_size' => $uploadedFile->getSize() ?: 0,
                    'sort_order' => $idx,
                ]);

                $mediaItemIds[] = $mediaItem->id;
            }

            $freshMemory = $memory->fresh(['mediaItems', 'reactions', 'comments.commentLikes']);

            DB::afterCommit(static function () use ($freshMemory): void {
                // Dispatch async job for notifications, media optimization, caching & broadcasting
                ProcessMemoryJob::dispatch($freshMemory->id);
            });

            return $freshMemory;
        });
    }
}
