<?php

namespace App\Jobs;

use App\Events\CommentLikeUpdated;
use App\Models\Memory;
use App\Models\MemoryComment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ProcessCommentLikeJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 30;

    public function __construct(
        public int $commentId,
        public string $actorKey,
        public ?int $likesCount = null,
        public ?bool $liked = null
    ) {
        $this->queue = 'default';
    }

    public function handle(): void
    {
        $comment = MemoryComment::with(['memory.event', 'commentLikes'])->find($this->commentId);
        if (!$comment) {
            return;
        }

        $memory = $comment->memory;
        if (!$memory) {
            return;
        }

        $likesCount = $this->likesCount;
        if ($likesCount === null) {
            $likesCount = $comment->commentLikes()->count();
            $comment->update(['likes_count' => $likesCount]);
        }

        $liked = $this->liked ?? $comment->commentLikes()->where('actor_key', $this->actorKey)->exists();

        Cache::store('redis')->forget("memory:comments:{$memory->id}");

        $eventCode = $memory->event?->code ?? config('xv.default_event_code', 'XV-NATALIA');

        broadcast(new CommentLikeUpdated(
            $eventCode,
            $memory->external_id,
            $comment->external_id,
            $likesCount,
            $this->actorKey,
            $liked
        ));

        Log::info("[ProcessCommentLikeJob] Broadcasted 'comment.liked' for Comment #{$comment->id} (Ext: {$comment->external_id}, Memory: {$memory->external_id}, Event: {$eventCode}, Likes: {$likesCount}, Actor: {$this->actorKey}, Liked: " . ($liked ? 'true' : 'false') . ")");
    }
}
