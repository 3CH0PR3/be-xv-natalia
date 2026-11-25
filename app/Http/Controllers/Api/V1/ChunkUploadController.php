<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\InitChunkUploadRequest;
use App\Http\Requests\Api\V1\StoreChunkPartRequest;
use App\Services\ChunkUploadService;
use App\Traits\Api\ApiResponser;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class ChunkUploadController extends Controller
{
  use ApiResponser;

  public function __construct(
    private readonly ChunkUploadService $chunkService,
  ) {}

  public function init(InitChunkUploadRequest $request): JsonResponse
  {
    return $this->sendResponse(
      $this->chunkService->initUpload($request->toDto()),
      'Sesión de carga iniciada.',
      Response::HTTP_CREATED
    );
  }

  public function storeChunk(string $uploadKey, StoreChunkPartRequest $request): JsonResponse
  {
    return $this->sendResponse(
      $this->chunkService->storeChunk($request->toDto($uploadKey)),
      'Fragmento almacenado.',
      200
    );
  }
}
