<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyHostPinMiddleware
{
  public function handle(Request $request, Closure $next): Response
  {
    $incomingPin = $request->hostPin();

    if ($incomingPin === '' || !User::verifyHostPin($incomingPin)) {
      return response()->json([
        'success' => false,
        'code' => 'HOST_PIN_INVALID',
        'message' => 'PIN de anfitriona inválido o ausente.',
      ], Response::HTTP_FORBIDDEN);
    }

    return $next($request);
  }
}
