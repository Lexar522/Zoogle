<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Bundle extends Model
{
    protected $fillable = [
        'title',
        'slug',
        'sku',
        'description',
        'short_description',
        'photos',
        'variant_options',
        'category_parent_value_id',
        'category_value_id',
        'is_visible',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'photos' => 'array',
            'variant_options' => 'array',
            'is_visible' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(BundleItem::class)->orderBy('sort_order');
    }

    public function firstCatalogPhotoPath(): ?string
    {
        foreach ($this->normalizePhotoList($this->photos) as $path) {
            return $path;
        }

        /** @var EloquentCollection<int, BundleItem> $items */
        $items = $this->relationLoaded('items') ? $this->items : $this->items()->with(['product.variants'])->get();

        foreach ($items as $item) {
            $product = $item->product;
            if (! $product) {
                continue;
            }

            foreach ($this->normalizePhotoList($product->photos) as $path) {
                return $path;
            }

            $variants = $product->relationLoaded('variants') ? $product->variants : $product->variants()->get();
            foreach ($variants as $variant) {
                foreach ($this->normalizePhotoList($variant->photos) as $path) {
                    return $path;
                }
            }
        }

        return null;
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
