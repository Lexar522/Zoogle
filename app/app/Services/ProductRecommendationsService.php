<?php

namespace App\Services;

use App\Models\OptionGroup;
use App\Models\Product;
use App\Support\SchemaCache;
use Illuminate\Support\Collection;

class ProductRecommendationsService
{
    /**
     * Ручний список рекомендованих товарів зі сторінки редагування (порядок = sort_order).
     *
     * @return Collection<int, Product>
     */
    public function forProductPage(Product $current, int $limit = 8): Collection
    {
        $limit = max(1, $limit);

        if (! SchemaCache::hasTable('product_recommendations')) {
            return collect();
        }

        $query = $current->recommendedProducts()
            ->whereKeyNot((int) $current->id)
            ->whereIn('product_type', OptionGroup::catalogListingProductTypes());

        if (SchemaCache::hasColumn($current->getTable(), 'is_available')) {
            $query->where('is_available', true);
        }

        return $query
            ->with('variants')
            ->withCount('favoritedByUsers')
            ->limit($limit)
            ->get()
            ->values();
    }

    /**
     * @param  list<array{product_id?: int|string|null}>  $rows
     */
    public static function syncForProduct(Product $product, array $rows): void
    {
        if (! SchemaCache::hasTable('product_recommendations')) {
            return;
        }

        $sync = [];
        $order = 0;
        $seen = [];
        $selfId = (int) $product->id;

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $id = (int) ($row['product_id'] ?? 0);
            if ($id <= 0 || $id === $selfId || isset($seen[$id])) {
                continue;
            }

            if (! Product::query()->whereKey($id)->exists()) {
                continue;
            }

            $seen[$id] = true;
            $sync[$id] = ['sort_order' => $order];
            $order++;
        }

        $product->recommendedProducts()->sync($sync);
    }
}
