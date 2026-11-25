<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

/**
 * Rate limiters centralizados por dispositivo / IP.
 */
class RateLimiterProvider extends ServiceProvider
{
  public function boot(): void
  {
    $this->registerApiLimiters();
  }

  private function registerApiLimiters(): void
  {
    $resolveActorKey = static fn (Request $request): string => (string) ($request->deviceId() ?: ($request->actorKey() ?: $request->ip()));

    RateLimiter::for('api', static function (Request $request) use ($resolveActorKey) {
      return Limit::perMinute(180)->by($resolveActorKey($request));
    });

    RateLimiter::for('api.celebrate', static function (Request $request) use ($resolveActorKey) {
      return Limit::perMinute(120)->by($resolveActorKey($request));
    });

    RateLimiter::for('api.checkin', static function (Request $request) use ($resolveActorKey) {
      return Limit::perMinute(60)->by($resolveActorKey($request));
    });

    RateLimiter::for('api.memories.store', static function (Request $request) use ($resolveActorKey) {
      return Limit::perMinute(45)->by($resolveActorKey($request));
    });

    RateLimiter::for('api.memories.chunks', static function (Request $request) use ($resolveActorKey) {
      return Limit::perMinute(6000)->by($resolveActorKey($request));
    });

    RateLimiter::for('api.memories.interactions', static function (Request $request) use ($resolveActorKey) {
      return Limit::perMinute(180)->by($resolveActorKey($request));
    });

    RateLimiter::for('api.memories.reactions', static function (Request $request) use ($resolveActorKey) {
      return Limit::perMinute(60)
        ->by($resolveActorKey($request))
        ->response(static fn () => response()->json([
          'success' => false,
          'code' => 429,
          'message' => 'Has alcanzado el límite de reacciones permitidas por minuto. Por favor, espera un momento antes de volver a reaccionar.',
          'data' => null,
        ], 429));
    });

    RateLimiter::for('api.comments', static function (Request $request) use ($resolveActorKey) {
      return Limit::perMinute(30)
        ->by($resolveActorKey($request))
        ->response(static fn () => response()->json([
          'success' => false,
          'code' => 429,
          'message' => 'Has alcanzado el límite de comentarios permitidos por minuto. Por favor, espera un momento antes de volver a comentar.',
          'data' => null,
        ], 429));
    });
  }
}
