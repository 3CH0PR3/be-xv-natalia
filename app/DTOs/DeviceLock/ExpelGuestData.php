<?php

namespace App\DTOs\DeviceLock;

final readonly class ExpelGuestData
{
  public function __construct(
    public string $eventCode,
    public string $tableId,
    public ?string $deviceId,
    public ?string $hwFingerprint,
  ) {}

  public static function fromArray(array $data): self
  {
    return new self(
      eventCode: $data['eventCode'] ?? config('xv.default_event_code', 'XV-NATALIA'),
      tableId: $data['tableId'] ?? '',
      deviceId: $data['deviceId'] ?? null,
      hwFingerprint: $data['hwFingerprint'] ?? null,
    );
  }

  public function toArray(): array
  {
    return [
      'eventCode' => $this->eventCode,
      'tableId' => $this->tableId,
      'deviceId' => $this->deviceId,
      'hwFingerprint' => $this->hwFingerprint,
    ];
  }
}
