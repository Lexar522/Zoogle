<?php

namespace App\Models;

use App\Support\CatalogProductsTable;
use App\Support\RichTextSanitizer;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Schema;

class Product extends Model
{
    use HasFactory;

    public function getTable(): string
    {
        return CatalogProductsTable::name();
    }

    protected $fillable = [
        'title',
        'slug',
        'sku',
        'product_type',
        'category_parent_value_id',
        'category_value_id',
        'description',
        'short_description',
        'dimensions',
        'price',
        'is_available',
        'search_tags',
        'photos',
        'variant_options',
        'published_at',
    ];

    protected $casts = [
        'sku' => 'string',
        'product_type' => 'string',
        'category_parent_value_id' => 'integer',
        'category_value_id' => 'integer',
        'price' => 'decimal:2',
        'is_available' => 'boolean',
        'search_tags' => 'array',
        'photos' => 'array',
        'variant_options' => 'array',
        'published_at' => 'datetime',
    ];

    public function orderItems(): HasMany
    {
        $fk = Schema::hasColumn((new OrderItem)->getTable(), 'product_id')
            ? 'product_id'
            : 'animal_listing_id';

        return $this->hasMany(OrderItem::class, $fk);
    }

    public function variants(): HasMany
    {
        $variantTable = (new ProductVariant)->getTable();
        $fk = Schema::hasColumn($variantTable, 'product_id')
            ? 'product_id'
            : 'animal_listing_id';

        return $this->hasMany(ProductVariant::class, $fk);
    }

    /** @return BelongsToMany<User, $this> */
    public function favoritedByUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'product_favorites')->withTimestamps();
    }

    public function firstCatalogPhotoPath(): ?string
    {
        foreach ($this->normalizePhotoList($this->photos) as $path) {
            return $path;
        }

        $variants = $this->relationLoaded('variants') ? $this->variants : $this->variants()->get();

        foreach ($variants as $variant) {
            foreach ($this->normalizePhotoList($variant->photos ?? null) as $path) {
                return $path;
            }
        }

        return null;
    }

    public function safeShortDescriptionHtml(): string
    {
        return RichTextSanitizer::toHtml($this->short_description);
    }

    public function safeDescriptionHtml(): string
    {
        return RichTextSanitizer::toHtml($this->description);
    }

    public function hasRichDescriptionSection(): bool
    {
        return $this->hasDisplayableShortDescription() || $this->hasDisplayableDescription();
    }

    public function hasDisplayableShortDescription(): bool
    {
        return RichTextSanitizer::hasVisibleContent($this->short_description);
    }

    public function hasDisplayableDescription(): bool
    {
        return RichTextSanitizer::hasVisibleContent($this->description);
    }

    /**
     * @return list<string>
     */
    private function normalizePhotoList(mixed $photos): array
    {
        if (! is_array($photos)) {
            return [];
        }

        $out = [];
        foreach ($photos as $path) {
            if (! is_string($path) || $path === '') {
                continue;
            }

            $out[] = ltrim($path, '/');
        }

        return $out;
    }
}
