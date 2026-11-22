<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;

class MonitoringAuthService
{
  public const SESSION_KEY = 'monitoring_authenticated';
  public const COOKIE_KEY = 'monitoring_auth_token';

  /**
   * Obtiene el PIN configurado para dashboards de monitoreo (Horizon y Pulse).
   */
  public static function getExpectedPin(): string
  {
    $pin = (string) config('xv.monitoring_pin', env('XV_MONITORING_PIN', '$*Developer1'));
    return trim($pin) !== '' ? trim($pin) : '$*Developer1';
  }

  /**
   * Determina si la petición actual está autorizada para ver los dashboards.
   * Admite:
   * 1. PIN en header (X-Host-Pin o Host-Pin), query (?pin=...) o body (pin).
   * 2. Sesión activa previamente autenticada (para llamadas AJAX internas de Horizon/Pulse).
   * 3. Cookie segura de monitoreo para navegación persistente entre pestañas.
   */
  public static function authorize(Request $request): bool
  {
    $expectedPin = self::getExpectedPin();
    if ($expectedPin === '') {
      return false;
    }

    $expectedHash = hash('sha256', $expectedPin);

    // 1. Verificación directa si el PIN viene en la petición actual
    $incomingPin = (string) $request->hostPin();
    if ($incomingPin !== '' && hash_equals($expectedPin, $incomingPin)) {
      if ($request->hasSession()) {
        $request->session()->put(self::SESSION_KEY, true);
      }
      Cookie::queue(self::COOKIE_KEY, $expectedHash, 60 * 24 * 7, null, null, null, false);
      return true;
    }

    // 2. Si ya fue autenticado en la sesión de la sesión web actual (AJAX /horizon/api/...)
    if ($request->hasSession() && $request->session()->get(self::SESSION_KEY) === true) {
      return true;
    }

    // 3. Si viene la cookie segura previamente emitida
    $cookieToken = (string) $request->cookie(self::COOKIE_KEY);
    if ($cookieToken !== '' && hash_equals($expectedHash, $cookieToken)) {
      if ($request->hasSession()) {
        $request->session()->put(self::SESSION_KEY, true);
      }
      return true;
    }

    return false;
  }
}
