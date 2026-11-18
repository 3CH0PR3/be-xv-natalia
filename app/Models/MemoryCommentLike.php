<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MemoryCommentLike extends Model
{
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
