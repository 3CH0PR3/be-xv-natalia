<?php

namespace App\Actions\Profile;

use App\Http\Resources\Api\V1\MemoryResource;
use App\Models\DeviceLock;
use App\Models\Event;
use App\Models\Memory;
use App\Models\MemoryComment;
use App\Models\MemoryCommentLike;
use App\Models\MemoryReaction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
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

    $lock = $this->resolveTargetLock($event, $targetIdentifier, $viewerDeviceId, $viewerActorKey);

    if ($targetIdentifier !== null && trim($targetIdentifier) !== '') {
      if (!$this->isViewerOwner($lock, $viewerDeviceId, $viewerActorKey)) {
        throw new AccessDeniedHttpException('La actividad de reacciones es privada y solo puede verla el dueño del perfil.');
      }
    }

    $actorKey = $lock?->actor_key ?: ($targetIdentifier !== null ? trim($targetIdentifier) : null);
    $deviceId = $lock?->device_id ?: ($targetIdentifier !== null ? trim($targetIdentifier) : null);
    $lockId = $lock?->id;
    $actorDisplayName = $lock?->guest_name ?: ($targetIdentifier ?: 'Invitado');

    if (!$lockId && !$actorKey && !$deviceId) {
      return $this->emptyPaginatedResult($page, $perPage);
    }

    $memoryRelations = [
      'deviceLock:id,guest_name,avatar_path,cover_path,actor_key,device_id,updated_at',
      'mediaItems',
      'reactions',
      'comments.deviceLock:id,guest_name,avatar_path,cover_path,actor_key,device_id,updated_at',
      'comments.commentLikes',
    ];

    $postReactions = MemoryReaction::query()
      ->whereHas('memory', fn (Builder $q) => $q->forEvent($event->id)->where('status', Memory::STATUS_APPROVED))
      ->where(function (Builder $q) use ($actorKey, $deviceId): void {
        if ($actorKey) {
          $q->orWhere('actor_key', $actorKey);
        }
        if ($deviceId) {
          $q->orWhere('device_id', $deviceId);
        }
      })
      ->with(array_map(fn (string $rel): string => "memory.{$rel}", $memoryRelations))
      ->get();

    $commentLikes = MemoryCommentLike::query()
      ->whereHas('comment.memory', fn (Builder $q) => $q->forEvent($event->id)->where('status', Memory::STATUS_APPROVED))
      ->where(function (Builder $q) use ($actorKey, $deviceId): void {
        if ($actorKey) {
          $q->orWhere('actor_key', $actorKey);
        }
        if ($deviceId) {
          $q->orWhere('device_id', $deviceId);
        }
      })
      ->with(array_merge(
        ['comment.deviceLock:id,guest_name,avatar_path,cover_path,actor_key,device_id,updated_at'],
        array_map(fn (string $rel): string => "comment.memory.{$rel}", $memoryRelations)
      ))
      ->get();

    $comments = MemoryComment::query()
      ->whereHas('memory', fn (Builder $q) => $q->forEvent($event->id)->where('status', Memory::STATUS_APPROVED))
      ->where(function (Builder $q) use ($lockId, $actorKey, $deviceId): void {
        if ($lockId) {
          $q->orWhere('device_lock_id', $lockId);
        }
        if ($actorKey) {
          $q->orWhere('owner_actor_key', $actorKey);
        }
        if ($deviceId) {
          $q->orWhere('owner_device_id', $deviceId);
        }
      })
      ->with(array_map(fn (string $rel): string => "memory.{$rel}", $memoryRelations))
      ->get();

    $request = app()->bound('request') ? app('request') : new Request();
    $resolvedMemoryCache = [];

    $resolveMemory = static function (?Memory $mem) use ($request, &$resolvedMemoryCache): ?array {
      if (!$mem) {
        return null;
      }
      if (!isset($resolvedMemoryCache[$mem->id])) {
        $resolvedMemoryCache[$mem->id] = (new MemoryResource($mem))->resolve($request);
      }
      return $resolvedMemoryCache[$mem->id];
    };

    $resolvePreviewMedia = static function (?array $resolvedMemory, ?Memory $mem): array {
      if (!$mem || !$resolvedMemory) {
        return ['mediaUrl' => '', 'mediaType' => 'image', 'isAlbum' => false];
      }
      $items = $resolvedMemory['mediaItems'] ?? [];
      $first = $items[0] ?? null;
      $mediaType = $first['mediaType'] ?? $resolvedMemory['mediaType'] ?? 'image';
      $posterOrThumb = $first['thumbUrl'] ?? $first['posterUrl'] ?? $resolvedMemory['thumbUrl'] ?? $resolvedMemory['posterUrl'] ?? '';
      $fallbackImg = $mediaType === 'video' ? '' : ($first['imageUrl'] ?? $resolvedMemory['imageUrl'] ?? '');
      $mediaUrl = $posterOrThumb ?: $fallbackImg;
      $isAlbum = count($items) > 1;

      return [
        'mediaUrl' => (string) $mediaUrl,
        'mediaType' => (string) $mediaType,
        'isAlbum' => $isAlbum,
      ];
    };

    $reactionMeta = [
      'likes' => ['emoji' => "\u{2764}\u{FE0F}", 'label' => 'Me encanta'],
      'fires' => ['emoji' => "\u{1F525}", 'label' => 'Fuego'],
      'cheers' => ['emoji' => "\u{2728}", 'label' => 'Destello'],
    ];

    $entries = [];

    foreach ($postReactions as $reaction) {
      $mem = $reaction->memory;
      $resolvedMem = $resolveMemory($mem);
      if (!$mem || !$resolvedMem) {
        continue;
      }
      $preview = $resolvePreviewMedia($resolvedMem, $mem);
      $meta = $reactionMeta[$reaction->choice] ?? $reactionMeta['likes'];
      $ts = $reaction->created_at?->getTimestamp() ?? 0;

      $entries[] = [
        'sortTimestamp' => $ts,
        'sortId' => $reaction->id,
        'item' => [
          'id' => 'react-' . $reaction->id,
          'choice' => $reaction->choice,
          'reactionLabel' => $meta['label'],
          'emoji' => $meta['emoji'],
          'targetType' => 'post',
          'isAlbum' => $preview['isAlbum'],
          'author' => $actorDisplayName,
          'targetAuthor' => $resolvedMem['guestName'] ?: 'Invitado',
          'commentId' => null,
          'excerpt' => $resolvedMem['caption'] ?: ($preview['isAlbum'] ? 'Álbum compartido' : 'Recuerdo compartido'),
          'mediaUrl' => $preview['mediaUrl'],
          'mediaType' => $preview['mediaType'],
          'createdAt' => optional($reaction->created_at)->toIso8601String(),
          'memoryId' => $mem->external_id,
          'memory' => $resolvedMem,
        ],
      ];
    }

    foreach ($commentLikes as $like) {
      $comment = $like->comment;
      $mem = $comment?->memory;
      $resolvedMem = $resolveMemory($mem);
      if (!$comment || !$mem || !$resolvedMem) {
        continue;
      }
      $preview = $resolvePreviewMedia($resolvedMem, $mem);
      $ts = $like->created_at?->getTimestamp() ?? 0;

      $entries[] = [
        'sortTimestamp' => $ts,
        'sortId' => $like->id,
        'item' => [
          'id' => 'comment-like-' . $like->id,
          'choice' => 'comment_like',
          'reactionLabel' => 'Me gusta',
          'emoji' => "\u{2764}\u{FE0F}",
          'targetType' => 'comment_like',
          'isAlbum' => $preview['isAlbum'],
          'author' => $actorDisplayName,
          'targetAuthor' => $comment->resolved_author ?: 'Invitado',
          'commentId' => $comment->external_id,
          'excerpt' => $comment->text ?: 'Comentario',
          'mediaUrl' => $preview['mediaUrl'],
          'mediaType' => $preview['mediaType'],
          'createdAt' => optional($like->created_at)->toIso8601String(),
          'memoryId' => $mem->external_id,
          'memory' => $resolvedMem,
        ],
      ];
    }

    foreach ($comments as $comment) {
      $mem = $comment->memory;
      $resolvedMem = $resolveMemory($mem);
      if (!$mem || !$resolvedMem) {
        continue;
      }
      $preview = $resolvePreviewMedia($resolvedMem, $mem);
      $ts = $comment->created_at?->getTimestamp()
        ?? ($comment->created_at_ms ? (int) floor($comment->created_at_ms / 1000) : 0);

      $entries[] = [
        'sortTimestamp' => $ts,
        'sortId' => $comment->id,
        'item' => [
          'id' => 'comment-' . $comment->id,
          'choice' => 'comment',
          'reactionLabel' => 'Comentario',
          'emoji' => "\u{1F4AC}",
          'targetType' => 'comment',
          'isAlbum' => $preview['isAlbum'],
          'author' => $actorDisplayName,
          'targetAuthor' => $resolvedMem['guestName'] ?: 'Invitado',
          'commentId' => $comment->external_id,
          'excerpt' => $comment->text ?: '',
          'mediaUrl' => $preview['mediaUrl'],
          'mediaType' => $preview['mediaType'],
          'createdAt' => optional($comment->created_at)->toIso8601String(),
          'memoryId' => $mem->external_id,
          'memory' => $resolvedMem,
        ],
      ];
    }

    usort($entries, static function (array $a, array $b): int {
      if ($a['sortTimestamp'] !== $b['sortTimestamp']) {
        return $b['sortTimestamp'] <=> $a['sortTimestamp'];
      }
      return $b['sortId'] <=> $a['sortId'];
    });

    $total = count($entries);
    $offset = max(0, ($page - 1) * $perPage);
    $pagedItems = array_map(
      static fn (array $entry): array => $entry['item'],
      array_slice($entries, $offset, $perPage)
    );

    return [
      'data' => array_values($pagedItems),
      'pagination' => [
        'currentPage' => $page,
        'perPage' => $perPage,
        'total' => $total,
        'hasMore' => ($offset + $perPage) < $total,
      ],
    ];
  }

  public static function countActivityForLock(int|string $eventId, DeviceLock $lock): int
  {
    $actorKey = $lock->actor_key;
    $deviceId = $lock->device_id;
    $lockId = $lock->id;

    $postCount = MemoryReaction::query()
      ->whereHas('memory', fn (Builder $q) => $q->forEvent($eventId)->where('status', Memory::STATUS_APPROVED))
      ->where(function (Builder $q) use ($actorKey, $deviceId): void {
        if ($actorKey) {
          $q->orWhere('actor_key', $actorKey);
        }
        if ($deviceId) {
          $q->orWhere('device_id', $deviceId);
        }
      })
      ->count();

    $commentLikeCount = MemoryCommentLike::query()
      ->whereHas('comment.memory', fn (Builder $q) => $q->forEvent($eventId)->where('status', Memory::STATUS_APPROVED))
      ->where(function (Builder $q) use ($actorKey, $deviceId): void {
        if ($actorKey) {
          $q->orWhere('actor_key', $actorKey);
        }
        if ($deviceId) {
          $q->orWhere('device_id', $deviceId);
        }
      })
      ->count();

    $commentCount = MemoryComment::query()
      ->whereHas('memory', fn (Builder $q) => $q->forEvent($eventId)->where('status', Memory::STATUS_APPROVED))
      ->where(function (Builder $q) use ($lockId, $actorKey, $deviceId): void {
        if ($lockId) {
          $q->orWhere('device_lock_id', $lockId);
        }
        if ($actorKey) {
          $q->orWhere('owner_actor_key', $actorKey);
        }
        if ($deviceId) {
          $q->orWhere('owner_device_id', $deviceId);
        }
      })
      ->count();

    return $postCount + $commentLikeCount + $commentCount;
  }

  private function resolveTargetLock(
    Event $event,
    ?string $targetIdentifier,
    ?string $viewerDeviceId,
    ?string $viewerActorKey
  ): ?DeviceLock {
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

    return $query->first();
  }

  private function isViewerOwner(?DeviceLock $lock, ?string $viewerDeviceId, ?string $viewerActorKey): bool
  {
    if (!$lock) {
      return false;
    }
    if ($viewerActorKey && $lock->actor_key && $lock->actor_key === $viewerActorKey) {
      return true;
    }
    if ($viewerDeviceId && $lock->device_id && $lock->device_id === $viewerDeviceId) {
      return true;
    }
    return false;
  }

  private function emptyPaginatedResult(int $page, int $perPage): array
  {
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
