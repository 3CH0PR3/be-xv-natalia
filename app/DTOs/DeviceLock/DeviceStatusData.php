<?php

namespace App\DTOs\DeviceLock;

use Illuminate\Http\Request;

final readonly class DeviceStatusData
{
  public function __construct(
    public string $eventCode,
    public string $deviceId,
  ) {}

  public static function fromRequest(Request $request, string $deviceId): self
  {
    $eventCode = (string) ($request->header('X-Event-Code') ?: $request->input('eventCode', config('xv.default_event_code', 'XV-NATALIA')));

    return new self(
      eventCode: trim($eventCode),
      deviceId: trim($deviceId),
    );
  }
}
