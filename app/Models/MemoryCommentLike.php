<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasVersion7Uuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MemoryCommentLike extends Model
{
  use HasVersion7Uuids;
  protected $fillable = [
    'comment_id',
    'actor_key',
    'device_id',
  ];

  public function comment(): BelongsTo
  {
    return $this->belongsTo(MemoryComment::class, 'comment_id');
  }
}
