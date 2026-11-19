<?php

namespace App\Actions\Moderation;

use App\Exceptions\DomainRuleException;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class ChangeHostPinAction
{
  public function execute(string $currentPin, string $newPin): bool
  {
    $isValid = User::verifyHostPin($currentPin);
    if (!$isValid) {
      throw new DomainRuleException(
        'INVALID_CURRENT_PIN',
        'El PIN actual ingresado no es correcto.',
        403
      );
    }

    $cleanNew = trim($newPin);
    if (strlen($cleanNew) < 4) {
      throw new DomainRuleException(
        'PIN_TOO_SHORT',
        'El nuevo PIN debe tener al menos 4 caracteres.',
        422
      );
    }

    $hostEmail = config('xv.host_email', 'anfitriona@xv-natalia-isabela.com');
    $user = User::firstOrCreate(
      ['email' => $hostEmail],
      ['name' => 'Anfitriona XV', 'password' => Hash::make($cleanNew)]
    );

    $user->password = Hash::make($cleanNew);
    $user->save();

    return true;
  }
}
