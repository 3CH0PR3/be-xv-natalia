<?php

namespace App\Actions\Notification;

use App\Events\GuestNotificationCreated;
use App\Models\GuestNotification;
use App\Models\Memory;
use Illuminate\Support\Facades\Log;

final readonly class CreateGuestNotificationAction
{
  /**
   * Crea una notificación para el dueño de un recuerdo cuando alguien
   * reacciona, comenta o da like a un comentario en su álbum.
   *
   * No genera notificación si el actor ES el dueño (evitar auto-notificación).
   */
  public function execute(
    string $type,
    Memory $memory,
    string $actorName,
    string $actorKey,
    string $actorDeviceId,
    string $extra = '',
  ): void {
    $ownerDeviceId = (string) ($memory->owner_device_id ?? '');
    $ownerActorKey = (string) ($memory->owner_actor_key ?? '');

    // No notificar al propio autor
    if ($actorKey === $ownerActorKey || $actorDeviceId === $ownerDeviceId) {
      return;
    }

    if (!$ownerDeviceId && !$ownerActorKey) {
      return;
    }

    $eventCode = $memory->event?->code ?? config('xv.default_event_code', 'XV-NATALIA');

    $notification = GuestNotification::create([
      'event_code'         => $eventCode,
      'owner_device_id'    => $ownerDeviceId,
      'owner_actor_key'    => $ownerActorKey,
      'type'               => $type,
      'actor_name'         => $actorName,
      'actor_key'          => $actorKey,
      'memory_external_id' => $memory->external_id,
      'memory_caption'     => mb_strimwidth((string) ($memory->caption ?? ''), 0, 120),
      'media_thumb_url'    => $this->resolveThumb($memory),
      'extra'              => mb_strimwidth($extra, 0, 240),
      'is_read'            => false,
    ]);

    try {
      broadcast(new GuestNotificationCreated(
        $eventCode,
        $ownerDeviceId,
        $ownerActorKey,
        [
          'id'               => $notification->id,
          'type'             => $type,
          'actorName'        => $actorName,
          'actorKey'         => $actorKey,
          'memoryExternalId' => $memory->external_id,
          'memoryCaption'    => $notification->memory_caption,
          'mediaThumbUrl'    => $notification->media_thumb_url,
          'extra'            => $extra,
          'isRead'           => false,
          'createdAt'        => $notification->created_at?->toIso8601String(),
        ]
      ));
    } catch (\Throwable $e) {
      Log::warning('[Notification] broadcast error', ['error' => $e->getMessage()]);
    }
  }

  private function resolveThumb(Memory $memory): string
  {
    $item = $memory->mediaItems?->first();
    if (!$item) return '';
    $path = $item->thumb_path ?: $item->poster_path ?: $item->file_path ?: '';
    if (!$path) return '';
    try {
      return \Illuminate\Support\Facades\Storage::disk($item->disk ?: 'public')->url($path);
    } catch (\Throwable) {
      return '';
    }
  }
}
