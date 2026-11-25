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
    $result = $this->chunkService->initUpload(
      filename: trim((string) $request->input('filename')),
      mimeType: trim((string) ($request->input('mimeType') ?: 'application/octet-stream')),
      totalSize: (int) $request->input('totalSize'),
      totalChunks: (int) $request->input('totalChunks'),
      actorKey: $request->actorKey() ?: null,
      deviceId: $request->deviceId() ?: null,
    );

    return $this->sendResponse($result, 'Sesión de carga iniciada.', Response::HTTP_CREATED);
  }

  public function storeChunk(string $uploadKey, StoreChunkPartRequest $request): JsonResponse
  {
    $result = $this->chunkService->storeChunk(
      uploadKey: $uploadKey,
      chunkIndex: (int) $request->input('chunkIndex'),
      chunkFile: $request->file('chunk'),
      actorKey: $request->actorKey() ?: null,
      deviceId: $request->deviceId() ?: null,
    );

    return $this->sendResponse($result, 'Fragmento almacenado.', 200);
  }
}
