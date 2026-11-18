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

    RateLimiter::for('api.comments', static function (Request $request) {
      $key = $request->header('X-Device-Id') ?: $request->ip();
      return Limit::perMinute(60)->by($key);
    });
  }
}
