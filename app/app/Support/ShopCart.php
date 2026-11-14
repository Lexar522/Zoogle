<?php

namespace App\Support;

final class ShopCart
{
    /**
     * @param  array<string, mixed>  $raw
     * @return array<string, array{
     *     line_kind: 'product'|'bundle',
     *     product_id?: int,
     *     bundle_id?: int,
     *     qty: int,
     *     option_value_ids: list<int>
     * }>
     */
    public static function normalize(array $raw): array
    {
        $out = [];

        foreach ($raw as $key => $val) {
            $cartKey = (string) $key;
            $listingIdFromKey = is_numeric($cartKey) ? (int) $cartKey : 0;
            $bundleIdFromKey = str_starts_with($cartKey, 'bundle:')
                ? (int) substr($cartKey, strlen('bundle:'))
                : 0;

            if (is_array($val)) {
                $lineKind = (($val['line_kind'] ?? null) === 'bundle' || (int) ($val['bundle_id'] ?? $bundleIdFromKey) > 0)
                    ? 'bundle'
                    : 'product';

                if ($lineKind === 'bundle') {
                    $bundleId = (int) ($val['bundle_id'] ?? $bundleIdFromKey);
                    if ($bundleId <= 0) {
                        continue;
                    }

                    $rawOptionValueIds = $val['option_value_ids'] ?? [];
                    $optionValueIds = [];
                    if (is_array($rawOptionValueIds)) {
                        foreach ($rawOptionValueIds as $id) {
                            $n = (int) $id;
                            if ($n > 0) {
                                $optionValueIds[$n] = true;
                            }
                        }
                    }

                    $out[$cartKey] = [
                        'line_kind' => 'bundle',
                        'bundle_id' => $bundleId,
                        'qty' => max(1, (int) ($val['qty'] ?? 1)),
                        'option_value_ids' => array_keys($optionValueIds),
                    ];

                    continue;
                }

                $productId = (int) ($val['product_id'] ?? $val['listing_id'] ?? $listingIdFromKey);
                if ($productId <= 0) {
                    continue;
                }
                $rawOptionValueIds = $val['option_value_ids'] ?? [];
                $optionValueIds = [];
                if (is_array($rawOptionValueIds)) {
                    foreach ($rawOptionValueIds as $id) {
                        $n = (int) $id;
                        if ($n > 0) {
                            $optionValueIds[$n] = true;
                        }
                    }
                }
                $variantId = (int) ($val['variant_id'] ?? 0);
                $out[$cartKey] = [
                    'line_kind' => 'product',
                    'product_id' => $productId,
                    'qty' => max(1, (int) ($val['qty'] ?? 1)),
                    'option_value_ids' => array_keys($optionValueIds),
                    'variant_id' => $variantId > 0 ? $variantId : null,
                ];
            } else {
                if ($listingIdFromKey <= 0) {
                    continue;
                }
                $out[$cartKey] = [
                    'line_kind' => 'product',
                    'product_id' => $listingIdFromKey,
                    'qty' => max(1, (int) $val),
                    'option_value_ids' => [],
                ];
            }
        }

        return $out;
    }

    /**
     * @param  array<string, array<string, mixed>>  $primary
     * @param  array<string, array<string, mixed>>  $secondary
     * @return array<string, array<string, mixed>>
     */
    public static function merge(array $primary, array $secondary): array
    {
        $merged = self::normalize($secondary);
        foreach (self::normalize($primary) as $key => $row) {
            if (! isset($merged[$key])) {
                $merged[$key] = $row;

                continue;
            }

            $merged[$key]['qty'] = max(1, (int) ($merged[$key]['qty'] ?? 1)) + max(1, (int) ($row['qty'] ?? 1));
        }

        return $merged;
    }
}
