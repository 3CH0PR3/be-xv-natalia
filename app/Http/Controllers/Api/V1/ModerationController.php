<?php

namespace App\Http\Controllers\Api\V1;

use App\DTOs\Shared\ViewerContextData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ExpelGuestRequest;
use App\Http\Requests\Api\V1\UpdateMemoryStatusRequest;
use App\Http\Requests\Api\V1\VerifyHostPinRequest;
use App\Http\Resources\Api\V1\EventTableResource;
use App\Http\Resources\Api\V1\MemoryResource;
use App\Http\Resources\Api\V1\ModerationMetricsResource;
use App\Services\ModerationService;
use App\Traits\Api\ApiResponser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

class ModerationController extends Controller
{
  use ApiResponser;

  public function __construct(
    private readonly ModerationService $service,
  ) {}

  public function verifyPin(VerifyHostPinRequest $request): JsonResponse
  {
    $pin = (string) ($request->input('pin') ?: ($request->input('hostPin') ?: $request->input('host_pin', '')));
    $valid = $this->service->verifyHostPin($pin);

    if (!$valid) {
      return $this->sendError('PIN de anfitriona incorrecto.', [], Response::HTTP_FORBIDDEN);
    }

    return $this->sendResponse(['valid' => true], 'PIN verificado correctamente.', 200);
  }

  public function metrics(Request $request): JsonResponse
  {
    $viewer = ViewerContextData::fromRequest($request);
    return $this->sendResponse(
      new ModerationMetricsResource($this->service->getMetrics($viewer->eventCode)),
      'Métricas de moderación.',
      200
    );
  }

  public function updateStatus(string $externalId, UpdateMemoryStatusRequest $request): JsonResponse
  {
    $memory = $this->service->updateMemoryStatus($externalId, $request->input('status'));
    return $this->sendResponse(
      new MemoryResource($memory),
      'Estado de recuerdo actualizado.',
      200
    );
  }

  public function expelGuest(ExpelGuestRequest $request): JsonResponse
  {
    $tables = $this->service->expelGuest($request->toDto());
    return $this->sendResponse(
      EventTableResource::collection($tables),
      'Invitado expulsado exitosamente.',
      200
    );
  }

  public function exportZip(Request $request): BinaryFileResponse
  {
    $viewer = ViewerContextData::fromRequest($request);
    $archive = $this->service->buildAlbumZip($viewer->eventCode);

    return response()->download($archive['path'], $archive['filename'])->deleteFileAfterSend(true);
  }

  public function purgeEvent(Request $request): JsonResponse
  {
    $viewer = ViewerContextData::fromRequest($request);
    $result = $this->service->purgeEventData($viewer->eventCode);

    return $this->sendResponse($result, 'Datos del evento purgados exitosamente.', 200);
  }
}
