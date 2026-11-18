<?php

use App\Http\Controllers\Api\V1\ModerationController;
use Illuminate\Support\Facades\Route;

// Panel de Moderación (protegido por X-Host-Pin)
Route::prefix('moderation')->middleware('host.pin')->group(function (): void {
  Route::post('/verify-pin', [ModerationController::class, 'verifyPin']);
  Route::get('/metrics', [ModerationController::class, 'metrics']);
  Route::patch('/memories/{externalId}/status', [ModerationController::class, 'updateStatus']);
  Route::post('/tables/expel', [ModerationController::class, 'expelGuest']);
  Route::get('/export-zip', [ModerationController::class, 'exportZip']);
  Route::post('/purge', [ModerationController::class, 'purgeEvent']);
});
