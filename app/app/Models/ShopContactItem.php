<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ShopContactItem extends Model
{
    /** @var list<string> */
    public const KINDS = [
        'phone',
        'email',
        'instagram',
        'tiktok',
        'viber',
        'whatsapp',
        'telegram',
    ];

    protected $fillable = [
        'sort_order',
        'kind',
        'value',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    /** @param  Builder<self>  $query */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query
            ->orderBy('sort_order')
            ->orderBy('id');
    }
}
