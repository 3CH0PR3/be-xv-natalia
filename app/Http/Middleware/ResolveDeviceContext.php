<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveDeviceContext
{
  /**
   * Extrae y normaliza el contexto de dispositivo, actor, evento y PIN de anfitriona
   * desde headers, query params o body inputs, asociándolos a los atributos de la petición.
   */
  public function handle(Request $request, Closure $next): Response
  {
    $deviceId = $this->firstNonEmpty([
      $request->header('X-Device-Id'),
      $request->header('x-device-id'),
      $request->query('deviceId'),
      $request->query('device_id'),
      $request->input('deviceId'),
      $request->input('device_id'),
      $request->input('ownerDeviceId'),
    ]);

    $actorKey = $this->firstNonEmpty([
      $request->header('X-Actor-Key'),
      $request->header('x-actor-key'),
      $request->query('actorKey'),
      $request->query('actor_key'),
      $request->input('actorKey'),
      $request->input('actor_key'),
      $request->input('ownerActorKey'),
    ]);

    $eventCode = $this->firstNonEmpty([
      $request->header('X-Event-Code'),
      $request->header('x-event-code'),
      $request->query('event_code'),
      $request->query('eventCode'),
      $request->input('eventCode'),
      $request->input('event_code'),
      config('xv.default_event_code', 'XV-NATALIA'),
    ]);

    $hostPin = $this->firstNonEmpty([
      $request->header('X-Host-Pin'),
      $request->header('Host-Pin'),
      $request->header('x-host-pin'),
      $request->input('hostPin'),
      $request->input('host_pin'),
      $request->input('pin'),
      $request->query('hostPin'),
      $request->query('host_pin'),
      $request->query('pin'),
      $request->bearerToken(),
    ]);

    $request->attributes->set('deviceId', $deviceId);
    $request->attributes->set('actorKey', $actorKey);
    $request->attributes->set('eventCode', $eventCode);
    $request->attributes->set('hostPin', $hostPin);

    return $next($request);
  }

  private function firstNonEmpty(array $candidates): string
  {
    foreach ($candidates as $candidate) {
      if ($candidate !== null && is_scalar($candidate)) {
        $trimmed = trim((string) $candidate, " \t\n\r\0\x0B\"'");
        if ($trimmed !== '') {
          return $trimmed;
        }
      }
    }

    return '';
  }
}
