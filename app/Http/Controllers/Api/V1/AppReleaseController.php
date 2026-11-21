<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ListAppReleasesRequest;
use App\Http\Requests\Api\V1\SyncAppReleasesRequest;
use App\Http\Resources\Api\V1\AppReleaseResource;
use App\Services\AppReleaseService;
use App\Traits\Api\ApiResponser;
use Illuminate\Http\JsonResponse;

class AppReleaseController extends Controller
{
  use ApiResponser;

  public function __construct(
    private readonly AppReleaseService $service,
  ) {}

  public function index(ListAppReleasesRequest $request): JsonResponse
  {
    return $this->sendResponse(
      AppReleaseResource::collection($this->service->listReleases()),
      'Releases listados.',
      200
    );
  }

  public function sync(SyncAppReleasesRequest $request): JsonResponse
  {
    $dto = $request->toDto();
    $releases = $this->service->syncReleases($dto, $dto->eventCode);
    return $this->sendResponse(
      AppReleaseResource::collection($releases),
      'Releases sincronizados exitosamente.',
      200
    );
  }
}
