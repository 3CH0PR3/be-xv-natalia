<?php

namespace App\Services;

use App\Actions\Profile\GetProfileAction;
use App\Actions\Profile\UpdateProfileAction;
use App\DTOs\Profile\UpdateProfileData;
use App\DTOs\Profile\ViewProfileData;

class ProfileService
{
  public function __construct(
    private readonly GetProfileAction $getProfile,
    private readonly UpdateProfileAction $updateProfile
  ) {}

  public function getProfile(
    ViewProfileData|string|null $targetOrDto = null,
    ?string $viewerDeviceId = null,
    ?string $viewerActorKey = null,
    ?string $eventCode = null
  ): array {
    if ($targetOrDto instanceof ViewProfileData) {
      return $this->getProfile->execute(
        $targetOrDto->targetIdentifier,
        $targetOrDto->viewerDeviceId,
        $targetOrDto->viewerActorKey,
        $targetOrDto->eventCode
      );
    }

    return $this->getProfile->execute($targetOrDto, $viewerDeviceId, $viewerActorKey, $eventCode);
  }

  public function updateProfile(UpdateProfileData $data): array
  {
    return $this->updateProfile->execute($data);
  }
}
