<?php

namespace App\DTOs\AppRelease;

final readonly class SyncAppReleasesData
{
  /** @param  array<int, array<string, mixed>>  $releases */
  public function __construct(
    public ?string $buildId,
    public int $builtAtMs = 0,
    public array $releases = [],
    public string $eventCode = 'XV-NATALIA',
  ) {}

  public static function fromArray(array $data): self
  {
    return new self(
      buildId: $data['buildId'] ?? null,
      builtAtMs: (int) ($data['builtAtMs'] ?? round(microtime(true) * 1000)),
      releases: $data['releases'] ?? [],
      eventCode: $data['eventCode'] ?? config('xv.default_event_code', 'XV-NATALIA'),
    );
  }

  public function toArray(): array
  {
    return [
      'buildId' => $this->buildId,
      'builtAtMs' => $this->builtAtMs,
      'releases' => $this->releases,
      'eventCode' => $this->eventCode,
    ];
  }
}
