<?php

namespace App\Actions\Moderation;

use App\Models\User;

class VerifyHostPinAction
{
  public function execute(string $pin): bool
  {
    return User::verifyHostPin($pin);
  }
}
