<?php

namespace App\Actions\Comment;

use App\DTOs\Comment\ToggleCommentLikeData;
use App\Jobs\ProcessCommentLikeJob;
use App\Models\Memory;
use App\Models\MemoryComment;
use App\Models\MemoryCommentLike;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ToggleCommentLikeAction
{
    public function execute(ToggleCommentLikeData $data): array
    {
        return DB::transaction(function () use ($data): array {
            $memory = Memory::with('event')->where('external_id', $data->memoryExternalId)->firstOrFail();
            $comment = MemoryComment::where('memory_id', $memory->id)
                ->where('external_id', $data->commentExternalId)
                ->lockForUpdate()
                ->firstOrFail();

            $existingQuery = MemoryCommentLike::where('comment_id', $comment->id);
            if ($data->deviceId) {
                $existingQuery->where(function ($query) use ($data): void {
                    $query->where('actor_key', $data->actorKey)
                        ->orWhere('device_id', $data->deviceId);
                });
            }
            if (!$data->deviceId) {
                $existingQuery->where('actor_key', $data->actorKey);
            }
            $existing = $existingQuery->first();

            $likesCountBefore = $comment->commentLikes()->count();

            Log::info("[ToggleCommentLike] PRE-TOGGLE: Comment #{$comment->id} (Ext: {$comment->external_id}, Memory: {$memory->external_id}), Actor: '{$data->actorKey}', Device: '{$data->deviceId}', RequestedLiked: " . json_encode($data->liked) . ", ExistingLike: " . ($existing ? 'YES (ID: ' . $existing->id . ')' : 'NO') . ", DB likes_count: {$comment->likes_count}, DB relation count: {$likesCountBefore}");

            $targetLiked = $data->liked !== null ? $data->liked : !$existing;

            if ($targetLiked && !$existing) {
                MemoryCommentLike::create([
                    'comment_id' => $comment->id,
                    'actor_key' => $data->actorKey,
                    'device_id' => $data->deviceId,
                ]);
            }

            if (!$targetLiked && $existing) {
                $existing->delete();
            }

            $likesCount = $comment->commentLikes()->count();
            $comment->update(['likes_count' => $likesCount]);
            Cache::store('redis')->forget("memory:comments:{$memory->id}");

            Log::info("[ToggleCommentLike] POST-TOGGLE: Comment #{$comment->id} (Ext: {$comment->external_id}), Action: " . ($targetLiked ? 'LIKED' : 'UNLIKED') . ", New authoritative likes_count: {$likesCount}");

            DB::afterCommit(static function () use ($comment, $data, $likesCount, $targetLiked): void {
                ProcessCommentLikeJob::dispatch($comment->id, $data->actorKey, $likesCount, $targetLiked);
            });

            return [
                'likes' => $likesCount,
                'liked' => $targetLiked,
            ];
        });
    }
}
