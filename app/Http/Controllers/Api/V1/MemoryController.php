<?php

namespace App\Http\Controllers\Api\V1;

use App\DTOs\Shared\ViewerContextData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ListMemoriesRequest;
use App\Http\Requests\Api\V1\StoreMemoryRequest;
use App\Http\Requests\Api\V1\UpdateMemoryCaptionRequest;
use App\Http\Resources\Api\V1\MemoryCollection;
use App\Http\Resources\Api\V1\MemoryResource;
use App\Services\MemoryService;
use App\Traits\Api\ApiResponser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
      ViewerContextData::fromRequest($request)
    );

    return new MemoryCollection($paginator);
  }

  public function show(string $externalId, Request $request): JsonResponse
  {
    $memory = $this->service->getMemory($externalId, ViewerContextData::fromRequest($request));
    return $this->sendResponse(
      new MemoryResource($memory),
      'Recuerdo obtenido.',
      200
    );
  }

  public function store(StoreMemoryRequest $request): JsonResponse
  {
    $dto = $request->toDto();
    $memory = $this->service->publishMemory($dto);

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
      ViewerContextData::fromRequest($request)
    );

    return $this->sendResponse(
      new MemoryResource($memory),
      'Descripción actualizada exitosamente.',
      200
    );
  }

  public function destroy(string $externalId, Request $request): JsonResponse
  {
    $result = $this->service->deleteMemory($externalId, ViewerContextData::fromRequest($request));
    return $this->sendResponse($result, 'Recuerdo eliminado correctamente.', 200);
  }

  public function destroyMediaItem(string $externalId, string $mediaItemId, Request $request): JsonResponse
  {
    $result = $this->service->deleteMediaItem(
      $externalId,
      $mediaItemId,
      ViewerContextData::fromRequest($request)
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

  public function incrementShare(string $externalId): JsonResponse
  {
    $memory = $this->service->incrementShare($externalId);

    return $this->sendResponse([
      'id' => $memory->external_id,
      'externalId' => $memory->external_id,
      'shares' => $memory->shares_count,
      'sharesCount' => $memory->shares_count,
    ], 'Contador de compartidos incrementado.', 200);
  }
}
