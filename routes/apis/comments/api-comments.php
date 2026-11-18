<?php

use App\Http\Controllers\Api\V1\MemoryCommentController;
use Illuminate\Support\Facades\Route;

// Comentarios en recuerdos
Route::get('/memories/{externalId}/comments', [MemoryCommentController::class, 'index']);
Route::post('/memories/{externalId}/comments', [MemoryCommentController::class, 'store'])
  ->middleware(['device.lock', 'throttle:api.comments']);
Route::patch('/memories/{externalId}/comments/{commentExternalId}', [MemoryCommentController::class, 'update'])
  ->middleware(['device.lock', 'throttle:api.comments']);
Route::delete('/memories/{externalId}/comments/{commentExternalId}', [MemoryCommentController::class, 'destroy'])
  ->middleware('device.lock');
Route::post('/memories/{externalId}/comments/{commentExternalId}/like', [MemoryCommentController::class, 'toggleLike'])
  ->middleware(['device.lock', 'throttle:api.memories.interactions']);
