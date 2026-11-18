<?php

use App\Exceptions\DomainRuleException;
use App\Http\Middleware\EnsureActiveDeviceLockMiddleware;
use App\Http\Middleware\VerifyHostPinMiddleware;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
  ->withRouting(
    web: __DIR__.'/../routes/web.php',
    commands: __DIR__.'/../routes/console.php',
    channels: __DIR__.'/../routes/channels.php',
    health: '/up',
  )
  ->withMiddleware(function (Middleware $middleware): void {
    $middleware->alias([
      'host.pin' => VerifyHostPinMiddleware::class,
      'device.lock' => EnsureActiveDeviceLockMiddleware::class,
    ]);
  })
  ->withExceptions(function (Exceptions $exceptions): void {
    $exceptions->dontReport(DomainRuleException::class);
    $exceptions->render(function (DomainRuleException $e, Request $request) {
      if ($request->expectsJson() || $request->is('api/*')) {
        return response()->json($e->toArray(), $e->httpStatus);
      }

      return null;
    });
  })->create();
