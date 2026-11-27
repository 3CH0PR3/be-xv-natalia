<?php

namespace App\Services;

use App\Actions\Profile\GetProfileAction;
use App\Actions\Profile\GetProfileMediaAction;
use App\Actions\Profile\GetProfileReactionsAction;
use App\Actions\Profile\ListProfilesAction;
use App\Actions\Profile\UpdateProfileAction;
use App\DTOs\Profile\ListProfilesData;
use App\DTOs\Profile\ProfileReactionsQueryData;
use App\DTOs\Profile\UpdateProfileData;
use App\DTOs\Profile\ViewProfileData;
use App\Http\Resources\Api\V1\ProfileListItemResource;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

final readonly class ProfileService
{
  public function __construct(
    private ListProfilesAction $listProfiles,
    private GetProfileAction $getProfile,
    private UpdateProfileAction $updateProfile,
    private GetProfileMediaAction $getProfileMedia,
    private GetProfileReactionsAction $getProfileReactions,
  ) {}

  public function listProfiles(ListProfilesData $data): array
  {
    $paginator = $this->listProfiles->execute($data);

    return [
      'data' => ProfileListItemResource::collection($paginator->items())->resolve(),
      'pagination' => [
        'currentPage' => $paginator->currentPage(),
        'perPage' => $paginator->perPage(),
        'total' => $paginator->total(),
        'hasMore' => $paginator->hasMorePages(),
      ],
    ];
  }

  public function getAvatar(string $id): BinaryFileResponse|StreamedResponse
  {
    return $this->getProfileMedia->execute($id, 'avatar');
  }

  public function getCover(string $id): BinaryFileResponse|StreamedResponse
  {
    return $this->getProfileMedia->execute($id, 'cover');
  }

  public function getProfile(ViewProfileData $data): array
  {
    return $this->getProfile->execute($data);
  }

  public function updateProfile(UpdateProfileData $data): array
  {
    return $this->updateProfile->execute($data);
  }

  public function getReactions(ProfileReactionsQueryData $data): array
  {
    return $this->getProfileReactions->execute(
      targetIdentifier: $data->targetIdentifier,
      viewerDeviceId: $data->viewerDeviceId,
      viewerActorKey: $data->viewerActorKey,
      eventCode: $data->eventCode,
      page: $data->page,
      perPage: $data->perPage,
    );
  }
}
