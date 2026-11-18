<?php

use App\Http\Controllers\Api\V1\AppReleaseController;
use Illuminate\Support\Facades\Route;

// Control de versiones PWA
Route::get('/releases', [AppReleaseController::class, 'index']);
Route::post('/releases/sync', [AppReleaseController::class, 'sync']);
