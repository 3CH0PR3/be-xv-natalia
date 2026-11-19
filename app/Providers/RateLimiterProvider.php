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
    RateLimiter::for('api', static function (Request $request) {
      $key = $request->header('X-Device-Id') ?: $request->ip();
      return Limit::perMinute(120)->by($key);
    });

    RateLimiter::for('api.celebrate', static function (Request $request) {
      $key = $request->header('X-Device-Id') ?: $request->ip();
      return Limit::perMinute(120)->by($key);
    });

    RateLimiter::for('api.checkin', static function (Request $request) {
      $key = $request->header('X-Device-Id') ?: $request->ip();
      return Limit::perMinute(60)->by($key);
    });

    RateLimiter::for('api.memories.store', static function (Request $request) {
      $key = $request->header('X-Device-Id') ?: $request->ip();
      return Limit::perMinute(30)->by($key);
    });

    RateLimiter::for('api.memories.interactions', static function (Request $request) {
      $key = $request->header('X-Device-Id') ?: $request->ip();
      return Limit::perMinute(180)->by($key);
    });

    RateLimiter::for('api.memories.reactions', static function (Request $request) {
      $key = (string) ($request->header('X-Device-Id') ?: ($request->header('X-Actor-Key') ?: $request->ip()));
      return Limit::perMinute(5)
        ->by($key)
        ->response(static fn () => response()->json([
          'success' => false,
          'code' => 429,
          'message' => 'Has alcanzado el límite de 5 reacciones por minuto. Por favor, espera un momento antes de volver a reaccionar.',
          'data' => null,
        ], 429));
    });

    RateLimiter::for('api.comments', static function (Request $request) {
      $key = (string) ($request->header('X-Device-Id') ?: ($request->header('X-Actor-Key') ?: $request->ip()));
      return Limit::perMinute(10)
        ->by($key)
        ->response(static fn () => response()->json([
          'success' => false,
          'code' => 429,
          'message' => 'Has alcanzado el límite de 10 comentarios por minuto. Por favor, espera un momento antes de volver a comentar.',
          'data' => null,
        ], 429));
    });
  }
}
