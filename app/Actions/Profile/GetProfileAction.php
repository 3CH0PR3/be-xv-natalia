<?php

namespace App\Actions\Profile;

use App\DTOs\Profile\ViewProfileData;
use App\Http\Resources\Api\V1\MemoryResource;
use App\Models\DeviceLock;
use App\Models\Event;
use App\Models\Memory;
use App\Support\Cache\Keys\EventKeys;
use App\Traits\Cache\CacheableCache;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class GetProfileAction
{
  public function execute(
    ViewProfileData|string|null $targetIdentifier = null,
    ?string $viewerDeviceId = null,
    ?string $viewerActorKey = null,
    ?string $eventCode = null
  ): array {
    if ($targetIdentifier instanceof ViewProfileData) {
      $viewerDeviceId = $targetIdentifier->viewerDeviceId;
      $viewerActorKey = $targetIdentifier->viewerActorKey;
      $eventCode = $targetIdentifier->eventCode;
      $targetIdentifier = $targetIdentifier->targetIdentifier;
    }

    $eventCode = $eventCode ?: config('xv.default_event_code', 'XV-NATALIA');
    $event = Event::where('code', $eventCode)->firstOrFail();

    $query = DeviceLock::where('event_id', $event->id)->with('table');

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

    if (!$lock) {
      // Si se busca por nombre o identificador cuando no hay lock en device_locks pero sí memorias
      if ($targetIdentifier !== null && trim($targetIdentifier) !== '') {
        $trimmed = trim($targetIdentifier);
        $cleanSlug = DeviceLock::normalizeSlug($trimmed);

        $firstMem = Memory::where('event_id', $event->id)
          ->where(function (Builder $q) use ($trimmed, $cleanSlug): void {
            $q->where('guest_name', $trimmed)
              ->orWhere('owner_actor_key', $trimmed)
              ->orWhere('owner_device_id', $trimmed);
            if ($cleanSlug !== '') {
              $q->orWhereRaw("LOWER(REPLACE(guest_name, ' ', '-')) = ?", [$cleanSlug])
                ->orWhereRaw("LOWER(guest_name) = ?", [strtolower($trimmed)]);
            }
          })
          ->first();

        if ($firstMem) {
          return $this->buildFallbackProfileFromMemory($firstMem, $viewerDeviceId, $viewerActorKey);
        }

        throw new NotFoundHttpException('El perfil solicitado no fue encontrado.');
      }

      // Si se solicita el perfil propio (/perfil) sin tener sesión activa iniciada o no encontrada
      throw new NotFoundHttpException('El perfil solicitado no fue encontrado.');
    }

    $page = app()->bound('request') ? (int) app('request')->query('page', 1) : 1;
    $cacheKey = EventKeys::profileData($event->id, $lock->id, $page);

    if (app()->environment('testing')) {
      $formatted = $this->formatProfileResponse($lock, $viewerDeviceId, $viewerActorKey);
    } else {
      $formatted = \Illuminate\Support\Facades\Cache::store('redis')->remember(
        $cacheKey,
        120,
        fn () => $this->formatProfileResponse($lock, $viewerDeviceId, $viewerActorKey)
      );
    }

    // Dynamic resolution of isOwnProfile relative to current viewer
    $isOwn = false;
    if ($viewerActorKey && $lock->actor_key === $viewerActorKey) {
      $isOwn = true;
    } elseif ($viewerDeviceId && $lock->device_id === $viewerDeviceId) {
      $isOwn = true;
    }
    $formatted['isOwnProfile'] = $isOwn;
    if (!$isOwn && isset($formatted['stats']) && is_array($formatted['stats'])) {
      $formatted['stats']['totalReactions'] = 0;
    }

    return $formatted;
  }

  private function formatProfileResponse(DeviceLock $lock, ?string $viewerDeviceId, ?string $viewerActorKey): array
  {
    $isOwn = false;
    if ($viewerActorKey && $lock->actor_key === $viewerActorKey) {
      $isOwn = true;
    } elseif ($viewerDeviceId && $lock->device_id === $viewerDeviceId) {
      $isOwn = true;
    }

    $memories = Memory::forEvent($lock->event_id)
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
      ->where('status', Memory::STATUS_APPROVED)
      ->with(['mediaItems', 'reactions'])
      ->withCount(['comments', 'shares'])
      ->orderByDesc('timestamp_ms')
      ->get();

    $totalPhotos = 0;
    $totalVideos = 0;

    foreach ($memories as $mem) {
      if ($mem->media_type === 'video') {
        $totalVideos++;
      } else {
        $totalPhotos++;
      }
    }

    $totalReactions = GetProfileReactionsAction::countActivityForLock($lock->event_id, $lock);

    $request = app()->bound('request') ? app('request') : new Request();
    $page = max(1, (int) $request->query('page', 1));
    $perPage = max(1, min(50, (int) $request->query('perPage', 10)));
    $offset = ($page - 1) * $perPage;
    $pagedMemories = $memories->slice($offset, $perPage)->values();

    return [
      'id' => (string) ($lock->actor_key ?: $lock->id),
      'numericId' => $lock->id,
      'guestName' => $lock->guest_name,
      'tableCode' => $lock->table?->code ?? 'Mesa del Evento',
      'tableName' => $lock->table?->name ?? 'Invitado',
      'avatarUrl' => $lock->avatar_url,
      'coverUrl' => $lock->cover_url,
      'bio' => $lock->bio ?? '',
      'location' => $lock->location ?? '',
      'joinedAt' => optional($lock->locked_at ?: $lock->created_at)->toIso8601String(),
      'isOwnProfile' => $isOwn,
      'stats' => [
        'totalMemories' => $memories->count(),
        'totalPhotos' => $totalPhotos,
        'totalVideos' => $totalVideos,
        'totalReactions' => $totalReactions,
      ],
      'pagination' => [
        'page' => $page,
        'perPage' => $perPage,
        'total' => $memories->count(),
        'hasMore' => ($offset + $perPage) < $memories->count(),
      ],
      'memories' => MemoryResource::collection($pagedMemories)->resolve($request),
    ];
  }

  private function buildFallbackProfileFromMemory(Memory $memory, ?string $viewerDeviceId, ?string $viewerActorKey): array
  {
    $isOwn = ($viewerDeviceId && $memory->owner_device_id === $viewerDeviceId) ||
             ($viewerActorKey && $memory->owner_actor_key === $viewerActorKey);

    $memories = Memory::forEvent($memory->event_id)
      ->where(function (Builder $q) use ($memory): void {
        if ($memory->guest_name) {
          $q->orWhere('guest_name', $memory->guest_name);
        }
        if ($memory->owner_actor_key) {
          $q->orWhere('owner_actor_key', $memory->owner_actor_key);
        }
        if ($memory->owner_device_id) {
          $q->orWhere('owner_device_id', $memory->owner_device_id);
        }
      })
      ->where('status', Memory::STATUS_APPROVED)
      ->with(['mediaItems', 'reactions'])
      ->withCount(['comments', 'shares'])
      ->orderByDesc('timestamp_ms')
      ->get();

    $request = app()->bound('request') ? app('request') : new Request();
    $page = max(1, (int) $request->query('page', 1));
    $perPage = max(1, min(50, (int) $request->query('perPage', 10)));
    $offset = ($page - 1) * $perPage;
    $pagedMemories = $memories->slice($offset, $perPage)->values();

    return [
      'id' => (string) ($memory->owner_actor_key ?: $memory->guest_name),
      'numericId' => 0,
      'guestName' => $memory->guest_name,
      'tableCode' => $memory->table_code,
      'tableName' => 'Invitado',
      'avatarUrl' => null,
      'coverUrl' => null,
      'bio' => '',
      'location' => '',
      'joinedAt' => optional($memory->created_at)->toIso8601String(),
      'isOwnProfile' => $isOwn,
      'stats' => [
        'totalMemories' => $memories->count(),
        'totalPhotos' => $memories->where('media_type', '!=', 'video')->count(),
        'totalVideos' => $memories->where('media_type', 'video')->count(),
        'totalReactions' => 0,
      ],
      'pagination' => [
        'page' => $page,
        'perPage' => $perPage,
        'total' => $memories->count(),
        'hasMore' => ($offset + $perPage) < $memories->count(),
      ],
      'memories' => MemoryResource::collection($pagedMemories)->resolve($request),
    ];
  }
}
