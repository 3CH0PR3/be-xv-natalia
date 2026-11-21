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
    return new self(
      eventCode: $request->eventCode(),
      deviceId: trim($deviceId),
    );
  }
}
