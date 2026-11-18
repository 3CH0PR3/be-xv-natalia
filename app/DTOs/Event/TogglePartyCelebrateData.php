<?php

namespace App\DTOs\Event;

final readonly class TogglePartyCelebrateData
{
  public function __construct(
    public string $eventCode,
    public string $actorKey,
    public ?string $deviceId,
  ) {}

  public static function fromArray(array $data): self
  {
    return new self(
      eventCode: $data['eventCode'] ?? config('xv.default_event_code', 'XV-NATALIA'),
      actorKey: $data['actorKey'] ?? '',
      deviceId: $data['deviceId'] ?? null,
    );
  }

  public function toArray(): array
  {
    return [
      'eventCode' => $this->eventCode,
      'actorKey' => $this->actorKey,
      'deviceId' => $this->deviceId,
    ];
  }
}
