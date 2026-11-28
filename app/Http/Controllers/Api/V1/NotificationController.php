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

    $mapper = static fn ($n) => [
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
    ];

    // Privadas: reacciones y comentarios dirigidos al dueño
    $private = GuestNotification::forEvent($eventCode)
      ->forOwner($deviceId, $actorKey)
      ->whereIn('type', ['reaction', 'comment'])
      ->orderByDesc('created_at')
      ->limit(40)
      ->get()
      ->map($mapper);

    // Globales: checkins de nuevos invitados (broadcast a todos)
    $global = GuestNotification::forEvent($eventCode)
      ->where('owner_device_id', 'broadcast')
      ->where('type', 'checkin')
      ->orderByDesc('created_at')
      ->limit(20)
      ->get()
      ->map($mapper);

    $notifications = $private->merge($global)
      ->sortByDesc('createdAt')
      ->values();

    $unread = $notifications->where('isRead', false)->count();

    return $this->sendResponse(
      ['notifications' => $notifications->all(), 'unread' => $unread],
      'Notificaciones recuperadas.'
    );
  }

  public function markRead(Request $request, int|string $id): JsonResponse
  {
    $deviceId = (string) $request->header('X-Device-Id', '');
    $actorKey = (string) $request->header('X-Actor-Key', '');

    // Para notificaciones broadcast (checkin), marcarlas sin restricción de owner
    GuestNotification::where('id', $id)
      ->where(function ($q) use ($deviceId, $actorKey): void {
        $q->where('owner_device_id', 'broadcast')
          ->orWhere('owner_device_id', $deviceId)
          ->orWhere('owner_actor_key', $actorKey);
      })
      ->update(['is_read' => true]);

    return $this->sendResponse([], 'Notificación marcada como leída.');
  }

  public function markAllRead(Request $request): JsonResponse
  {
    $deviceId = (string) $request->header('X-Device-Id', '');
    $actorKey = (string) $request->header('X-Actor-Key', '');
    $eventCode = (string) config('xv.default_event_code', 'XV-NATALIA');

    // Marcar privadas
    GuestNotification::forEvent($eventCode)
      ->forOwner($deviceId, $actorKey)
      ->unread()
      ->update(['is_read' => true]);

    // No marcar las broadcast como leídas globalmente — cada cliente las ve como unread
    // hasta que abra el panel (se manejan solo en cliente)

    return $this->sendResponse([], 'Todas las notificaciones marcadas como leídas.');
  }
}
