<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ToggleMemoryReactionRequest;
use App\Services\MemoryService;
use App\Traits\Api\ApiResponser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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

  public function share(string $externalId, Request $request): JsonResponse
  {
    $actorKey = trim((string) ($request->header('X-Actor-Key') ?: $request->input('actorKey', '')));
    $deviceId = trim((string) ($request->header('X-Device-Id') ?: $request->input('deviceId', '')));
    $deviceLockId = $request->input('deviceLockId') ? (int) $request->input('deviceLockId') : null;

    $memory = $this->service->incrementShare($externalId, $actorKey, $deviceId, $deviceLockId);
    return $this->sendResponse([
      'id' => $memory->external_id,
      'externalId' => $memory->external_id,
      'shares' => $memory->shares_count,
      'sharesCount' => $memory->shares_count,
    ], 'Contador de compartidos incrementado.', 200);
  }
}
