<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Table\GenerateTableQrsAction;
use App\DTOs\Shared\ViewerContextData;
use App\Http\Controllers\Controller;
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

        $tables = $action->execute($event, $baseUrl);
        return $this->sendResponse(
            EventTableResource::collection($tables),
            'QRs generados exitosamente.',
            200
        );
    }
}
