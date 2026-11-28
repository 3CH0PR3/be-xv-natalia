<?php

use App\Http\Controllers\Api\V1\NotificationController;
use Illuminate\Support\Facades\Route;

/*
|-------------------------------------------------------------
| APIs - GUEST NOTIFICATIONS
|-------------------------------------------------------------
*/
Route::get('/notifications', [NotificationController::class, 'index']);
Route::post('/notifications/read', [NotificationController::class, 'markAllRead']);
Route::post('/notifications/{id}/read', [NotificationController::class, 'markRead']);
