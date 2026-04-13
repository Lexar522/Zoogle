<?php

namespace App\Models;

use App\Models\Concerns\HasVariantStockBehavior;
use App\Services\VariantPricingService;
use App\Support\CatalogProductVariantsTable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;

class ProductVariant extends Model
{
    use HasFactory;
    use HasVariantStockBehavior;

    public function getTable(): string
    {
        return CatalogProductVariantsTable::name();
    }

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_visible' => true,
        'is_sold' => false,
    ];

    protected $fillable = [
        'product_id',
        'price',
        'compare_at_price',
        'quantity',
        'is_available',
        'is_low_stock',
        'is_visible',
        'allows_preorder',
        'is_sold',
        'options',
        'photos',
        'sort_order',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'compare_at_price' => 'decimal:2',
        'quantity' => 'integer',
        'is_available' => 'boolean',
        'is_low_stock' => 'boolean',
        'is_visible' => 'boolean',
        'allows_preorder' => 'boolean',
        'is_sold' => 'boolean',
        'options' => 'array',
        'photos' => 'array',
        'sort_order' => 'integer',
    ];

    public function product(): BelongsTo
    {
        $fk = Schema::hasColumn($this->getTable(), 'product_id')
            ? 'product_id'
            : 'animal_listing_id';

        return $this->belongsTo(Product::class, $fk);
    }

    public function isOnSale(): bool
    {
        return app(VariantPricingService::class)->quoteProductVariant($this)->isOnSale();
    }

    public function scopeOnSale(Builder $query): void
    {
        $query->whereExists(function ($sub): void {
            VariantPricingService::bindActivePromotionExists($sub, 'product_variant', 'product_variants.id');
        });
    }
}
