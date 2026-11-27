<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasVersion7Uuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PartyReaction extends Model
{
  use HasVersion7Uuids;
  protected $fillable = [
    'event_id',
    'actor_key',
    'device_id',
  ];

  public function event(): BelongsTo
  {
    return $this->belongsTo(Event::class);
  }
}
