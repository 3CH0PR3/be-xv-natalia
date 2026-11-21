<?php

use App\Http\Controllers\Api\V1\ProfileController;
use Illuminate\Support\Facades\Route;

/*
|-------------------------------------------------------------
| APIs - GUEST PROFILES
|-------------------------------------------------------------
*/
Route::get('/profile', [ProfileController::class, 'myProfile']);
Route::post('/profile', [ProfileController::class, 'update'])->middleware('throttle:api.memories.interactions');
Route::get('/profile/{id}/avatar', [ProfileController::class, 'avatar']);
Route::get('/profile/{id}/cover', [ProfileController::class, 'cover']);
Route::get('/profile/{id}', [ProfileController::class, 'show']);
