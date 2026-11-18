<?php

namespace App\Actions\Memory;

use App\DTOs\Memory\ToggleReactionData;
use App\Events\MemoryReactionUpdated;
use App\Jobs\ProcessLikeJob;
use App\Models\Memory;
use App\Models\MemoryReaction;
use Illuminate\Support\Facades\DB;

class ToggleMemoryReactionAction
{
  public function execute(ToggleReactionData $data): array
  {
    return DB::transaction(function () use ($data): array {
      $memory = Memory::with('event')
        ->where('external_id', $data->externalId)
        ->lockForUpdate()
        ->firstOrFail();

      $existing = MemoryReaction::forActor($memory->id, $data->actorKey, $data->deviceId)->first();
      $prevChoice = $existing?->choice ?? '';

      $isToggleOff = ($data->choice === '' || $data->choice === $prevChoice);

      // Eliminar cualquier reacción previa de este actor y dispositivo para garantizar exclusión mutua estricta
      MemoryReaction::forActor($memory->id, $data->actorKey, $data->deviceId)->delete();

      $effectiveChoice = '';
      if (!$isToggleOff && in_array($data->choice, MemoryReaction::CHOICES, true)) {
        MemoryReaction::create([
          'memory_id' => $memory->id,
          'actor_key' => $data->actorKey,
          'device_id' => $data->deviceId,
          'choice' => $data->choice,
        ]);
        $effectiveChoice = $data->choice;
      }

      // Recontar directamente desde la base de datos para garantizar conteo 100% exacto y auto-sanación
      $likes = MemoryReaction::where('memory_id', $memory->id)->where('choice', 'likes')->count();
      $fires = MemoryReaction::where('memory_id', $memory->id)->where('choice', 'fires')->count();
      $cheers = MemoryReaction::where('memory_id', $memory->id)->where('choice', 'cheers')->count();

      $memory->update([
        'likes_count' => $likes,
        'fires_count' => $fires,
        'cheers_count' => $cheers,
      ]);

      DB::afterCommit(static function () use ($memory, $likes, $fires, $cheers, $data, $effectiveChoice): void {
        ProcessLikeJob::dispatch($memory->id, $data->actorKey, $effectiveChoice, $likes, $fires, $cheers);
      });

      return [
        'memoryId' => $memory->external_id,
        'likes' => $likes,
        'fires' => $fires,
        'cheers' => $cheers,
        'choice' => $effectiveChoice,
      ];
    });
  }
}
