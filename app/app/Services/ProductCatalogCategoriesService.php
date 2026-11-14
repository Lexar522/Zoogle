<?php

namespace App\Services;

use App\Models\OptionGroup;
use App\Models\OptionValue;
use App\Models\Product;
use App\Support\SchemaCache;

class ProductCatalogCategoriesService
{
    /**
     * Основна категорія товару (листок або корінь) + додаткові з форми.
     * Основна завжди присутня в pivot.
     *
     * @param  list<int|string>|array<int, int|string>  $additionalCategoryIds
     */
    public static function syncForProduct(Product $product, array $additionalCategoryIds = []): void
    {
        if (! SchemaCache::hasTable('product_categories')) {
            return;
        }

        $categoryGroupId = OptionGroup::systemCategoryGroupIdForCatalog();
        if ($categoryGroupId <= 0) {
            return;
        }

        $primaryId = self::primaryCategoryNodeId($product);
        $wanted = [];

        if ($primaryId > 0) {
            $wanted[$primaryId] = true;
        }

        foreach ($additionalCategoryIds as $raw) {
            $id = (int) $raw;
            if ($id > 0) {
                $wanted[$id] = true;
            }
        }

        if ($wanted === []) {
            $product->categories()->sync([]);

            return;
        }

        $validIds = OptionValue::query()
            ->where('option_group_id', $categoryGroupId)
            ->whereIn('id', array_keys($wanted))
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        $sync = [];
        $order = 0;

        // Основна першою в порядку.
        if ($primaryId > 0 && in_array($primaryId, $validIds, true)) {
            $sync[$primaryId] = ['sort_order' => $order++];
        }

        foreach ($validIds as $id) {
            if (isset($sync[$id])) {
                continue;
            }
            $sync[$id] = ['sort_order' => $order++];
        }

        $product->categories()->sync($sync);
    }

    public static function primaryCategoryNodeId(Product $product): int
    {
        $leaf = (int) ($product->category_value_id ?? 0);
        if ($leaf > 0) {
            return $leaf;
        }

        return (int) ($product->category_parent_value_id ?? 0);
    }

    /**
     * Id додаткових категорій для форми (без основної).
     *
     * @return list<string>
     */
    public static function additionalCategoryIdsForForm(Product $product): array
    {
        if (! SchemaCache::hasTable('product_categories')) {
            return [];
        }

        $primaryId = self::primaryCategoryNodeId($product);

        return $product->categories()
            ->get(['option_values.id'])
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->reject(fn (int $id): bool => $id <= 0 || $id === $primaryId)
            ->values()
            ->map(fn (int $id): string => (string) $id)
            ->all();
    }
}
