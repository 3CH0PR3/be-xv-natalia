<?php

use App\Http\Controllers\Api\V1\UploadOperationController;
use Illuminate\Support\Facades\Route;

Route::get('/upload-operations', [UploadOperationController::class, 'index']);

Route::post('/upload-operations', [UploadOperationController::class, 'store'])
  ->middleware(['device.lock', 'throttle:api.memories.store']);

Route::get('/upload-operations/{operationKey}', [UploadOperationController::class, 'show']);

Route::post('/upload-operations/{operationKey}/files/{fileKey}/chunks', [UploadOperationController::class, 'storeChunk'])
  ->middleware(['device.lock', 'throttle:api.memories.chunks']);

Route::post('/upload-operations/{operationKey}/files/{fileKey}/chunk-stream', [UploadOperationController::class, 'storeBinaryChunk'])
  ->middleware(['device.lock', 'throttle:api.memories.chunks']);

Route::patch('/upload-operations/{operationKey}/files/{fileKey}/meta', [UploadOperationController::class, 'updateFileMeta'])
  ->middleware(['device.lock', 'throttle:api.memories.chunks']);

Route::post('/upload-operations/{operationKey}/files/{fileKey}/poster', [UploadOperationController::class, 'attachPoster'])
  ->middleware(['device.lock', 'throttle:api.memories.chunks']);

Route::post('/upload-operations/{operationKey}/files/{fileKey}/fail', [UploadOperationController::class, 'failFile'])
  ->middleware(['device.lock', 'throttle:api.memories.chunks']);

Route::delete('/upload-operations/{operationKey}', [UploadOperationController::class, 'destroy'])
  ->middleware(['device.lock']);
