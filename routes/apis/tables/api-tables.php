<?php

use App\Http\Controllers\Api\V1\DeviceLockController;
use App\Http\Controllers\Api\V1\TableController;
use App\Http\Controllers\Api\V1\TableGuestController;
use App\Http\Middleware\VerifyHostPinMiddleware;
use Illuminate\Support\Facades\Route;

// Mesas QR, identidad de dispositivo & Check-in
Route::get('/tables', [TableController::class, 'index']);
Route::post('/tables/generate-qrs', [TableController::class, 'generateQrs'])
  ->middleware([VerifyHostPinMiddleware::class]);

// Expulsión de invitados por anfitriona
Route::post('/tables/expel', [TableGuestController::class, 'expel'])
  ->middleware([VerifyHostPinMiddleware::class]);
Route::post('/tables/{tableId}/expel', [TableGuestController::class, 'expel'])
  ->middleware([VerifyHostPinMiddleware::class]);
Route::post('/tables/{tableId}/guests/expel', [TableGuestController::class, 'expel'])
  ->middleware([VerifyHostPinMiddleware::class]);

// Bloqueos de dispositivo y Check-in
Route::post('/device-locks/resolve', [DeviceLockController::class, 'resolve']);
Route::get('/device-locks/{deviceId}/status', [DeviceLockController::class, 'status']);
Route::post('/device-locks/expel', [DeviceLockController::class, 'expel'])
  ->middleware([VerifyHostPinMiddleware::class]);
Route::post('/checkin', [DeviceLockController::class, 'checkin'])
  ->middleware('throttle:api.checkin');
