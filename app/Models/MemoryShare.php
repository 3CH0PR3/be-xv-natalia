<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasVersion7Uuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MemoryShare extends Model
{
  use HasVersion7Uuids;
  protected $fillable = [
    'memory_id',
    'device_lock_id',
    'actor_key',
    'device_id',
  ];

  public function memory(): BelongsTo
  {
    return $this->belongsTo(Memory::class);
  }

  public function deviceLock(): BelongsTo
  {
    return $this->belongsTo(DeviceLock::class);
  }
}
