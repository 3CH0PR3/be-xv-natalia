<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ChangeHostPinRequest;
use App\Http\Requests\Api\V1\ExpelGuestRequest;
use App\Http\Requests\Api\V1\ExportAlbumZipRequest;
use App\Http\Requests\Api\V1\GetModerationMetricsRequest;
use App\Http\Requests\Api\V1\PurgeEventRequest;
use App\Http\Requests\Api\V1\UpdateMemoryStatusRequest;
use App\Http\Requests\Api\V1\VerifyHostPinRequest;
use App\Http\Resources\Api\V1\EventTableResource;
use App\Http\Resources\Api\V1\MemoryResource;
use App\Http\Resources\Api\V1\ModerationMetricsResource;
use App\Services\ModerationService;
use App\Traits\Api\ApiResponser;
use Illuminate\Http\JsonResponse;
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
    $valid = $this->service->verifyHostPin($request->toDto());

    if (!$valid) {
      return $this->sendError('PIN de anfitriona incorrecto.', [], Response::HTTP_FORBIDDEN);
    }

    return $this->sendResponse(['valid' => true], 'PIN verificado correctamente.', 200);
  }

  public function metrics(GetModerationMetricsRequest $request): JsonResponse
  {
    $viewer = $request->toDto();
    return $this->sendResponse(
      new ModerationMetricsResource($this->service->getMetrics($viewer->eventCode)),
      'Métricas de moderación.',
      200
    );
  }

  public function updateStatus(string $externalId, UpdateMemoryStatusRequest $request): JsonResponse
  {
    $memory = $this->service->updateMemoryStatus($request->toDto($externalId));
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

  public function exportZip(ExportAlbumZipRequest $request): BinaryFileResponse
  {
    $viewer = $request->toDto();
    $archive = $this->service->buildAlbumZip($viewer->eventCode);

    return response()->download($archive['path'], $archive['filename'])->deleteFileAfterSend(true);
  }

  public function purgeEvent(PurgeEventRequest $request): JsonResponse
  {
    $viewer = $request->toDto();
    $result = $this->service->purgeEventData($viewer->eventCode);

    return $this->sendResponse($result, 'Datos del evento purgados exitosamente.', 200);
  }

  public function changePin(ChangeHostPinRequest $request): JsonResponse
  {
    $this->service->changePin($request->toDto());

    return $this->sendResponse(['success' => true], 'PIN de anfitriona actualizado exitosamente.', 200);
  }
}
