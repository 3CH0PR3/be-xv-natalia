<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\CelebratePartyRequest;
use App\Http\Requests\Api\V1\GetEventStateRequest;
use App\Http\Resources\Api\V1\EventStateResource;
use App\Http\Resources\Api\V1\PartyCelebrationResource;
use App\Services\EventStateService;
use App\Traits\Api\ApiResponser;
use Illuminate\Http\JsonResponse;

class EventStateController extends Controller
{
  use ApiResponser;

  public function __construct(
    private readonly EventStateService $service,
  ) {}

  public function show(GetEventStateRequest $request): JsonResponse
  {
    $state = $this->service->getEventState($request->toDto());
    return $this->sendResponse(
      new EventStateResource($state),
      'Estado del evento consultado.',
      200
    );
  }

  public function celebrate(CelebratePartyRequest $request): JsonResponse
  {
    $result = $this->service->toggleCelebration($request->toDto());
    return $this->sendResponse(
      new PartyCelebrationResource($result),
      'Reacción de fiesta registrada.',
      200
    );
  }
}
