<?php

namespace App\Services;

use App\Actions\Profile\GetProfileAction;
use App\Actions\Profile\GetProfileMediaAction;
use App\Actions\Profile\GetProfileReactionsAction;
use App\Actions\Profile\UpdateProfileAction;
use App\DTOs\Profile\UpdateProfileData;
use App\DTOs\Profile\ViewProfileData;
use App\Models\DeviceLock;
use App\Models\Event;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ProfileService
{
  public function __construct(
    private readonly GetProfileAction $getProfile,
    private readonly UpdateProfileAction $updateProfile,
    private readonly GetProfileMediaAction $getProfileMedia,
    private readonly GetProfileReactionsAction $getProfileReactions
  ) {}

  public function getAvatar(string $id): BinaryFileResponse
  {
    return $this->getProfileMedia->execute($id, 'avatar');
  }

  public function getCover(string $id): BinaryFileResponse
  {
    return $this->getProfileMedia->execute($id, 'cover');
  }

  public function getProfile(
    ViewProfileData|string|null $targetOrDto = null,
    ?string $viewerDeviceId = null,
    ?string $viewerActorKey = null,
    ?string $eventCode = null
  ): array {
    if ($targetOrDto instanceof ViewProfileData) {
      return $this->getProfile->execute(
        $targetOrDto->targetIdentifier,
        $targetOrDto->viewerDeviceId,
        $targetOrDto->viewerActorKey,
        $targetOrDto->eventCode
      );
    }

    return $this->getProfile->execute($targetOrDto, $viewerDeviceId, $viewerActorKey, $eventCode);
  }

  public function updateProfile(UpdateProfileData $data): array
  {
    return $this->updateProfile->execute($data);
  }

  public function getReactions(
    ViewProfileData|string|null $targetOrDto = null,
    ?string $viewerDeviceId = null,
    ?string $viewerActorKey = null,
    ?string $eventCode = null,
    int $page = 1,
    int $perPage = 7
  ): array {
    if ($targetOrDto instanceof ViewProfileData) {
      return $this->getProfileReactions->execute(
        $targetOrDto->targetIdentifier,
        $targetOrDto->viewerDeviceId,
        $targetOrDto->viewerActorKey,
        $targetOrDto->eventCode,
        $page,
        $perPage
      );
    }

    return $this->getProfileReactions->execute($targetOrDto, $viewerDeviceId, $viewerActorKey, $eventCode, $page, $perPage);
  }

  public function listProfiles(
    ?string $search = null,
    int $limit = 10,
    int $page = 1,
    ?string $eventCode = null,
    ?string $viewerDeviceId = null,
    ?string $viewerActorKey = null
  ): array
  {
    $eventCode = $eventCode ?: config('xv.default_event_code', 'XV-NATALIA');
    $event = Event::where('code', $eventCode)->first();
    if (!$event) {
      return [
        'data' => [],
        'pagination' => [
          'currentPage' => 1,
          'perPage' => $limit,
          'total' => 0,
          'hasMore' => false,
        ],
      ];
    }

    $query = DeviceLock::where('event_id', $event->id)->with('table');

    if ($search !== null && trim($search) !== '') {
      $term = trim($search);
      $cleanSlug = DeviceLock::normalizeSlug($term);
      $query->where(function (Builder $q) use ($term, $cleanSlug): void {
        $q->where('guest_name', 'like', "%{$term}%")
          ->orWhere('guest_name_slug', 'like', "%{$cleanSlug}%");
        if (is_numeric($term)) {
          $q->orWhere('id', (int) $term);
        }
        $q->orWhereHas('table', function (Builder $tq) use ($term): void {
          $tq->where('code', 'like', "%{$term}%")
            ->orWhere('number', 'like', "%{$term}%")
            ->orWhere('zone', 'like', "%{$term}%");
        });
      });
    }

    $isTableContext = $search !== null && (
      stripos($search, 'mesa') !== false ||
      stripos($search, 'table') !== false ||
      stripos($search, 'principal') !== false
    );

    if (($viewerActorKey !== null && $viewerActorKey !== '') || ($viewerDeviceId !== null && $viewerDeviceId !== '')) {
      $escapedActor = addslashes((string) $viewerActorKey);
      $escapedDev = addslashes((string) $viewerDeviceId);
      $query->orderByRaw("CASE WHEN (actor_key = '{$escapedActor}' OR device_id = '{$escapedDev}') THEN 0 ELSE 1 END");
    }

    if ($isTableContext) {
      $query->orderBy('locked_at', 'asc')->orderBy('id', 'asc');
    } else {
      $query->orderByRaw('CASE WHEN avatar_path IS NOT NULL AND avatar_path != \'\' THEN 0 ELSE 1 END')
        ->orderByDesc('locked_at')
        ->orderByDesc('id');
    }

    $paginator = $query->paginate($limit, ['*'], 'page', $page);

    $items = collect($paginator->items())->map(function (DeviceLock $lock): array {
      return [
        'id' => $lock->id,
        'guestName' => $lock->guest_name,
        'slug' => $lock->guest_name_slug ?: DeviceLock::normalizeSlug($lock->guest_name),
        'actorKey' => $lock->actor_key,
        'deviceId' => $lock->device_id,
        'avatarUrl' => $lock->avatar_url,
        'coverUrl' => $lock->cover_url,
        'bio' => $lock->bio,
        'location' => $lock->location,
        'tableCode' => $lock->table?->code,
        'tableNumber' => $lock->table?->number,
        'tableZone' => $lock->table?->zone,
        'joinedAt' => optional($lock->locked_at ?? $lock->created_at)->toIso8601String(),
      ];
    })->values()->all();

    return [
      'data' => $items,
      'pagination' => [
        'currentPage' => $paginator->currentPage(),
        'perPage' => $paginator->perPage(),
        'total' => $paginator->total(),
        'hasMore' => $paginator->hasMorePages(),
      ],
    ];
  }
}
