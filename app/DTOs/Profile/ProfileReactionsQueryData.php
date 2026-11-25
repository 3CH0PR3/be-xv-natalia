<?php

namespace App\DTOs\Profile;

use App\DTOs\Shared\HasSafeArray;

final readonly class ProfileReactionsQueryData
{
  use HasSafeArray;

  public function __construct(
    public ?string $targetIdentifier = null,
    public ?string $viewerDeviceId = null,
    public ?string $viewerActorKey = null,
    public ?string $eventCode = null,
    public int $page = 1,
    public int $perPage = 7,
  ) {}

  public static function fromArray(array $data): self
  {
    return new self(
      targetIdentifier: self::get($data, 'targetIdentifier'),
      viewerDeviceId: self::get($data, 'viewerDeviceId'),
      viewerActorKey: self::get($data, 'viewerActorKey'),
      eventCode: self::get($data, 'eventCode'),
      page: max(1, (int) (self::get($data, 'page') ?: 1)),
      perPage: min(30, max(1, (int) (self::get($data, 'perPage') ?: 7))),
    );
  }
}
