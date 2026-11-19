<?php

namespace App\DTOs\DeviceLock;

final readonly class CheckinGuestData
{
  /** @param  array<int, string>  $candidateFingerprints */
  public function __construct(
    public string $eventCode,
    public string $tableCode,
    public string $guestName,
    public string $deviceId,
    public ?string $hwFingerprint,
    public array $candidateFingerprints,
    public ?array $hardwareSummary,
    public ?string $qrToken = null,
  ) {}

  public static function fromArray(array $data): self
  {
    return new self(
      eventCode: $data['eventCode'] ?? config('xv.default_event_code', 'XV-NATALIA'),
      tableCode: $data['tableCode'] ?? '',
      guestName: $data['guestName'] ?? '',
      deviceId: $data['deviceId'] ?? '',
      hwFingerprint: $data['hwFingerprint'] ?? null,
      candidateFingerprints: $data['candidateFingerprints'] ?? [],
      hardwareSummary: $data['hardwareSummary'] ?? null,
      qrToken: $data['qrToken'] ?? ($data['token'] ?? null),
    );
  }

  public function toArray(): array
  {
    return [
      'eventCode' => $this->eventCode,
      'tableCode' => $this->tableCode,
      'guestName' => $this->guestName,
      'deviceId' => $this->deviceId,
      'hwFingerprint' => $this->hwFingerprint,
      'candidateFingerprints' => $this->candidateFingerprints,
      'hardwareSummary' => $this->hardwareSummary,
      'qrToken' => $this->qrToken,
    ];
  }
}
