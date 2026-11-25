<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\AttachOperationPosterRequest;
use App\Http\Requests\Api\V1\FailOperationFileRequest;
use App\Http\Requests\Api\V1\InitUploadOperationRequest;
use App\Http\Requests\Api\V1\StoreBinaryOperationChunkRequest;
use App\Http\Requests\Api\V1\StoreOperationChunkRequest;
use App\Http\Requests\Api\V1\UpdateOperationFileMetaRequest;
use App\Http\Requests\Api\V1\UploadOperationContextRequest;
use App\Http\Resources\Api\V1\UploadOperationResource;
use App\Services\UploadOperationService;
use App\Traits\Api\ApiResponser;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class UploadOperationController extends Controller
{
  use ApiResponser;

  public function __construct(
    private readonly UploadOperationService $uploadService,
  ) {}

  public function index(UploadOperationContextRequest $request): JsonResponse
  {
    $operations = $this->uploadService->listOperations($request->toDto());

    return $this->sendResponse(
      UploadOperationResource::collection($operations)->resolve($request),
      'Operaciones de subida recuperadas.'
    );
  }

  public function store(InitUploadOperationRequest $request): JsonResponse
  {
    $operation = $this->uploadService->createOperation($request->toDto());

    return $this->sendResponse(
      (new UploadOperationResource($operation))->resolve($request),
      'Operación de subida registrada.',
      Response::HTTP_CREATED
    );
  }

  public function show(string $operationKey, UploadOperationContextRequest $request): JsonResponse
  {
    $operation = $this->uploadService->findOperationForActor($request->toDto($operationKey));

    return $this->sendResponse(
      (new UploadOperationResource($operation))->resolve($request),
      'Estado de operación.'
    );
  }

  public function storeChunk(string $operationKey, string $fileKey, StoreOperationChunkRequest $request): JsonResponse
  {
    $operation = $this->uploadService->storeChunk($request->toDto($operationKey, $fileKey));

    return $this->sendResponse(
      (new UploadOperationResource($operation))->resolve($request),
      'Fragmento recibido.'
    );
  }

  public function storeBinaryChunk(string $operationKey, string $fileKey, StoreBinaryOperationChunkRequest $request): JsonResponse
  {
    $result = $this->uploadService->storeBinaryChunk($request->toDto($operationKey, $fileKey));
    if (!empty($result['operationModel'])) {
      $result['operationModel'] = (new UploadOperationResource($result['operationModel']))->resolve($request);
    }

    return $this->sendResponse($result, 'Fragmento binario recibido.');
  }

  public function attachPoster(string $operationKey, string $fileKey, AttachOperationPosterRequest $request): JsonResponse
  {
    $operation = $this->uploadService->attachPoster($request->toDto($operationKey, $fileKey));

    return $this->sendResponse(
      (new UploadOperationResource($operation))->resolve($request),
      'Portada actualizada.'
    );
  }

  public function updateFileMeta(string $operationKey, string $fileKey, UpdateOperationFileMetaRequest $request): JsonResponse
  {
    $operation = $this->uploadService->updateFileMetadata($request->toDto($operationKey, $fileKey));

    return $this->sendResponse(
      (new UploadOperationResource($operation))->resolve($request),
      'Metadatos de archivo actualizados.'
    );
  }

  public function failFile(string $operationKey, string $fileKey, FailOperationFileRequest $request): JsonResponse
  {
    $operation = $this->uploadService->markFileFailed($request->toDto($operationKey, $fileKey));

    return $this->sendResponse(
      (new UploadOperationResource($operation))->resolve($request),
      'Fallo de archivo registrado.'
    );
  }

  public function destroy(string $operationKey, UploadOperationContextRequest $request): JsonResponse
  {
    $this->uploadService->dismissOperation($request->toDto($operationKey));

    return $this->sendResponse(null, 'Operación ocultada.');
  }
}
