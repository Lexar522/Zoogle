<?php

namespace App\Support;

/**
 * Доплати за значення опцій з JSON картки товару (variant_options[].value_prices).
 */
final class ListingOptionValuePrices
{
    /**
     * @param  list<array<string, mixed>>|null  $variantOptionsRows
     */
    public static function priceAddonForValue(?array $variantOptionsRows, int $groupId, int $valueId): float
    {
        if ($variantOptionsRows === null || $variantOptionsRows === []) {
            return 0.0;
        }

        foreach ($variantOptionsRows as $row) {
            if ((int) ($row['option_group_id'] ?? 0) !== $groupId) {
                continue;
            }
            $m = $row['value_prices'] ?? [];
            if (! is_array($m)) {
                continue;
            }
            $raw = $m[(string) $valueId] ?? $m[$valueId] ?? null;
            if ($raw === null || $raw === '') {
                continue;
            }

            return round(max(0, (float) $raw), 2);
        }

        return 0.0;
    }
}
