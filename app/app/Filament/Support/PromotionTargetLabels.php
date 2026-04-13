<?php

namespace App\Filament\Support;

use App\Enums\PromotionDiscountMode;
use App\Models\Bundle;
use App\Models\OptionGroup;
use App\Models\OptionValue;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\PromotionTarget;
use App\Support\CatalogProductsTable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class PromotionTargetLabels
{
    /**
     * @param  ?int  $categoryOptionValueFilterId  id значення опції «Категорія» (батько або підкатегорія)
     * @return array<int, string>
     */
    public static function searchProducts(string $search, ?int $categoryOptionValueFilterId = null): array
    {
        $q = Product::query()->orderByDesc('id');

        if (Schema::hasColumn(CatalogProductsTable::name(), 'product_type')) {
            $q->where(function (Builder $w): void {
                $w->whereIn('product_type', OptionGroup::catalogListingProductTypes())
                    ->orWhereNull('product_type');
            });
        }

        if ($categoryOptionValueFilterId !== null && $categoryOptionValueFilterId > 0) {
            self::applyProductCategoryOptionFilter($q, $categoryOptionValueFilterId);
        }

        $searchTrimmed = trim($search);

        if ($searchTrimmed !== '') {
            $q->where(function ($w) use ($searchTrimmed): void {
                if (ctype_digit($searchTrimmed)) {
                    $w->where('id', (int) $searchTrimmed);
                }
                $w->orWhere('title', 'like', '%'.$searchTrimmed.'%');
            });
        } elseif (
            Schema::hasColumn('option_values', 'parent_id')
            && ($categoryOptionValueFilterId === null || $categoryOptionValueFilterId <= 0)
        ) {
            return [];
        }

        return $q->limit(50)->get()->mapWithKeys(fn (Product $l): array => [
            $l->id => $l->title,
        ])->all();
    }

    /**
     * Група опцій «Категорія» для основного каталогу товарів.
     */
    public static function catalogCategoryOptionGroupId(): int
    {
        $id = OptionGroup::systemCategoryGroupIdForCatalog();

        if ($id > 0) {
            return $id;
        }

        return (int) (OptionGroup::query()
            ->where('slug', 'category')
            ->where('is_active', true)
            ->value('id') ?? 0);
    }

    /**
     * Звужує вибір товарів за значенням з довідника категорій (як у вітрині).
     */
    public static function applyProductCategoryOptionFilter(Builder $query, int $categoryValueId): void
    {
        $categoryGroupId = self::catalogCategoryOptionGroupId();

        if ($categoryGroupId <= 0) {
            return;
        }

        $exists = OptionValue::query()
            ->where('option_group_id', $categoryGroupId)
            ->whereKey($categoryValueId)
            ->exists();

        if (! $exists) {
            return;
        }

        $hasParentColumn = Schema::hasColumn('option_values', 'parent_id');
        $hasListingCategoryParent = Schema::hasColumn(CatalogProductsTable::name(), 'category_parent_value_id');
        $hasListingSubcategory = Schema::hasColumn(CatalogProductsTable::name(), 'category_value_id');

        /** @var list<int> $categoryFilterValueIds */
        $categoryFilterValueIds = [];
        $selectedIsParentCategory = false;
        $selectedRootCategoryId = 0;

        if ($hasParentColumn) {
            $allCategoryIds = OptionValue::query()
                ->where('option_group_id', $categoryGroupId)
                ->get(['id', 'parent_id']);
            $byId = $allCategoryIds->keyBy('id');
            $byParent = $allCategoryIds->groupBy(fn ($row): int => (int) ($row->parent_id ?? 0));
            $selected = $byId->get($categoryValueId);

            if ($selected) {
                $selectedIsParentCategory = $selected->parent_id === null;
                $selectedRootCategoryId = (int) $selected->id;

                $guard = 0;
                while ($guard < 10) {
                    $parentId = (int) ($byId->get($selectedRootCategoryId)?->parent_id ?? 0);
                    if ($parentId <= 0) {
                        break;
                    }
                    $selectedRootCategoryId = $parentId;
                    $guard++;
                }

                $descendantIds = [];
                $queue = [(int) $selected->id];
                $seen = [];
                while ($queue !== []) {
                    $current = array_shift($queue);
                    if ($current === null || isset($seen[$current])) {
                        continue;
                    }
                    $seen[$current] = true;
                    foreach ($byParent->get((int) $current, collect()) as $childRow) {
                        $childId = (int) $childRow->id;
                        $descendantIds[] = $childId;
                        $queue[] = $childId;
                    }
                }

                $categoryFilterValueIds = array_values(array_unique(array_merge([(int) $selected->id], $descendantIds)));
            }
        } else {
            $categoryFilterValueIds = [$categoryValueId];
        }

        if ($categoryFilterValueIds === []) {
            return;
        }

        if ($hasListingCategoryParent && $hasListingSubcategory) {
            $query->where(function (Builder $outer) use (
                $selectedIsParentCategory,
                $categoryValueId,
                $categoryFilterValueIds,
                $selectedRootCategoryId
            ): void {
                $outer->where(function (Builder $cols) use ($selectedIsParentCategory, $categoryValueId, $selectedRootCategoryId, $categoryFilterValueIds): void {
                    if ($selectedIsParentCategory) {
                        $cols->where('category_parent_value_id', $categoryValueId);
                    } else {
                        $cols->where('category_parent_value_id', $selectedRootCategoryId > 0 ? $selectedRootCategoryId : $categoryValueId)
                            ->whereIn('category_value_id', $categoryFilterValueIds);
                    }
                })->orWhere(function (Builder $json) use ($categoryFilterValueIds): void {
                    self::applyVariantOptionsCategoryJsonFilter($json, $categoryFilterValueIds);
                });
            });

            return;
        }

        self::applyVariantOptionsCategoryJsonFilter($query, $categoryFilterValueIds);
    }

    /**
     * @return list<string>
     */
    private static function variantOptionsJsonLikePatterns(int $valueId): array
    {
        $v = (int) $valueId;

        return [
            '%"option_value_ids":['.$v.']%',
            '%"option_value_ids":['.$v.',%',
            '%"option_value_ids":[%'.$v.']%',
            '%"option_value_ids":[%'.$v.',%',
            '%"option_value_ids":[%,'.$v.']%',
            '%"option_value_ids":[%,'.$v.',%',
            '%"option_value_ids": ['.$v.']%',
            '%"option_value_ids": ['.$v.',%',
            '%"option_value_ids": [%'.$v.']%',
            '%"option_value_ids": [%'.$v.',%',
            '%"option_value_ids": [%,'.$v.']%',
            '%"option_value_ids": [%,'.$v.',%',
        ];
    }

    /**
     * @param  list<int>  $categoryFilterValueIds
     */
    private static function applyVariantOptionsCategoryJsonFilter(Builder $query, array $categoryFilterValueIds): void
    {
        if ($categoryFilterValueIds === []) {
            return;
        }

        $isMysql = DB::connection()->getDriverName() === 'mysql';

        $query->where(function (Builder $outer) use ($categoryFilterValueIds, $isMysql): void {
            foreach ($categoryFilterValueIds as $valueId) {
                $v = (int) $valueId;

                $outer->orWhere(function (Builder $inner) use ($v, $isMysql): void {
                    if ($isMysql) {
                        $inner->whereRaw(
                            'JSON_SEARCH(COALESCE(variant_options, JSON_ARRAY()), \'one\', CAST(? as CHAR), NULL, \'$[*].option_value_ids[*]\') IS NOT NULL',
                            [$v]
                        );
                    }

                    foreach (self::variantOptionsJsonLikePatterns($v) as $pattern) {
                        $inner->orWhere('variant_options', 'like', $pattern);
                    }
                });
            }
        });
    }

    public static function formatProductPromotionLabel(Product $listing): string
    {
        $price = number_format((float) ($listing->price ?? 0), 0, '', ' ');

        return (string) $listing->title.' · базова ціна '.$price.' ₴';
    }

    public static function formatProductVariantLabel(ProductVariant $v, int $categoryGroupId): string
    {
        $price = number_format((float) $v->price, 0, '', ' ');
        $bits = ['#'.$v->id, $price.' ₴'];

        $optParts = [];
        foreach ($v->options ?? [] as $pair) {
            $gid = (int) ($pair['option_group_id'] ?? 0);
            $vid = (int) ($pair['option_value_id'] ?? 0);
            if ($gid <= 0 || $vid <= 0 || $gid === $categoryGroupId) {
                continue;
            }
            $g = OptionGroup::query()->whereKey($gid)->value('name');
            $name = OptionValue::query()->whereKey($vid)->value('name');
            if ($g && $name) {
                $optParts[] = $g.': '.$name;
            }
        }
        if ($optParts !== []) {
            $bits[] = implode(', ', $optParts);
        }

        return implode(' · ', $bits);
    }

    public static function describeTarget(PromotionTarget $record): string
    {
        if ($record->target_type === 'product') {
            $listing = Product::query()->find($record->target_id);
            if (! $listing) {
                return 'Товар #'.$record->target_id;
            }

            return self::formatProductPromotionLabel($listing);
        }

        if ($record->target_type === 'product_variant') {
            $v = ProductVariant::query()->with('product')->find($record->target_id);
            if (! $v) {
                return 'Варіант #'.$record->target_id;
            }
            $title = $v->product?->title ?? 'Товар';
            $catId = self::catalogCategoryOptionGroupId();

            return $title.' · '.self::formatProductVariantLabel($v, $catId);
        }

        if ($record->target_type === 'bundle') {
            $bundle = Bundle::query()->find($record->target_id);

            return $bundle
                ? 'Комплект: '.$bundle->title
                : 'Комплект #'.$record->target_id;
        }

        return (string) $record->target_type.' #'.$record->target_id;
    }

    public static function formatDiscountSummary(PromotionTarget $record): string
    {
        $v = (float) $record->discount_value;

        return match ($record->discount_mode) {
            PromotionDiscountMode::Percent => $v.'%',
            PromotionDiscountMode::AmountOff => '−'.number_format($v, 2, ',', ' ').' ₴',
            PromotionDiscountMode::FixedPrice => 'Ціна '.number_format($v, 2, ',', ' ').' ₴',
            default => (string) $v,
        };
    }
}
