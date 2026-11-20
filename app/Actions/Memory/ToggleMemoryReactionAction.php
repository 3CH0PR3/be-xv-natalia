<?php

namespace App\Actions\Memory;

use App\DTOs\Memory\ToggleReactionData;
use App\Jobs\ProcessLikeJob;
use App\Models\Memory;
use App\Models\MemoryReaction;
use App\Services\MemoryReactionCounterService;
use Illuminate\Support\Facades\DB;

class ToggleMemoryReactionAction
{
  public function __construct(
    private readonly MemoryReactionCounterService $counterService,
  ) {}

  public function execute(ToggleReactionData $data): array
  {
    return DB::transaction(function () use ($data): array {
      $memory = Memory::with('event')
        ->where('external_id', $data->externalId)
        ->firstOrFail();

      $existing = MemoryReaction::forActor($memory->id, $data->actorKey, $data->deviceId)->first();
      $prevChoice = $existing?->choice ?? '';

      $isToggleOff = ($data->choice === '' || $data->choice === $prevChoice);

      MemoryReaction::forActor($memory->id, $data->actorKey, $data->deviceId)->delete();

      $effectiveChoice = $this->createReactionRecordIfActive($memory->id, $data, $isToggleOff);

      $fallbacks = [
        'likes' => (int) ($memory->likes_count ?? 0),
        'fires' => (int) ($memory->fires_count ?? 0),
        'cheers' => (int) ($memory->cheers_count ?? 0),
      ];

      $counts = $this->counterService->adjust(
        $memory->id,
        $prevChoice ?: null,
        $effectiveChoice ?: null,
        $fallbacks
      );

      $likes = $counts['likes'];
      $fires = $counts['fires'];
      $cheers = $counts['cheers'];

      DB::afterCommit(static function () use ($memory, $likes, $fires, $cheers, $data, $effectiveChoice): void {
        ProcessLikeJob::dispatch($memory->id, $data->actorKey, $effectiveChoice, $likes, $fires, $cheers, $data->deviceId);
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

  private function createReactionRecordIfActive(int $memoryId, ToggleReactionData $data, bool $isToggleOff): string
  {
    $isValidChoice = in_array($data->choice, MemoryReaction::CHOICES, true);
    if ($isToggleOff || !$isValidChoice) {
      return '';
    }

    MemoryReaction::create([
      'memory_id' => $memoryId,
      'actor_key' => $data->actorKey,
      'device_id' => $data->deviceId,
      'choice' => $data->choice,
    ]);

    return $data->choice;
  }
}
