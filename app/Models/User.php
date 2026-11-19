<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
  use HasFactory, Notifiable;

  /**
   * The attributes that are mass assignable.
   *
   * @var array<int, string>
   */
  protected $fillable = [
    'name',
    'email',
    'password',
  ];

  /**
   * The attributes that should be hidden for serialization.
   *
   * @var array<int, string>
   */
  protected $hidden = [
    'password',
    'remember_token',
  ];

  /**
   * Get the attributes that should be cast.
   *
   * @return array<string, string>
   */
  protected function casts(): array
  {
    return [
      'email_verified_at' => 'datetime',
      'password' => 'hashed',
    ];
  }

  public static function verifyHostPin(string $pin): bool
  {
    $cleanPin = trim($pin, " \t\n\r\0\x0B\"'");
    if ($cleanPin === '') {
      return false;
    }

    $hostUser = static::where('email', config('xv.host_email', 'anfitriona@xv-natalia-isabela.com'))->first();
    if ($hostUser && \Illuminate\Support\Facades\Hash::check($cleanPin, $hostUser->password)) {
      return true;
    }

    $configuredPin = trim((string) config('xv.host_pin', '1515'), " \t\n\r\0\x0B\"'");
    return $configuredPin !== '' && hash_equals($configuredPin, $cleanPin);
  }
}
