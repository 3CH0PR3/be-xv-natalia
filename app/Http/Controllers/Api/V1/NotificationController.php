<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\GuestNotification;
use App\Traits\Api\ApiResponser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
  use ApiResponser;

  public function index(Request $request): JsonResponse
  {
    $deviceId = (string) $request->header('X-Device-Id', '');
    $actorKey = (string) $request->header('X-Actor-Key', '');
    $eventCode = (string) config('xv.default_event_code', 'XV-NATALIA');

    if (!$deviceId && !$actorKey) {
      return $this->sendResponse(['notifications' => [], 'unread' => 0], 'Sin notificaciones.');
    }

    $notifications = GuestNotification::forEvent($eventCode)
      ->forOwner($deviceId, $actorKey)
      ->orderByDesc('created_at')
      ->limit(50)
      ->get()
      ->map(static fn ($n) => [
        'id'               => $n->id,
        'type'             => $n->type,
        'actorName'        => $n->actor_name,
        'actorKey'         => $n->actor_key,
        'memoryExternalId' => $n->memory_external_id,
        'memoryCaption'    => $n->memory_caption,
        'mediaThumbUrl'    => $n->media_thumb_url,
        'extra'            => $n->extra,
        'isRead'           => (bool) $n->is_read,
        'createdAt'        => $n->created_at?->toIso8601String(),
      ]);

    $unread = $notifications->where('isRead', false)->count();

    return $this->sendResponse(
      ['notifications' => $notifications->values()->all(), 'unread' => $unread],
      'Notificaciones recuperadas.'
    );
  }

  public function markRead(Request $request, int|string $id): JsonResponse
  {
    $deviceId = (string) $request->header('X-Device-Id', '');
    $actorKey = (string) $request->header('X-Actor-Key', '');

    GuestNotification::forOwner($deviceId, $actorKey)
      ->where('id', $id)
      ->update(['is_read' => true]);

    return $this->sendResponse([], 'Notificación marcada como leída.');
  }

  public function markAllRead(Request $request): JsonResponse
  {
    $deviceId = (string) $request->header('X-Device-Id', '');
    $actorKey = (string) $request->header('X-Actor-Key', '');
    $eventCode = (string) config('xv.default_event_code', 'XV-NATALIA');

    GuestNotification::forEvent($eventCode)
      ->forOwner($deviceId, $actorKey)
      ->unread()
      ->update(['is_read' => true]);

    return $this->sendResponse([], 'Todas las notificaciones marcadas como leídas.');
  }
}
