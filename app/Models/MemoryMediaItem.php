<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasVersion7Uuids;
use App\Observers\MemoryMediaItemObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

#[ObservedBy([MemoryMediaItemObserver::class])]
class MemoryMediaItem extends Model
{
  use HasVersion7Uuids;
  protected $fillable = [
    'memory_id',
    'external_id',
    'media_blob_key',
    'media_type',
    'disk',
    'file_path',
    'thumb_path',
    'poster_path',
    'mime_type',
    'byte_size',
    'sort_order',
  ];

  protected function casts(): array
  {
    return [
      'byte_size' => 'integer',
      'sort_order' => 'integer',
    ];
  }

  public function memory(): BelongsTo
  {
    return $this->belongsTo(Memory::class);
  }

  public function storedPaths(): array
  {
    return array_values(array_filter([
      $this->file_path,
      $this->thumb_path,
      $this->poster_path,
    ]));
  }

  public function publicUrls(): array
  {
    $diskName = $this->disk ?: config('xv.media_disk', 'public');
    $driver = (string) (config("filesystems.disks.{$diskName}.driver") ?? 'local');
    $disk = Storage::disk($diskName);

    $resolveUrl = static function (?string $path) use ($disk, $driver): string {
      if (!$path) {
        return '';
      }
      if ($driver !== 'local') {
        return $disk->url($path);
      }
      if (app()->bound('request') && ($req = app('request')) instanceof \Illuminate\Http\Request) {
        $base = $req->getSchemeAndHttpHost();
        return rtrim($base, '/') . '/storage/' . ltrim($path, '/');
      }
      return $disk->url($path);
    };

    return [
      'imageUrl' => $resolveUrl($this->file_path),
      'thumbUrl' => $resolveUrl($this->thumb_path),
      'posterUrl' => $resolveUrl($this->poster_path),
    ];
  }
}
