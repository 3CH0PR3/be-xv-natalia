<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\GetProfileReactionsRequest;
use App\Http\Requests\Api\V1\GetProfileRequest;
use App\Http\Requests\Api\V1\ListProfilesRequest;
use App\Http\Requests\Api\V1\UpdateProfileRequest;
use App\Http\Resources\Api\V1\ProfileResource;
use App\Services\ProfileService;
use App\Traits\Api\ApiResponser;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ProfileController extends Controller
{
  use ApiResponser;

  public function __construct(
    private readonly ProfileService $service
  ) {}

  public function index(ListProfilesRequest $request): JsonResponse
  {
    return $this->sendResponse(
      $this->service->listProfiles($request->toDto()),
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

  public function myReactions(GetProfileReactionsRequest $request): JsonResponse
  {
    return $this->sendResponse(
      $this->service->getReactions($request->toDto()),
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

  public function reactions(GetProfileReactionsRequest $request, string $id): JsonResponse
  {
    return $this->sendResponse(
      $this->service->getReactions($request->toDto($id)),
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
