<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ShopFooterColumn extends Model
{
    protected $fillable = [
        'sort_order',
        'title_uk',
        'title_en',
        'title_ru',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    /** @return HasMany<ShopFooterLink, $this> */
    public function links(): HasMany
    {
        return $this->hasMany(ShopFooterLink::class)->orderBy('sort_order')->orderBy('id');
    }
}
