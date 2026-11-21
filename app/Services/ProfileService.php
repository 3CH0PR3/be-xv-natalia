<?php

namespace App\Services;

use App\Actions\Profile\GetProfileAction;
use App\Actions\Profile\GetProfileMediaAction;
use App\Actions\Profile\UpdateProfileAction;
use App\DTOs\Profile\UpdateProfileData;
use App\DTOs\Profile\ViewProfileData;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ProfileService
{
  public function __construct(
    private readonly GetProfileAction $getProfile,
    private readonly UpdateProfileAction $updateProfile,
    private readonly GetProfileMediaAction $getProfileMedia
  ) {}

  public function getAvatar(string $id): BinaryFileResponse
  {
    return $this->getProfileMedia->execute($id, 'avatar');
  }

  public function getCover(string $id): BinaryFileResponse
  {
    return $this->getProfileMedia->execute($id, 'cover');
  }

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
