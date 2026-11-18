<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyHostPinMiddleware
{
  public function handle(Request $request, Closure $next): Response
  {
    $configuredPin = trim((string) config('xv.host_pin', '1515'), " \t\n\r\0\x0B\"'");
    if ($configuredPin === '') {
      $configuredPin = '1515';
    }

    $rawPin = (string) (
      $request->header('X-Host-Pin')
      ?: $request->header('Host-Pin')
      ?: $request->header('x-host-pin')
      ?: $request->input('pin')
      ?: $request->input('hostPin')
      ?: $request->input('host_pin')
      ?: ($request->bearerToken() ?: '')
    );

    $incomingPin = trim($rawPin, " \t\n\r\0\x0B\"'");

    if ($incomingPin === '' || !hash_equals($configuredPin, $incomingPin)) {
      return response()->json([
        'success' => false,
        'code' => 'HOST_PIN_INVALID',
        'message' => 'PIN de anfitriona inválido o ausente.',
      ], Response::HTTP_FORBIDDEN);
    }

    return $next($request);
  }
}
