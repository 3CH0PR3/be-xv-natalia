<?php

namespace App\DTOs\Profile;

use Illuminate\Http\UploadedFile;

final readonly class UpdateProfileData
{
  public function __construct(
    public ?string $guestName,
    public ?string $bio,
    public ?string $location,
    public ?UploadedFile $avatar = null,
    public ?UploadedFile $cover = null,
    public ?string $ownerDeviceId = null,
    public ?string $ownerActorKey = null,
  ) {}

  public static function fromArray(array $data): self
  {
    return new self(
      guestName: isset($data['guestName']) ? trim((string) $data['guestName']) : null,
      bio: isset($data['bio']) ? trim((string) $data['bio']) : null,
      location: isset($data['location']) ? trim((string) $data['location']) : null,
      avatar: $data['avatar'] ?? null,
      cover: $data['cover'] ?? null,
      ownerDeviceId: $data['ownerDeviceId'] ?? null,
      ownerActorKey: $data['ownerActorKey'] ?? null,
    );
  }
}
