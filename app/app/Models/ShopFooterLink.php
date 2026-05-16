<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShopFooterLink extends Model
{
    protected $fillable = [
        'shop_footer_column_id',
        'sort_order',
        'label_uk',
        'label_en',
        'label_ru',
        'url',
        'open_new_tab',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'open_new_tab' => 'boolean',
        ];
    }

    /** @return BelongsTo<ShopFooterColumn, $this> */
    public function column(): BelongsTo
    {
        return $this->belongsTo(ShopFooterColumn::class, 'shop_footer_column_id');
    }
}
