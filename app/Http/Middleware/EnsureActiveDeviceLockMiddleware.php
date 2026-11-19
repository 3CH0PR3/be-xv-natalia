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
    $incomingPin = trim((string) ($request->header('X-Host-Pin')
      ?: $request->input('hostPin')
      ?: $request->query('host_pin')
      ?: ''));
    if ($incomingPin !== '' && User::verifyHostPin($incomingPin)) {
      return $next($request);
    }

    $eventCode = trim((string) ($request->query('event_code')
      ?: $request->input('eventCode')
      ?: config('xv.default_event_code', 'XV-NATALIA')));
    $deviceId = trim((string) ($request->header('X-Device-Id')
      ?: $request->input('deviceId')
      ?: $request->input('ownerDeviceId')
      ?: ''));
    $actorKey = trim((string) ($request->header('X-Actor-Key')
      ?: $request->input('actorKey')
      ?: $request->input('ownerActorKey')
      ?: ''));

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

    return $next($request);
  }
}
