<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\IncrementMemoryShareRequest;
use App\Http\Requests\Api\V1\ToggleMemoryReactionRequest;
use App\Http\Resources\Api\V1\MemoryReactionResource;
use App\Http\Resources\Api\V1\MemoryShareResource;
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
    return $this->sendResponse(
      new MemoryReactionResource($result),
      'Reacción actualizada.',
      200
    );
  }

  public function share(string $externalId, IncrementMemoryShareRequest $request): JsonResponse
  {
    $memory = $this->service->incrementShare($request->toDto($externalId));

    return $this->sendResponse(
      new MemoryShareResource($memory),
      'Contador de compartidos incrementado.',
      200
    );
  }
}
