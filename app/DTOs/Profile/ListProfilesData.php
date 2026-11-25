<?php

namespace App\DTOs\Profile;

use App\DTOs\Shared\HasSafeArray;

final readonly class ListProfilesData
{
  use HasSafeArray;

  public function __construct(
    public ?string $search = null,
    public int $limit = 10,
    public int $page = 1,
    public ?string $eventCode = null,
    public ?string $viewerDeviceId = null,
    public ?string $viewerActorKey = null,
  ) {}

  public static function fromArray(array $data): self
  {
    $rawSearch = self::get($data, 'search');
    $cleanSearch = is_string($rawSearch) ? trim($rawSearch) : null;
    $rawLimit = (int) (self::get($data, 'limit') ?: 10);
    $rawPage = (int) (self::get($data, 'page') ?: 1);

    return new self(
      search: $cleanSearch !== '' ? $cleanSearch : null,
      limit: min(30, max(1, $rawLimit)),
      page: max(1, $rawPage),
      eventCode: self::get($data, 'eventCode'),
      viewerDeviceId: self::get($data, 'viewerDeviceId'),
      viewerActorKey: self::get($data, 'viewerActorKey'),
    );
  }
}
