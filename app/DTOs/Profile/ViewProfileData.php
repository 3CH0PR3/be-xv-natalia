<?php

namespace App\DTOs\Profile;

use App\DTOs\Shared\HasSafeArray;

final readonly class ViewProfileData
{
  use HasSafeArray;

  public function __construct(
    public ?string $targetIdentifier = null,
    public ?string $viewerDeviceId = null,
    public ?string $viewerActorKey = null,
    public ?string $eventCode = null,
  ) {}

  public static function fromArray(array $data): self
  {
    return new self(
      targetIdentifier: self::get($data, 'targetIdentifier'),
      viewerDeviceId: self::get($data, 'viewerDeviceId'),
      viewerActorKey: self::get($data, 'viewerActorKey'),
      eventCode: self::get($data, 'eventCode'),
    );
  }
}
