<?php

namespace App\Actions\Profile;

use App\DTOs\Profile\UpdateProfileData;
use App\Events\ProfileUpdated;
use App\Jobs\ProcessProfileUpdateJob;
use App\Models\DeviceLock;
use App\Support\Cache\Keys\EventKeys;
use App\Traits\Cache\InvalidatesCache;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class UpdateProfileAction
{
  use InvalidatesCache;

  public function __construct(
    private readonly GetProfileAction $getProfile
  ) {}

  public function execute(UpdateProfileData $data): array
  {
    if (!$data->ownerDeviceId && !$data->ownerActorKey) {
      throw new NotFoundHttpException('Se requieren credenciales de dispositivo o invitado para actualizar el perfil.');
    }

    $lock = DeviceLock::with(['event', 'table'])
      ->where(function (Builder $q) use ($data): void {
        if ($data->ownerActorKey) {
          $q->orWhere('actor_key', $data->ownerActorKey);
        }
        if ($data->ownerDeviceId) {
          $q->orWhere('device_id', $data->ownerDeviceId);
        }
      })
      ->first();

    if (!$lock) {
      throw new NotFoundHttpException('No se encontró una sesión activa de invitado para este dispositivo.');
    }

    $diskName = config('xv.media_disk', 'public');
    $disk = Storage::disk($diskName);
    $eventCode = $lock->event?->code ?: 'XV-NATALIA';

    // 1. Guardar nueva foto de perfil (avatar)
    if ($data->avatar) {
      if ($lock->avatar_path && $disk->exists($lock->avatar_path)) {
        $disk->delete($lock->avatar_path);
      }
      $ext = $data->avatar->extension() ?: 'jpg';
      $avatarName = "avatar-{$lock->id}-" . time() . '.' . $ext;
      $avatarPath = $data->avatar->storeAs("profiles/{$eventCode}/avatars", $avatarName, $diskName);
      $lock->avatar_path = $avatarPath;
    }

    // 2. Guardar nueva foto de portada / background
    if ($data->cover) {
      if ($lock->cover_path && $disk->exists($lock->cover_path)) {
        $disk->delete($lock->cover_path);
      }
      $ext = $data->cover->extension() ?: 'jpg';
      $coverName = "cover-{$lock->id}-" . time() . '.' . $ext;
      $coverPath = $data->cover->storeAs("profiles/{$eventCode}/covers", $coverName, $diskName);
      $lock->cover_path = $coverPath;
    }

    // 3. Actualizar campos de texto
    if ($data->bio !== null) {
      $lock->bio = trim($data->bio);
    }

    if ($data->location !== null) {
      $lock->location = trim($data->location);
    }

    if ($data->guestName !== null && trim($data->guestName) !== '') {
      $trimmedName = trim($data->guestName);
      $lock->guest_name = $trimmedName;
      $lock->guest_name_slug = DeviceLock::normalizeSlug($trimmedName);
    }

    $lock->save();

    // Sincronizar retroactivamente el device_lock_id y el nombre en recuerdos creados por este invitado
    $memoryUpdates = ['device_lock_id' => $lock->id];
    if ($lock->guest_name) {
      $memoryUpdates['guest_name'] = $lock->guest_name;
    }

    \App\Models\Memory::forEvent($lock->event_id)
      ->where(function (Builder $q) use ($lock): void {
        if ($lock->id) {
          $q->orWhere('device_lock_id', $lock->id);
        }
        if ($lock->actor_key) {
          $q->orWhere('owner_actor_key', $lock->actor_key);
        }
        if ($lock->device_id) {
          $q->orWhere('owner_device_id', $lock->device_id);
        }
      })
      ->update($memoryUpdates);

    // Sincronizar retroactivamente el device_lock_id y autor en comentarios creados por este invitado
    $commentUpdates = ['device_lock_id' => $lock->id];
    if ($lock->guest_name) {
      $commentUpdates['author'] = $lock->guest_name;
    }

    \App\Models\MemoryComment::where(function (Builder $q) use ($lock): void {
        if ($lock->id) {
          $q->orWhere('device_lock_id', $lock->id);
        }
        if ($lock->actor_key) {
          $q->orWhere('owner_actor_key', $lock->actor_key);
        }
        if ($lock->device_id) {
          $q->orWhere('owner_device_id', $lock->device_id);
        }
      })
      ->update($commentUpdates);

    // Invalidar caché de perfil, feed y mesas en Redis
    $this->scanAndDelete(EventKeys::profilePattern($lock->event_id));
    $this->scanAndDelete(EventKeys::feedPattern($lock->event_id));
    $this->scanAndDelete(EventKeys::tables($lock->event_id));

    $profileData = $this->getProfile->execute(
      targetIdentifier: (string) $lock->id,
      viewerDeviceId: $data->ownerDeviceId,
      viewerActorKey: $data->ownerActorKey,
      eventCode: $eventCode
    );

    try {
      broadcast(new ProfileUpdated($eventCode, $profileData));
      ProcessProfileUpdateJob::dispatch($eventCode, $profileData);
    } catch (\Throwable $e) {
      Log::warning('Error broadcasting ProfileUpdated: ' . $e->getMessage());
    }

    return $profileData;
  }
}
