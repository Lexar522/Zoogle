<?php

namespace App\Support;

use App\Enums\PromotionDiscountMode;
use App\Models\OptionGroup;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\Schema;

/**
 * Віртуальні поля форми (товар, нова ціна) ↔ запис promotion_targets.
 */
final class PromotionTargetFormState
{
    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function hydrate(array $data): array
    {
        $type = $data['target_type'] ?? null;
        $tid = (int) ($data['target_id'] ?? 0);

        if ($type === 'bundle' && $tid > 0) {
            $data['line_target'] = 'bundle';
            $data['bundle_id'] = $tid;
        } elseif ($type === 'product' && $tid > 0) {
            $data['line_target'] = 'product';
            $data['catalog_product_id'] = $tid;
            $data['variant_id'] = null;
            $product = Product::query()->find($tid);
            if ($product) {
                self::applyCatalogCategoryFiltersFromProduct($data, $product);
            }
        } elseif ($type === 'product_variant' && $tid > 0) {
            $data['line_target'] = 'product';
            $v = ProductVariant::query()->find($tid);
            $data['catalog_product_id'] = $v?->product_id;
            $data['variant_id'] = $tid;

            if (
                Schema::hasColumn('option_values', 'parent_id')
                && $v
                && $v->product_id
            ) {
                $product = Product::query()->find($v->product_id);
                if ($product) {
                    self::applyCatalogCategoryFiltersFromProduct($data, $product);
                }
            }
        }

        $modeRaw = $data['discount_mode'] ?? null;
        $isFixed = $modeRaw === PromotionDiscountMode::FixedPrice->value
            || $modeRaw === PromotionDiscountMode::FixedPrice
            || ($modeRaw instanceof PromotionDiscountMode && $modeRaw === PromotionDiscountMode::FixedPrice);

        $data['use_advanced_discount'] = ! $isFixed;
        if ($isFixed) {
            $data['sale_price'] = $data['discount_value'] ?? null;
        }

        $data['line_ends_at'] = $data['ends_at'] ?? null;

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function applyCatalogCategoryFiltersFromProduct(array &$data, Product $product): void
    {
        if (! Schema::hasColumn('option_values', 'parent_id')) {
            return;
        }

        $categoryGroupId = OptionGroup::systemCategoryGroupIdForCatalog();
        if ($categoryGroupId <= 0) {
            return;
        }

        $levels = CatalogCategoryTree::levelFieldsForFormFill($product->attributesToArray(), $categoryGroupId);
        for ($i = 1; $i <= CatalogCategoryTree::MAX_DEPTH; $i++) {
            $data['catalog_category_level_'.$i.'_filter_id'] = $levels['category_level_'.$i.'_id'] ?? null;
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function persist(array $data): array
    {
        $lineTarget = $data['line_target'] ?? 'product';

        if ($lineTarget === 'bundle') {
            $data['target_type'] = 'bundle';
            $data['target_id'] = (int) ($data['bundle_id'] ?? 0);
        } else {
            $productId = (int) ($data['catalog_product_id'] ?? 0);
            $data['target_type'] = 'product';
            $data['target_id'] = $productId;
        }

        $useAdv = (bool) ($data['use_advanced_discount'] ?? false);
        if (! $useAdv) {
            $data['discount_mode'] = PromotionDiscountMode::FixedPrice->value;
            $data['discount_value'] = $data['sale_price'] ?? 0;
        }

        $line = $data['line_ends_at'] ?? null;
        $data['ends_at'] = ($line === '' || $line === null) ? null : $line;

        unset(
            $data['catalog_product_id'],
            $data['bundle_id'],
            $data['line_target'],
            $data['sale_price'],
            $data['use_advanced_discount'],
            $data['line_ends_at'],
        );
        for ($i = 1; $i <= CatalogCategoryTree::MAX_DEPTH; $i++) {
            unset($data['catalog_category_level_'.$i.'_filter_id']);
        }

        return $data;
    }
}
