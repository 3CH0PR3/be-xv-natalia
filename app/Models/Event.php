<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Event extends Model
{
    protected $fillable = [
        'code',
        'honoree_name',
        'subtitle',
        'venue',
        'starts_at',
        'party_celebrations_count',
        'db_scope',
        'epoch',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'party_celebrations_count' => 'integer',
            'epoch' => 'integer',
        ];
    }

    public function tables(): HasMany
    {
        return $this->hasMany(EventTable::class)->orderBy('number');
    }

    public function deviceLocks(): HasMany
    {
        return $this->hasMany(DeviceLock::class);
    }

    public function memories(): HasMany
    {
        return $this->hasMany(Memory::class);
    }

    public function partyReactions(): HasMany
    {
        return $this->hasMany(PartyReaction::class);
    }

    public function scopeByCode(Builder $query, string $code): Builder
    {
        return $query->where('code', trim($code));
    }

    public function hasPartyReactionBy(?string $actorKey, ?string $deviceId): bool
    {
        if (!$actorKey && !$deviceId) {
            return false;
        }

        return $this->partyReactions()
            ->where(function (Builder $q) use ($actorKey, $deviceId): void {
                if ($actorKey) {
                    $q->orWhere('actor_key', $actorKey);
                }
                if ($deviceId) {
                    $q->orWhere('device_id', $deviceId);
                }
            })
            ->exists();
    }
}
