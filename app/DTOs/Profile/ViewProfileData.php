<?php

namespace App\DTOs\Profile;

final readonly class ViewProfileData
{
  public function __construct(
    public ?string $targetIdentifier = null,
    public ?string $viewerDeviceId = null,
    public ?string $viewerActorKey = null,
    public ?string $eventCode = null,
  ) {}
}
