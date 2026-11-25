<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\AttachOperationPosterRequest;
use App\Http\Requests\Api\V1\FailOperationFileRequest;
use App\Http\Requests\Api\V1\InitUploadOperationRequest;
use App\Http\Requests\Api\V1\StoreOperationChunkRequest;
use App\Http\Resources\Api\V1\UploadOperationResource;
use App\Models\User;
use App\Services\UploadOperationService;
use App\Traits\Api\ApiResponser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class UploadOperationController extends Controller
{
  use ApiResponser;

  public function __construct(
    private readonly UploadOperationService $uploadService,
  ) {}

  public function index(Request $request): JsonResponse
  {
    $event = $this->uploadService->resolveEvent($request->eventCode());
    $operations = $this->uploadService->listOperations(
      $event,
      $request->deviceId() ?: null,
      $request->actorKey() ?: null,
    );

    return $this->sendResponse(
      UploadOperationResource::collection($operations)->resolve($request),
      'Operaciones de subida recuperadas.'
    );
  }

  public function store(InitUploadOperationRequest $request): JsonResponse
  {
    $event = $this->uploadService->resolveEvent($request->eventCode());
    $deviceLock = $request->attributes->get('activeDeviceLock');
    $origin = $request->getSchemeAndHttpHost();

    $operation = $this->uploadService->createOperation(
      event: $event,
      deviceLock: $deviceLock,
      deviceId: $request->deviceId() ?: null,
      actorKey: $request->actorKey() ?: null,
      payload: $request->validated(),
      requestOrigin: $origin !== '' ? $origin : null,
    );

    return $this->sendResponse(
      (new UploadOperationResource($operation))->resolve($request),
      'Operación de subida registrada.',
      Response::HTTP_CREATED
    );
  }

  public function show(string $operationKey, Request $request): JsonResponse
  {
    $event = $this->uploadService->resolveEvent($request->eventCode());
    $operation = $this->uploadService->findOperationForActor(
      operationKey: $operationKey,
      event: $event,
      deviceId: $request->deviceId() ?: null,
      actorKey: $request->actorKey() ?: null,
      isHost: $this->isHost($request),
    );

    return $this->sendResponse(
      (new UploadOperationResource($operation))->resolve($request),
      'Estado de operación.'
    );
  }

  public function storeChunk(string $operationKey, string $fileKey, StoreOperationChunkRequest $request): JsonResponse
  {
    $event = $this->uploadService->resolveEvent($request->eventCode());
    $operation = $this->uploadService->storeChunk(
      operationKey: $operationKey,
      fileKey: $fileKey,
      chunkIndex: (int) $request->input('chunkIndex'),
      chunkFile: $request->file('chunk'),
      event: $event,
      deviceId: $request->deviceId() ?: null,
      actorKey: $request->actorKey() ?: null,
      isHost: $this->isHost($request),
    );

    return $this->sendResponse(
      (new UploadOperationResource($operation))->resolve($request),
      'Fragmento recibido.'
    );
  }

  public function storeBinaryChunk(string $operationKey, string $fileKey, Request $request): JsonResponse
  {
    $chunkIndex = (int) ($request->header('X-Chunk-Index') ?? $request->query('chunkIndex', -1));
    $stream = fopen('php://input', 'rb');
    if ($stream === false) {
      return $this->sendError('No se pudo abrir el flujo binario de entrada.', [], 400);
    }

    if (app()->runningUnitTests()) {
      $probe = fread($stream, 1);
      if ($probe === '' || $probe === false) {
        $rawContent = $request->getContent();
        if ($rawContent !== '') {
          fclose($stream);
          $stream = fopen('php://temp', 'w+b');
          fwrite($stream, $rawContent);
          rewind($stream);
        }
      } else {
        $remainder = stream_get_contents($stream);
        fclose($stream);
        $stream = fopen('php://temp', 'w+b');
        fwrite($stream, $probe . ($remainder !== false ? $remainder : ''));
        rewind($stream);
      }
    }

    try {
      $event = $this->uploadService->resolveEvent($request->eventCode());
      $result = $this->uploadService->storeBinaryChunk(
        operationKey: $operationKey,
        fileKey: $fileKey,
        chunkIndex: $chunkIndex,
        inputStream: $stream,
        event: $event,
        deviceId: $request->deviceId() ?: null,
        actorKey: $request->actorKey() ?: null,
        isHost: $this->isHost($request),
      );

      if (!empty($result['operationModel'])) {
        $result['operationModel'] = (new UploadOperationResource($result['operationModel']))->resolve($request);
      }

      return $this->sendResponse($result, 'Fragmento binario recibido.');
    } finally {
      if (is_resource($stream)) {
        fclose($stream);
      }
    }
  }

  public function attachPoster(string $operationKey, string $fileKey, AttachOperationPosterRequest $request): JsonResponse
  {
    $event = $this->uploadService->resolveEvent($request->eventCode());
    $operation = $this->uploadService->attachPoster(
      operationKey: $operationKey,
      fileKey: $fileKey,
      posterFile: $request->file('poster'),
      event: $event,
      deviceId: $request->deviceId() ?: null,
      actorKey: $request->actorKey() ?: null,
      isHost: $this->isHost($request),
    );

    return $this->sendResponse(
      (new UploadOperationResource($operation))->resolve($request),
      'Portada actualizada.'
    );
  }

  public function updateFileMeta(string $operationKey, string $fileKey, Request $request): JsonResponse
  {
    $validated = $request->validate([
      'originalName' => ['nullable', 'string', 'min:1', 'max:255'],
      'mimeType' => ['nullable', 'string', 'max:120'],
      'byteSize' => ['required', 'integer', 'min:1'],
      'chunkSize' => ['required', 'integer', 'min:1024', 'max:33554432'],
      'totalChunks' => ['required', 'integer', 'min:1', 'max:100000'],
    ]);

    $event = $this->uploadService->resolveEvent($request->eventCode());
    $operation = $this->uploadService->updateFileMetadata(
      operationKey: $operationKey,
      fileKey: $fileKey,
      meta: $validated,
      event: $event,
      deviceId: $request->deviceId() ?: null,
      actorKey: $request->actorKey() ?: null,
      isHost: $this->isHost($request),
    );

    return $this->sendResponse(
      (new UploadOperationResource($operation))->resolve($request),
      'Metadatos de archivo actualizados.'
    );
  }

  public function failFile(string $operationKey, string $fileKey, FailOperationFileRequest $request): JsonResponse
  {
    $event = $this->uploadService->resolveEvent($request->eventCode());
    $operation = $this->uploadService->markFileFailed(
      operationKey: $operationKey,
      fileKey: $fileKey,
      errorCode: (string) ($request->input('errorCode') ?: 'CLIENT_UPLOAD_FAILED'),
      errorMessage: (string) $request->input('errorMessage'),
      event: $event,
      deviceId: $request->deviceId() ?: null,
      actorKey: $request->actorKey() ?: null,
      isHost: $this->isHost($request),
    );

    return $this->sendResponse(
      (new UploadOperationResource($operation))->resolve($request),
      'Fallo de archivo registrado.'
    );
  }

  public function destroy(string $operationKey, Request $request): JsonResponse
  {
    $event = $this->uploadService->resolveEvent($request->eventCode());
    $this->uploadService->dismissOperation(
      operationKey: $operationKey,
      event: $event,
      deviceId: $request->deviceId() ?: null,
      actorKey: $request->actorKey() ?: null,
      isHost: $this->isHost($request),
    );

    return $this->sendResponse(null, 'Operación ocultada.');
  }

  private function isHost(Request $request): bool
  {
    $pin = $request->hostPin();
    return $pin !== '' && User::verifyHostPin($pin);
  }
}
