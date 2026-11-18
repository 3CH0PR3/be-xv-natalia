<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ExpelGuestRequest;
use App\Http\Resources\Api\V1\EventTableResource;
use App\Services\ModerationService;
use App\Traits\Api\ApiResponser;
use Illuminate\Http\JsonResponse;

class TableGuestController extends Controller
{
    use ApiResponser;

    public function __construct(
        private readonly ModerationService $service,
    ) {}

    public function expel(ExpelGuestRequest $request): JsonResponse
    {
        $tables = $this->service->expelGuest($request->toDto());
        return $this->sendResponse(
            EventTableResource::collection($tables),
            'Invitado expulsado exitosamente.',
            200
        );
    }
}
