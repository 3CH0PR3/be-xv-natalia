<?php

namespace App\Actions\Profile;

use App\Models\DeviceLock;
use App\Models\Event;
use App\Models\Memory;
use App\Models\MemoryReaction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class GetProfileReactionsAction
{
  public function execute(
    ?string $targetIdentifier = null,
    ?string $viewerDeviceId = null,
    ?string $viewerActorKey = null,
    ?string $eventCode = null,
    int $page = 1,
    int $perPage = 7
  ): array {
    $eventCode = $eventCode ?: config('xv.default_event_code', 'XV-NATALIA');
    $event = Event::where('code', $eventCode)->firstOrFail();

    $query = DeviceLock::where('event_id', $event->id);

    if ($targetIdentifier !== null && trim($targetIdentifier) !== '') {
      $id = trim($targetIdentifier);
      $query->where(function (Builder $q) use ($id): void {
        if (is_numeric($id)) {
          $q->orWhere('id', (int) $id);
        }
        $q->orWhere('actor_key', $id)
          ->orWhere('device_id', $id)
          ->orWhere('guest_name_slug', DeviceLock::normalizeSlug($id))
          ->orWhere('guest_name', $id);
      });
    } else {
      if (!$viewerDeviceId && !$viewerActorKey) {
        throw new NotFoundHttpException('No hay sesión de invitado activa para consultar el perfil.');
      }
      $query->where(function (Builder $q) use ($viewerDeviceId, $viewerActorKey): void {
        if ($viewerActorKey) {
          $q->orWhere('actor_key', $viewerActorKey);
        }
        if ($viewerDeviceId) {
          $q->orWhere('device_id', $viewerDeviceId);
        }
      });
    }

    $lock = $query->first();

    if ($lock) {
      $memoryQuery = Memory::forEvent($event->id)
        ->where(function (Builder $q) use ($lock): void {
          $q->where('device_lock_id', $lock->id)
            ->orWhere(function (Builder $sub) use ($lock): void {
              if ($lock->actor_key) {
                $sub->orWhere('owner_actor_key', $lock->actor_key);
              }
              if ($lock->device_id) {
                $sub->orWhere('owner_device_id', $lock->device_id);
              }
              if ($lock->guest_name) {
                $sub->orWhere('guest_name', $lock->guest_name);
              }
            });
        })
        ->where('status', Memory::STATUS_APPROVED);
    } else {
      if ($targetIdentifier !== null && trim($targetIdentifier) !== '') {
        $trimmed = trim($targetIdentifier);
        $cleanSlug = DeviceLock::normalizeSlug($trimmed);
        $memoryQuery = Memory::forEvent($event->id)
          ->where(function (Builder $q) use ($trimmed, $cleanSlug): void {
            $q->where('guest_name', $trimmed)
              ->orWhere('owner_actor_key', $trimmed)
              ->orWhere('owner_device_id', $trimmed);
            if ($cleanSlug !== '') {
              $q->orWhereRaw("LOWER(REPLACE(guest_name, ' ', '-')) = ?", [$cleanSlug])
                ->orWhereRaw("LOWER(guest_name) = ?", [strtolower($trimmed)]);
            }
          })
          ->where('status', Memory::STATUS_APPROVED);
      } else {
        return [
          'data' => [],
          'pagination' => [
            'currentPage' => $page,
            'perPage' => $perPage,
            'total' => 0,
            'hasMore' => false,
          ],
        ];
      }
    }

    $memoryIds = $memoryQuery->pluck('id');

    if ($memoryIds->isEmpty()) {
      return [
        'data' => [],
        'pagination' => [
          'currentPage' => $page,
          'perPage' => $perPage,
          'total' => 0,
          'hasMore' => false,
        ],
      ];
    }

    $reactionsQuery = MemoryReaction::whereIn('memory_id', $memoryIds)
      ->with(['memory.mediaItems'])
      ->orderByDesc('created_at')
      ->orderByDesc('id');

    $paginator = $reactionsQuery->paginate($perPage, ['*'], 'page', $page);

    $actorKeys = collect($paginator->items())->pluck('actor_key')->filter()->unique();
    $deviceIds = collect($paginator->items())->pluck('device_id')->filter()->unique();

    $reactorLocks = DeviceLock::where(function (Builder $q) use ($actorKeys, $deviceIds): void {
      if ($actorKeys->isNotEmpty()) {
        $q->orWhereIn('actor_key', $actorKeys);
      }
      if ($deviceIds->isNotEmpty()) {
        $q->orWhereIn('device_id', $deviceIds);
      }
    })->get();

    $reactorNamesByActor = $reactorLocks->pluck('guest_name', 'actor_key')->all();
    $reactorNamesByDevice = $reactorLocks->pluck('guest_name', 'device_id')->all();

    $request = app()->bound('request') ? app('request') : new Request();

    $items = collect($paginator->items())->map(function (MemoryReaction $reaction) use ($reactorNamesByActor, $reactorNamesByDevice, $request): array {
      $mem = $reaction->memory;
      $emojiMap = [
        'likes' => "\u{2764}\u{FE0F}",
        'fires' => "\u{1F525}",
        'cheers' => "\u{2728}",
      ];

      $reactorName = ($reaction->actor_key && isset($reactorNamesByActor[$reaction->actor_key]))
        ? $reactorNamesByActor[$reaction->actor_key]
        : (($reaction->device_id && isset($reactorNamesByDevice[$reaction->device_id]))
          ? $reactorNamesByDevice[$reaction->device_id]
          : 'Invitado');

      $mediaUrl = '';
      if ($mem) {
        $firstItem = $mem->mediaItems->first();
        $rawUrl = $firstItem?->media_url ?? $mem->media_url ?? '';
        if ($rawUrl) {
          if (str_starts_with($rawUrl, 'http://') || str_starts_with($rawUrl, 'https://')) {
            $mediaUrl = $rawUrl;
          } else {
            $base = $request->getSchemeAndHttpHost();
            $mediaUrl = rtrim($base, '/') . '/' . ltrim($rawUrl, '/');
          }
        }
      }

      return [
        'id' => 'react-' . $reaction->id,
        'choice' => $reaction->choice,
        'emoji' => $emojiMap[$reaction->choice] ?? "\u{2764}\u{FE0F}",
        'targetType' => 'post',
        'author' => $reactorName,
        'targetAuthor' => $mem?->guest_name ?? 'Invitado',
        'excerpt' => $mem?->caption ?: 'Publicación compartida',
        'mediaUrl' => $mediaUrl,
        'createdAt' => optional($reaction->created_at)->toIso8601String(),
        'memoryId' => $reaction->memory_id,
        'memory' => $mem ? [
          'id' => (string) $mem->id,
          'guestName' => $mem->guest_name,
          'caption' => $mem->caption,
          'mediaType' => $mem->media_type,
          'mediaUrl' => $mediaUrl,
        ] : null,
      ];
    });

    return [
      'data' => $items->values()->all(),
      'pagination' => [
        'currentPage' => $paginator->currentPage(),
        'perPage' => $paginator->perPage(),
        'total' => $paginator->total(),
        'hasMore' => $paginator->hasMorePages(),
      ],
    ];
  }
}
