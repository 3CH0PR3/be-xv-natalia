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
      eventCode: trim((string) ($request->query('event_code')
        ?: $request->input('eventCode')
        ?: config('xv.default_event_code', 'XV-NATALIA'))),
      actorKey: trim((string) $request->header('X-Actor-Key', $request->query('actor_key', ''))),
      deviceId: trim((string) $request->header('X-Device-Id', $request->query('device_id', ''))),
      hostPin: trim((string) ($request->header('X-Host-Pin') ?: $request->input('hostPin') ?: $request->query('host_pin', ''))),
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
