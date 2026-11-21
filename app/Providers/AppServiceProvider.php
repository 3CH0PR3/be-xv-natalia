<?php

namespace App\Providers;

use Illuminate\Http\Request;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
  /**
   * Register any application services.
   */
  public function register(): void
  {
    //
  }

  /**
   * Bootstrap any application services.
   */
  public function boot(): void
  {
    $extractFirst = static function (array $candidates): string {
      foreach ($candidates as $candidate) {
        if ($candidate !== null && is_scalar($candidate)) {
          $trimmed = trim((string) $candidate, " \t\n\r\0\x0B\"'");
          if ($trimmed !== '') {
            return $trimmed;
          }
        }
      }

      return '';
    };

    Request::macro('deviceId', function () use ($extractFirst): string {
      $attr = (string) ($this->attributes->get('deviceId') ?? '');
      if ($attr !== '') {
        return $attr;
      }

      return $extractFirst([
        $this->header('X-Device-Id'),
        $this->header('x-device-id'),
        $this->query('deviceId'),
        $this->query('device_id'),
        $this->input('deviceId'),
        $this->input('device_id'),
        $this->input('ownerDeviceId'),
      ]);
    });

    Request::macro('actorKey', function () use ($extractFirst): string {
      $attr = (string) ($this->attributes->get('actorKey') ?? '');
      if ($attr !== '') {
        return $attr;
      }

      return $extractFirst([
        $this->header('X-Actor-Key'),
        $this->header('x-actor-key'),
        $this->query('actorKey'),
        $this->query('actor_key'),
        $this->input('actorKey'),
        $this->input('actor_key'),
        $this->input('ownerActorKey'),
      ]);
    });

    Request::macro('eventCode', function () use ($extractFirst): string {
      $attr = (string) ($this->attributes->get('eventCode') ?? '');
      if ($attr !== '') {
        return $attr;
      }

      return $extractFirst([
        $this->header('X-Event-Code'),
        $this->header('x-event-code'),
        $this->query('event_code'),
        $this->query('eventCode'),
        $this->input('eventCode'),
        $this->input('event_code'),
        config('xv.default_event_code', 'XV-NATALIA'),
      ]);
    });

    Request::macro('hostPin', function () use ($extractFirst): string {
      $attr = (string) ($this->attributes->get('hostPin') ?? '');
      if ($attr !== '') {
        return $attr;
      }

      return $extractFirst([
        $this->header('X-Host-Pin'),
        $this->header('Host-Pin'),
        $this->header('x-host-pin'),
        $this->input('hostPin'),
        $this->input('host_pin'),
        $this->input('pin'),
        $this->query('hostPin'),
        $this->query('host_pin'),
        $this->query('pin'),
        $this->bearerToken(),
      ]);
    });
  }
}
