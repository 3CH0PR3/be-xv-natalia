<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ToggleMemoryReactionRequest;
use App\Services\MemoryService;
use App\Traits\Api\ApiResponser;
use Illuminate\Http\JsonResponse;

class MemoryReactionController extends Controller
{
  use ApiResponser;

  public function __construct(
    private readonly MemoryService $service,
  ) {}

  public function toggle(string $externalId, ToggleMemoryReactionRequest $request): JsonResponse
  {
    $result = $this->service->toggleReaction($request->toDto($externalId));
    return $this->sendResponse($result, 'Reacción actualizada.', 200);
  }

  public function share(string $externalId): JsonResponse
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
