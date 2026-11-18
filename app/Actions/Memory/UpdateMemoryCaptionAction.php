<?php

namespace App\Actions\Memory;

use App\DTOs\Memory\UpdateCaptionData;
use App\DTOs\Shared\ViewerContextData;
use App\Events\MemoryUpdated;
use App\Exceptions\DomainRuleException;
use App\Http\Resources\Api\V1\MemoryResource;
use App\Models\Memory;
use Illuminate\Support\Facades\DB;

class UpdateMemoryCaptionAction
{
    public function execute(UpdateCaptionData $data, ViewerContextData $viewer): Memory
    {
        return DB::transaction(function () use ($data, $viewer): Memory {
            $memory = Memory::with('event')
                ->where('external_id', $data->externalId)
                ->lockForUpdate()
                ->firstOrFail();

            if (!$viewer->isHost() && !$memory->isOwnedBy($viewer->deviceId, $viewer->actorKey)) {
                throw new DomainRuleException(
                    'FORBIDDEN_CAPTION_EDIT',
                    'Solo el autor original puede editar la dedicatoria de este recuerdo.',
                    403
                );
            }

            $memory->update(['caption' => $data->caption]);
            $fresh = $memory->fresh(['mediaItems', 'reactions', 'comments.commentLikes']);

            DB::afterCommit(static function () use ($memory, $fresh): void {
                $payload = (new MemoryResource($fresh))->resolve();
                broadcast(new MemoryUpdated($memory->event->code, $payload))->toOthers();
            });

            return $fresh;
        });
    }
}
