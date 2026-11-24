<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\GetProfileRequest;
use App\Http\Requests\Api\V1\UpdateProfileRequest;
use App\Http\Resources\Api\V1\ProfileResource;
use App\Services\ProfileService;
use App\Traits\Api\ApiResponser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ProfileController extends Controller
{
  use ApiResponser;

  public function __construct(
    private readonly ProfileService $service
  ) {}

  public function index(Request $request): JsonResponse
  {
    $search = $request->query('search') ?: $request->query('q');
    $limit = min(30, max(1, (int) $request->query('limit', $request->query('per_page', 10))));
    $page = max(1, (int) $request->query('page', 1));
    $eventCode = $request->query('event_code') ?: config('xv.default_event_code', 'XV-NATALIA');
    $viewerDeviceId = $request->deviceId() ?: null;
    $viewerActorKey = $request->actorKey() ?: null;

    $result = $this->service->listProfiles($search, $limit, $page, $eventCode, $viewerDeviceId, $viewerActorKey);

    return $this->sendResponse(
      $result,
      'Listado de perfiles obtenido exitosamente.',
      200
    );
  }

  public function avatar(string $id): BinaryFileResponse
  {
    return $this->service->getAvatar($id);
  }

  public function cover(string $id): BinaryFileResponse
  {
    return $this->service->getCover($id);
  }

  public function myProfile(GetProfileRequest $request): JsonResponse
  {
    $profile = $this->service->getProfile($request->toDto());

    return $this->sendResponse(
      new ProfileResource($profile),
      'Perfil obtenido exitosamente.',
      200
    );
  }

  public function myReactions(GetProfileRequest $request): JsonResponse
  {
    $page = max(1, (int) $request->query('page', 1));
    $perPage = min(30, max(1, (int) $request->query('per_page', $request->query('perPage', 7))));

    $result = $this->service->getReactions(
      $request->toDto(),
      page: $page,
      perPage: $perPage
    );

    return $this->sendResponse(
      $result,
      'Reacciones del perfil obtenidas exitosamente.',
      200
    );
  }

  public function show(GetProfileRequest $request, string $id): JsonResponse
  {
    $profile = $this->service->getProfile($request->toDto($id));

    return $this->sendResponse(
      new ProfileResource($profile),
      'Perfil obtenido exitosamente.',
      200
    );
  }

  public function reactions(GetProfileRequest $request, string $id): JsonResponse
  {
    $page = max(1, (int) $request->query('page', 1));
    $perPage = min(30, max(1, (int) $request->query('per_page', $request->query('perPage', 7))));

    $result = $this->service->getReactions(
      $request->toDto($id),
      page: $page,
      perPage: $perPage
    );

    return $this->sendResponse(
      $result,
      'Reacciones del perfil obtenidas exitosamente.',
      200
    );
  }

  public function update(UpdateProfileRequest $request): JsonResponse
  {
    $profile = $this->service->updateProfile($request->toDto());

    return $this->sendResponse(
      new ProfileResource($profile),
      'Perfil actualizado exitosamente.',
      200
    );
  }
}
