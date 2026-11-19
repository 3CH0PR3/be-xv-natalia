<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Table\GenerateTableQrsAction;
use App\Actions\Table\ValidateQrAction;
use App\DTOs\Shared\ViewerContextData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ValidateQrRequest;
use App\Http\Resources\Api\V1\EventTableResource;
use App\Models\Event;
use App\Services\DeviceLockService;
use App\Traits\Api\ApiResponser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TableController extends Controller
{
  use ApiResponser;

  public function __construct(
    private readonly DeviceLockService $service,
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

  public function generateQrs(Request $request, GenerateTableQrsAction $action): JsonResponse
  {
    $viewer = ViewerContextData::fromRequest($request);
    $event = Event::byCode($viewer->eventCode)->firstOrFail();
    $baseUrl = $request->input('baseUrl') ?: ($request->header('origin') ?: $request->getSchemeAndHttpHost());
    $tableCount = $request->filled('tableCount') ? (int) $request->input('tableCount') : null;

    $tables = $action->execute($event, $baseUrl, $tableCount);
    return $this->sendResponse(
      EventTableResource::collection($tables),
      'QRs dinámicos generados exitosamente.',
      200
    );
  }

  public function validateQr(ValidateQrRequest $request, ValidateQrAction $action): JsonResponse
  {
    $result = $action->execute($request->toDto());

    if (!$result['isValid']) {
      return $this->sendError($result['message'], [
        'isValid' => false,
        'code' => $result['code'],
      ], 422);
    }

    return $this->sendResponse([
      'isValid' => true,
      'code' => $result['code'],
      'table' => new EventTableResource($result['table']),
    ], $result['message'], 200);
  }
}
