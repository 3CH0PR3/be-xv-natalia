<?php

namespace App\Actions\Profile;

use App\DTOs\Profile\UpdateProfileData;
use App\Models\DeviceLock;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class UpdateProfileAction
{
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

    return $this->getProfile->execute(
      targetIdentifier: (string) $lock->id,
      viewerDeviceId: $data->ownerDeviceId,
      viewerActorKey: $data->ownerActorKey,
      eventCode: $eventCode
    );
  }
}
