<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\DeleteMediaItemRequest;
use App\Http\Requests\Api\V1\DeleteMemoryRequest;
use App\Http\Requests\Api\V1\GetMemoryPreviewRequest;
use App\Http\Requests\Api\V1\GetMemoryRequest;
use App\Http\Requests\Api\V1\IncrementMemoryShareRequest;
use App\Http\Requests\Api\V1\ListMemoriesRequest;
use App\Http\Requests\Api\V1\StoreMemoryRequest;
use App\Http\Requests\Api\V1\UpdateMemoryCaptionRequest;
use App\Http\Resources\Api\V1\MemoryCollection;
use App\Http\Resources\Api\V1\MemoryPreviewResource;
use App\Http\Resources\Api\V1\MemoryResource;
use App\Http\Resources\Api\V1\MemoryShareResource;
use App\Services\MemoryService;
use App\Traits\Api\ApiResponser;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

class MemoryController extends Controller
{
  use ApiResponser;

  public function __construct(
    private readonly MemoryService $service,
  ) {}

  public function index(ListMemoriesRequest $request): MemoryCollection
  {
    $paginator = $this->service->listMemories(
      $request->toDto(),
      $request->viewerContext()
    );

    return new MemoryCollection($paginator);
  }

  public function show(string $externalId, GetMemoryRequest $request): JsonResponse
  {
    $memory = $this->service->getMemory($externalId, $request->toDto());
    return $this->sendResponse(
      new MemoryResource($memory),
      'Recuerdo obtenido.',
      200
    );
  }

  public function preview(string $externalId, GetMemoryPreviewRequest $request): JsonResponse
  {
    $preview = $this->service->getMemoryPreview($externalId);

    return (new MemoryPreviewResource($preview))
      ->response()
      ->header('Cache-Control', 'public, max-age=300, stale-while-revalidate=600');
  }

  public function coverImage(string $externalId): BinaryFileResponse
  {
    return $this->service->getMemoryCoverImage($externalId);
  }

  public function store(StoreMemoryRequest $request): JsonResponse
  {
    $memory = $this->service->publishMemory($request->toDto());

    return $this->sendResponse(
      new MemoryResource($memory),
      'Recuerdo publicado exitosamente.',
      Response::HTTP_CREATED
    );
  }

  public function updateCaption(string $externalId, UpdateMemoryCaptionRequest $request): JsonResponse
  {
    $memory = $this->service->updateCaption(
      $request->toDto($externalId),
      $request->viewerContext()
    );

    return $this->sendResponse(
      new MemoryResource($memory),
      'Descripción actualizada exitosamente.',
      200
    );
  }

  public function destroy(string $externalId, DeleteMemoryRequest $request): JsonResponse
  {
    $result = $this->service->deleteMemory($externalId, $request->toDto());
    return $this->sendResponse($result, 'Recuerdo eliminado correctamente.', 200);
  }

  public function destroyMediaItem(string $externalId, string $mediaItemId, DeleteMediaItemRequest $request): JsonResponse
  {
    $result = $this->service->deleteMediaItem(
      $externalId,
      $mediaItemId,
      $request->toDto()
    );

    if ($result['deletedParent']) {
      return $this->sendResponse(['deletedParent' => true], 'Elemento y recuerdo vaciado eliminados.', 200);
    }

    return $this->sendResponse(
      new MemoryResource($result['memory']),
      'Elemento multimedia eliminado.',
      200
    );
  }

  public function incrementShare(string $externalId, IncrementMemoryShareRequest $request): JsonResponse
  {
    $memory = $this->service->incrementShare($request->toDto($externalId));

    return $this->sendResponse(
      new MemoryShareResource($memory),
      'Contador de compartidos incrementado.',
      200
    );
  }
}
