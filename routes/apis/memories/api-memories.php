<?php

use App\Http\Controllers\Api\V1\MemoryController;
use App\Http\Controllers\Api\V1\MemoryReactionController;
use Illuminate\Support\Facades\Route;

// Muro de recuerdos y multimedia
Route::get('/memories', [MemoryController::class, 'index']);
Route::get('/memories/{externalId}', [MemoryController::class, 'show']);
Route::post('/memories', [MemoryController::class, 'store'])
  ->middleware(['device.lock', 'throttle:api.memories.store']);
Route::patch('/memories/{externalId}/caption', [MemoryController::class, 'updateCaption'])
  ->middleware(['device.lock', 'throttle:api.memories.interactions']);
Route::delete('/memories/{externalId}', [MemoryController::class, 'destroy'])
  ->middleware('device.lock');
Route::delete('/memories/{externalId}/media/{mediaItemId}', [MemoryController::class, 'destroyMediaItem'])
  ->middleware('device.lock');

// Reacciones y compartidos en recuerdos
Route::post('/memories/{externalId}/reactions/toggle', [MemoryReactionController::class, 'toggle'])
  ->middleware(['device.lock', 'throttle:api.memories.reactions']);
Route::post('/memories/{externalId}/shares', [MemoryReactionController::class, 'share'])
  ->middleware(['device.lock', 'throttle:api.memories.interactions']);
Route::post('/memories/{externalId}/share', [MemoryController::class, 'incrementShare'])
  ->middleware(['device.lock', 'throttle:api.memories.interactions']);
