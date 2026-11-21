<?php

namespace App\DTOs\Shared;

use Illuminate\Http\Request;

final readonly class ViewerContextData
{
  public function __construct(
    public string $eventCode,
    public string $actorKey,
    public string $deviceId,
    public string $hostPin,
  ) {}

  public static function fromRequest(Request $request): self
  {
    return new self(
      eventCode: $request->eventCode(),
      actorKey: $request->actorKey(),
      deviceId: $request->deviceId(),
      hostPin: $request->hostPin(),
    );
  }

  public static function fromArray(array $data): self
  {
    return new self(
      eventCode: $data['eventCode'] ?? config('xv.default_event_code', 'XV-NATALIA'),
      actorKey: $data['actorKey'] ?? '',
      deviceId: $data['deviceId'] ?? '',
      hostPin: $data['hostPin'] ?? '',
    );
  }

  public function toArray(): array
  {
    return [
      'eventCode' => $this->eventCode,
      'actorKey' => $this->actorKey,
      'deviceId' => $this->deviceId,
      'hostPin' => $this->hostPin,
    ];
  }

  public function isHost(): bool
  {
    $configured = (string) config('xv.host_pin');
    return $configured !== '' && $this->hostPin !== '' && hash_equals($configured, $this->hostPin);
  }
}
