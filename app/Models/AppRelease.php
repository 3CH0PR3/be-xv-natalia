<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class AppRelease extends Model
{
    protected $fillable = [
        'version',
        'version_order',
        'type',
        'date_label',
        'title',
        'highlights',
        'commit_sha',
        'build_id',
        'applied_at_ms',
    ];

    protected function casts(): array
    {
        return [
            'version_order' => 'integer',
            'highlights' => 'array',
            'applied_at_ms' => 'integer',
        ];
    }

    public function scopeLatestOrder(Builder $query): Builder
    {
        return $query->orderByDesc('version_order');
    }

    public static function computeVersionOrder(string $version): int
    {
        if (preg_match('/^(\d+)\.(\d+)\.(\d+)/', trim($version), $m)) {
            return ((int) $m[1] * 1_000_000) + ((int) $m[2] * 1_000) + (int) $m[3];
        }

        return 1_000_000;
    }
}
