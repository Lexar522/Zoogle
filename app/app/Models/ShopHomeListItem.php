<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShopHomeListItem extends Model
{
    public const LIST_BESTSELLERS = 'bestsellers';

    public const LIST_RECOMMENDED = 'recommended';

    protected $fillable = [
        'list',
        'product_id',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    /** @param  Builder<self>  $query */
    public function scopeForList(Builder $query, string $list): void
    {
        $query->where('list', $list);
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
