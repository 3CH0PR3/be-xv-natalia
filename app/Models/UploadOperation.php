<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UploadOperation extends Model
{
  public const STATUS_QUEUED = 'queued';
  public const STATUS_UPLOADING = 'uploading';
  public const STATUS_PROCESSING = 'processing';
  public const STATUS_COMPLETED = 'completed';
  public const STATUS_COMPLETED_WITH_ERRORS = 'completed_with_errors';
  public const STATUS_FAILED = 'failed';

  protected $fillable = [
    'operation_key',
    'event_id',
    'event_table_id',
    'device_lock_id',
    'memory_id',
    'memory_external_id',
    'owner_device_id',
    'owner_actor_key',
    'guest_name',
    'table_code',
    'moment_tag',
    'caption',
    'request_origin',
    'status',
    'total_files',
    'uploaded_files',
    'completed_files',
    'failed_files',
    'total_bytes',
    'uploaded_bytes',
    'error_summary',
    'started_at',
    'completed_at',
    'dismissed_at',
  ];

  protected function casts(): array
  {
    return [
      'total_files' => 'integer',
      'uploaded_files' => 'integer',
      'completed_files' => 'integer',
      'failed_files' => 'integer',
      'total_bytes' => 'integer',
      'uploaded_bytes' => 'integer',
      'started_at' => 'datetime',
      'completed_at' => 'datetime',
      'dismissed_at' => 'datetime',
    ];
  }

  public function event(): BelongsTo
  {
    return $this->belongsTo(Event::class);
  }

  public function table(): BelongsTo
  {
    return $this->belongsTo(EventTable::class, 'event_table_id');
  }

  public function deviceLock(): BelongsTo
  {
    return $this->belongsTo(DeviceLock::class);
  }

  public function memory(): BelongsTo
  {
    return $this->belongsTo(Memory::class);
  }

  public function files(): HasMany
  {
    return $this->hasMany(UploadOperationFile::class)->orderBy('sort_order')->orderBy('id');
  }

  public function scopeForEvent(Builder $query, int $eventId): Builder
  {
    return $query->where('event_id', $eventId);
  }

  public function scopeVisible(Builder $query): Builder
  {
    return $query->whereNull('dismissed_at');
  }

  public function scopeOwnedBy(Builder $query, ?string $deviceId, ?string $actorKey = null): Builder
  {
    return $query->where(function (Builder $q) use ($deviceId, $actorKey): void {
      if ($deviceId) {
        $q->orWhere('owner_device_id', $deviceId);
      }
      if ($actorKey) {
        $q->orWhere('owner_actor_key', $actorKey);
      }
    });
  }

  public function isOwnedBy(?string $deviceId, ?string $actorKey = null): bool
  {
    if ($deviceId && $this->owner_device_id === $deviceId) {
      return true;
    }
    if ($actorKey && $this->owner_actor_key === $actorKey) {
      return true;
    }
    return false;
  }

  public function isTerminal(): bool
  {
    return in_array($this->status, [
      self::STATUS_COMPLETED,
      self::STATUS_COMPLETED_WITH_ERRORS,
      self::STATUS_FAILED,
    ], true);
  }

  public function progressPercent(): int
  {
    if ($this->status === self::STATUS_COMPLETED) {
      return 100;
    }

    if ($this->total_bytes <= 0) {
      if ($this->total_files <= 0) {
        return 0;
      }
      return (int) min(100, round((($this->completed_files + $this->failed_files) / $this->total_files) * 100));
    }

    $uploadFraction = min(1.0, max(0.0, $this->uploaded_bytes / $this->total_bytes));
    $doneFiles = $this->completed_files + $this->failed_files;
    $processFraction = $this->total_files > 0 ? min(1.0, $doneFiles / $this->total_files) : 0.0;

    return (int) min(99, max(0, round(($uploadFraction * 80) + ($processFraction * 20))));
  }
}
