<?php

namespace App\DTOs\DeviceLock;

final readonly class ResolveDeviceLockData
{
  /** @param  array<int, string>  $candidateFingerprints */
  public function __construct(
    public string $eventCode,
    public string $deviceId,
    public ?string $hwFingerprint,
    public array $candidateFingerprints,
    public ?array $hardwareSummary,
  ) {}

  public static function fromArray(array $data): self
  {
    return new self(
      eventCode: $data['eventCode'] ?? config('xv.default_event_code', 'XV-NATALIA'),
      deviceId: $data['deviceId'] ?? '',
      hwFingerprint: $data['hwFingerprint'] ?? null,
      candidateFingerprints: $data['candidateFingerprints'] ?? [],
      hardwareSummary: $data['hardwareSummary'] ?? null,
    );
  }

  public function toArray(): array
  {
    return [
      'eventCode' => $this->eventCode,
      'deviceId' => $this->deviceId,
      'hwFingerprint' => $this->hwFingerprint,
      'candidateFingerprints' => $this->candidateFingerprints,
      'hardwareSummary' => $this->hardwareSummary,
    ];
  }
}
