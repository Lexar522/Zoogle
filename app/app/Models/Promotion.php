<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class Promotion extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'description',
        'is_active',
        'starts_at',
        'ends_at',
        'priority',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'priority' => 'integer',
    ];

    public function targets(): HasMany
    {
        return $this->hasMany(PromotionTarget::class);
    }

    public function scopeActiveAt(Builder $query, \DateTimeInterface $at): void
    {
        $moment = Carbon::parse($at);
        $query->where('is_active', true)
            ->where(function (Builder $q) use ($moment): void {
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', $moment);
            })
            ->where(function (Builder $q) use ($moment): void {
                $q->whereNull('ends_at')->orWhere('ends_at', '>=', $moment);
            });
    }

    public function appliesAt(\DateTimeInterface $at): bool
    {
        if (! $this->is_active) {
            return false;
        }

        $moment = Carbon::parse($at);

        if ($this->starts_at && $this->starts_at->greaterThan($moment)) {
            return false;
        }

        if ($this->ends_at && $this->ends_at->lessThan($moment)) {
            return false;
        }

        return true;
    }
}
