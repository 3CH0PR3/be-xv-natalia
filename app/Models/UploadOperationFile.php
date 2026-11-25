<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class UploadOperationFile extends Model
{
  public const STATUS_QUEUED_UPLOAD = 'queued_upload';
  public const STATUS_UPLOADING = 'uploading';
  public const STATUS_UPLOADED = 'uploaded';
  public const STATUS_PROCESSING = 'processing';
  public const STATUS_COMPLETED = 'completed';
  public const STATUS_FAILED = 'failed';

  protected $fillable = [
    'upload_operation_id',
    'file_key',
    'memory_media_item_id',
    'original_name',
    'mime_type',
    'media_type',
    'byte_size',
    'uploaded_bytes',
    'chunk_size',
    'total_chunks',
    'received_chunks',
    'sort_order',
    'status',
    'disk',
    'temp_dir',
    'poster_path',
    'final_path',
    'error_code',
    'error_message',
    'started_at',
    'uploaded_at',
    'processed_at',
  ];

  protected function casts(): array
  {
    return [
      'byte_size' => 'integer',
      'uploaded_bytes' => 'integer',
      'chunk_size' => 'integer',
      'total_chunks' => 'integer',
      'received_chunks' => 'array',
      'sort_order' => 'integer',
      'started_at' => 'datetime',
      'uploaded_at' => 'datetime',
      'processed_at' => 'datetime',
    ];
  }

  public function operation(): BelongsTo
  {
    return $this->belongsTo(UploadOperation::class, 'upload_operation_id');
  }

  public function mediaItem(): BelongsTo
  {
    return $this->belongsTo(MemoryMediaItem::class, 'memory_media_item_id');
  }

  public function receivedChunksList(): array
  {
    $raw = is_array($this->received_chunks) ? $this->received_chunks : [];
    $clean = array_values(array_unique(array_map('intval', $raw)));
    sort($clean);
    return $clean;
  }

  public function progressPercent(): int
  {
    if ($this->status === self::STATUS_COMPLETED) {
      return 100;
    }

    if ($this->status === self::STATUS_PROCESSING) {
      return 92;
    }

    if ($this->status === self::STATUS_UPLOADED) {
      return 85;
    }

    if ($this->byte_size <= 0) {
      return $this->total_chunks > 0
        ? (int) min(80, round((count($this->receivedChunksList()) / $this->total_chunks) * 80))
        : 0;
    }

    $ratio = min(1.0, max(0.0, $this->uploaded_bytes / $this->byte_size));
    return (int) min(80, max(0, round($ratio * 80)));
  }

  public function resolvePublicUrl(?string $path, ?string $origin = null): string
  {
    if (!$path) {
      return '';
    }

    $cleanOrigin = rtrim((string) ($origin ?: ''), '/');
    if ($cleanOrigin !== '') {
      return $cleanOrigin . '/storage/' . ltrim($path, '/');
    }

    if (app()->bound('request') && ($req = app('request')) instanceof \Illuminate\Http\Request) {
      return rtrim($req->getSchemeAndHttpHost(), '/') . '/storage/' . ltrim($path, '/');
    }

    $disk = Storage::disk($this->disk ?: config('xv.media_disk', 'public'));
    return $disk->url($path);
  }
}
