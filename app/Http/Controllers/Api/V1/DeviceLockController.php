<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\CheckinGuestRequest;
use App\Http\Requests\Api\V1\DeviceStatusRequest;
use App\Http\Requests\Api\V1\ExpelGuestRequest;
use App\Http\Requests\Api\V1\ResolveDeviceLockRequest;
use App\Http\Resources\Api\V1\DeviceLockResource;
use App\Http\Resources\Api\V1\DeviceStatusResource;
use App\Http\Resources\Api\V1\EventTableResource;
use App\Services\DeviceLockService;
use App\Traits\Api\ApiResponser;
use Illuminate\Http\JsonResponse;

class DeviceLockController extends Controller
{
  use ApiResponser;

  public function __construct(
    private readonly DeviceLockService $service,
  ) {}

  public function resolve(ResolveDeviceLockRequest $request): JsonResponse
  {
    $resolved = $this->service->resolveLock($request->toDto());

    if (!$resolved['lock']) {
      return $this->sendResponse([
        'lock' => null,
        'confirmedMissingOnServer' => true,
      ], 'No se encontró bloqueo de dispositivo existente.', 200);
    }

    return $this->sendResponse([
      'lock' => new DeviceLockResource($resolved['lock'], $resolved['restoredByFingerprint']),
      'restoredByFingerprint' => $resolved['restoredByFingerprint'],
      'confirmedMissingOnServer' => false,
    ], 'Bloqueo de dispositivo resuelto.', 200);
  }

  public function status(string $deviceId, DeviceStatusRequest $request): JsonResponse
  {
    $lock = $this->service->checkDeviceStatus($request->toDto($deviceId));

    return $this->sendResponse(
      new DeviceStatusResource($lock),
      'Estado de dispositivo consultado.',
      200
    );
  }

  public function checkin(CheckinGuestRequest $request): JsonResponse
  {
    $lock = $this->service->checkin($request->toDto());
    return $this->sendResponse(new DeviceLockResource($lock), 'Check-in completado exitosamente.', 201);
  }

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
