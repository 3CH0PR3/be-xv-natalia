<?php

namespace App\Http\Middleware;

use App\Models\DeviceFingerprintIndex;
use App\Models\DeviceLock;
use App\Models\Event;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Replica en backend la política de useQrInteractionGate.js (v1.5.1):
 * Ningún dispositivo sin check-in QR activo (escritorio solo lectura, móvil sin QR
 * o invitado expulsado / con BD purgada) puede publicar, reaccionar, comentar ni sumar compartidos.
 */
class EnsureActiveDeviceLockMiddleware
{
  public function handle(Request $request, Closure $next): Response
  {
    $incomingPin = $request->hostPin();
    if ($incomingPin !== '' && User::verifyHostPin($incomingPin)) {
      return $next($request);
    }

    $eventCode = $request->eventCode();
    $deviceId = $request->deviceId();
    $actorKey = $request->actorKey();

    if ($deviceId === '' && $actorKey === '') {
      return response()->json([
        'code' => 'QR_SESSION_REQUIRED',
        'message' => 'Escanea el código QR de tu mesa para interactuar en el muro.',
      ], Response::HTTP_FORBIDDEN);
    }

    $event = Event::byCode($eventCode)->first();
    if (!$event) {
      return response()->json([
        'code' => 'EVENT_NOT_FOUND',
        'message' => 'Evento no encontrado.',
      ], Response::HTTP_NOT_FOUND);
    }

    $directLock = $deviceId !== ''
      ? DeviceLock::forEvent($event->id)->byDeviceId($deviceId)->first()
      : null;

    $fpMatch = (!$directLock && $deviceId !== '')
      ? DeviceFingerprintIndex::with('deviceLock')
        ->where('event_id', $event->id)
        ->where('fingerprint', $deviceId)
        ->first()
      : null;

    $fpLock = $fpMatch?->deviceLock;

    $actorLock = (!$directLock && !$fpLock && $actorKey !== '')
      ? DeviceLock::forEvent($event->id)->where('actor_key', $actorKey)->first()
      : null;

    $activeLock = $directLock ?: ($fpLock ?: $actorLock);

    if ($actorLock && $deviceId !== '') {
      DeviceFingerprintIndex::updateOrCreate(
        ['event_id' => $event->id, 'fingerprint' => $deviceId],
        ['device_lock_id' => $actorLock->id]
      );
    }

    if (!$activeLock) {
      return response()->json([
        'code' => 'DEVICE_LOCK_MISSING',
        'confirmedMissingOnServer' => true,
        'message' => 'Tu dispositivo no tiene una mesa QR activa en el servidor.',
      ], Response::HTTP_FORBIDDEN);
    }

    $request->attributes->set('activeDeviceLock', $activeLock);
    $request->merge(['deviceLockId' => $activeLock->id]);

    return $next($request);
  }
}
