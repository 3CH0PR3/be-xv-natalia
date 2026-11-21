<?php

namespace App\Actions\Moderation;

use App\DTOs\Moderation\VerifyHostPinData;
use App\Models\User;

class VerifyHostPinAction
{
  public function execute(VerifyHostPinData|string $pin): bool
  {
    $pinValue = $pin instanceof VerifyHostPinData ? $pin->pin : $pin;
    return User::verifyHostPin($pinValue);
  }
}
