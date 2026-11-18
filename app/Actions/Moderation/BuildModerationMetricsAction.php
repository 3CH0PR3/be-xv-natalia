<?php

namespace App\Actions\Moderation;

use App\Models\DeviceLock;
use App\Models\Event;
use App\Models\Memory;
use App\Models\MemoryComment;
use App\Models\MemoryMediaItem;

class BuildModerationMetricsAction
{
    public function execute(Event $event): array
    {
        $publishedQuery = Memory::forEvent($event->id)->approved();
        $publishedCount = (clone $publishedQuery)->count();
        $totalFiles = MemoryMediaItem::whereHas('memory', static function ($q) use ($event): void {
            $q->forEvent($event->id)->approved();
        })->count();
        $totalComments = MemoryComment::whereHas('memory', static function ($q) use ($event): void {
            $q->forEvent($event->id)->approved();
        })->count();

        return [
            'publishedCount' => $publishedCount,
            'totalFiles' => $totalFiles,
            'totalComments' => $totalComments,
            'totalMemories' => Memory::forEvent($event->id)->count(),
            'approvedMemories' => $publishedCount,
            'connectedGuests' => DeviceLock::forEvent($event->id)->count(),
            'partyCelebrations' => $event->party_celebrations_count,
        ];
    }
}
