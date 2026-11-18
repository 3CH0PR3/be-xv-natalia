<?php

use App\Http\Controllers\Api\V1\EventStateController;
use Illuminate\Support\Facades\Route;

Route::get('/health', static fn () => response()->json([
    'success' => true,
    'status' => 'ok',
    'timestamp' => now()->toIso8601String(),
]));

// Evento global & Celebración
Route::get('/event', [EventStateController::class, 'show']);
Route::post('/event/celebrate', [EventStateController::class, 'celebrate'])
    ->middleware(['device.lock', 'throttle:api.celebrate']);
