<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('event.{eventCode}', static fn (): bool => true);
Broadcast::channel('device.{deviceId}', static fn (): bool => true);
