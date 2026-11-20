<?php

namespace App\Http\Controllers\Api\V1;

use App\DTOs\Shared\ViewerContextData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\GenerateTableQrsRequest;
use App\Http\Requests\Api\V1\ValidateQrRequest;
use App\Http\Resources\Api\V1\EventTableResource;
use App\Http\Resources\Api\V1\TableValidationResource;
use App\Services\TableService;
use App\Traits\Api\ApiResponser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TableController extends Controller
{
  use ApiResponser;

  public function __construct(
    private readonly TableService $service,
  ) {}

  public function index(Request $request): JsonResponse
  {
    $viewer = ViewerContextData::fromRequest($request);
    $tables = $this->service->listTables($viewer->eventCode);

    return $this->sendResponse(
      EventTableResource::collection($tables),
      'Listado de mesas.',
      200
    );
  }

  public function generateQrs(GenerateTableQrsRequest $request): JsonResponse
  {
    $tables = $this->service->generateQrs($request->toDto());

    return $this->sendResponse(
      EventTableResource::collection($tables),
      'QRs dinámicos generados exitosamente.',
      200
    );
  }

  public function validateQr(ValidateQrRequest $request): JsonResponse
  {
    $result = $this->service->validateQr($request->toDto());

    if (!$result['isValid']) {
      return $this->sendError($result['message'], [
        'isValid' => false,
        'code' => $result['code'],
      ], 422);
    }

    return $this->sendResponse(
      new TableValidationResource($result),
      $result['message'],
      200
    );
  }
}
