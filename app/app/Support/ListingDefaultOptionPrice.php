<?php

namespace App\Support;

use App\Models\OptionGroup;
use App\Models\Product;

/**
 * Головна / преселект-ціна з доплат опцій (variant_options[].default_option_value_id).
 * Якщо default не задано — як на PDP: значення без доплати або з мінімальною.
 * Сімʼя «Розмір: …» рахується один раз (не сума всіх груп розміру).
 */
final class ListingDefaultOptionPrice
{
    /**
     * Сума доплат для преселекту опцій (поверх базової ціни товару).
     */
    public static function addonTotalForProduct(Product $product): float
    {
        $rows = is_array($product->variant_options ?? null) ? $product->variant_options : [];

        return self::addonTotalForRows($rows);
    }

    /**
     * @param  list<array<string, mixed>>  $variantOptionsRows
     */
    public static function addonTotalForRows(array $variantOptionsRows): float
    {
        if ($variantOptionsRows === []) {
            return 0.0;
        }

        $categoryGroupId = 0;
        $groupNamesById = [];
        try {
            if (SchemaCache::hasTable('option_groups')) {
                $categoryGroupId = OptionGroup::systemCategoryGroupIdForCatalog();
                $groupIds = [];
                foreach ($variantOptionsRows as $row) {
                    if (! is_array($row)) {
                        continue;
                    }
                    $id = (int) ($row['option_group_id'] ?? 0);
                    if ($id > 0) {
                        $groupIds[$id] = true;
                    }
                }
                if ($groupIds !== []) {
                    $groupNamesById = StorefrontOptionCatalog::groupsByIds(array_keys($groupIds))
                        ->map(fn (OptionGroup $group): string => (string) $group->name)
                        ->all();
                }
            }
        } catch (\Throwable) {
            $categoryGroupId = 0;
        }
        $total = 0.0;
        $claimedExclusivityKeys = [];

        foreach ($variantOptionsRows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $groupId = (int) ($row['option_group_id'] ?? 0);
            if ($groupId <= 0 || ($categoryGroupId > 0 && $groupId === $categoryGroupId)) {
                continue;
            }

            $valueIds = collect($row['option_value_ids'] ?? [])
                ->map(fn ($id) => (int) $id)
                ->filter(fn (int $id) => $id > 0)
                ->values()
                ->all();

            if (count($valueIds) < 2) {
                continue;
            }

            $exclusivityKey = OptionGroupExclusivity::keyFromName(
                isset($groupNamesById[$groupId]) ? (string) $groupNamesById[$groupId] : null
            );
            if ($exclusivityKey !== null && isset($claimedExclusivityKeys[$exclusivityKey])) {
                continue;
            }

            $defaultId = self::resolvedDefaultValueId($row, $valueIds, $variantOptionsRows, $groupId);
            if ($defaultId === null) {
                continue;
            }

            $total += ListingOptionValuePrices::priceAddonForValue($variantOptionsRows, $groupId, $defaultId);
            if ($exclusivityKey !== null) {
                $claimedExclusivityKeys[$exclusivityKey] = true;
            }
        }

        return round($total, 2);
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  list<int>  $valueIds
     * @param  list<array<string, mixed>>  $variantOptionsRows
     */
    public static function resolvedDefaultValueId(
        array $row,
        array $valueIds,
        array $variantOptionsRows,
        int $groupId,
    ): ?int {
        $preferred = (int) ($row['default_option_value_id'] ?? 0);
        if ($preferred > 0 && in_array($preferred, $valueIds, true)) {
            return $preferred;
        }

        return self::minAddonValueId($variantOptionsRows, $groupId, $valueIds);
    }

    /**
     * @param  list<array<string, mixed>>  $variantOptionsRows
     * @param  list<int>  $valueIds
     */
    public static function minAddonValueId(array $variantOptionsRows, int $groupId, array $valueIds): ?int
    {
        $zeroId = null;
        $lowestId = null;
        $lowestAddon = null;

        foreach ($valueIds as $valueId) {
            $addon = ListingOptionValuePrices::priceAddonForValue($variantOptionsRows, $groupId, $valueId);
            if ($addon <= 0.0) {
                return $valueId;
            }
            if ($lowestAddon === null || $addon < $lowestAddon) {
                $lowestAddon = $addon;
                $lowestId = $valueId;
            }
            if ($zeroId === null && $addon <= 0.0) {
                $zeroId = $valueId;
            }
        }

        return $lowestId ?? $zeroId;
    }
}
