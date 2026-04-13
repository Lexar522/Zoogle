<?php

namespace App\Support;

use App\Models\Product;

/**
 * Опції варіанта узгоджені з карткою товару (variant_options): групи та значення з переліку товару.
 */
final class VariantOptionsAllowlist
{
    /**
     * Усі групи опцій товару (рядки в variant_options), крім категорії.
     *
     * @return array<int, true>
     */
    public static function listingDeclaredGroupIds(Product $product, int $categoryGroupId): array
    {
        $gids = [];
        foreach ($product->variant_options ?? [] as $row) {
            $gid = (int) ($row['option_group_id'] ?? 0);
            if ($gid > 0 && $gid !== $categoryGroupId) {
                $gids[$gid] = true;
            }
        }

        return $gids;
    }

    /**
     * Група є в картці, але значення ще не відмічені — пари варіанта для неї недопустимі.
     *
     * @return array<int, true>
     */
    public static function listingGroupsWithoutChosenValues(Product $product, int $categoryGroupId): array
    {
        $blocked = [];
        foreach ($product->variant_options ?? [] as $row) {
            $gid = (int) ($row['option_group_id'] ?? 0);
            if ($gid <= 0 || $gid === $categoryGroupId) {
                continue;
            }
            $ids = collect($row['option_value_ids'] ?? [])
                ->map(fn ($id) => (int) $id)
                ->filter(fn (int $id) => $id > 0)
                ->values()
                ->all();
            if ($ids === []) {
                $blocked[$gid] = true;
            }
        }

        return $blocked;
    }

    /**
     * group_id => [ value_id => true ] — лише групи з непорожнім списком значень у картці.
     *
     * @return array<int, array<int, true>>
     */
    public static function listingValueAllowlistByGroup(Product $product, int $categoryGroupId): array
    {
        $byGroup = [];

        foreach ($product->variant_options ?? [] as $row) {
            $gid = (int) ($row['option_group_id'] ?? 0);
            if ($gid <= 0 || $gid === $categoryGroupId) {
                continue;
            }

            $ids = collect($row['option_value_ids'] ?? [])
                ->map(fn ($id) => (int) $id)
                ->filter(fn (int $id) => $id > 0)
                ->values()
                ->all();

            if ($ids === []) {
                continue;
            }

            foreach ($ids as $id) {
                $byGroup[$gid][$id] = true;
            }
        }

        return $byGroup;
    }

    /**
     * Фільтрація пар варіанта для збереження та вітрини.
     *
     * Якщо в картці товару задані значення хоча б для однієї групи — застосовується суворий режим:
     * для груп, у яких на картці відмічені конкретні значення, лишаються лише пари з цього переліку.
     * Пари для груп, яких немає в «Опції товару», або рядок групи є, але без жодної галочки, не відкидаються —
     * інакше на вітрині зникають осі, які живуть тільки у варіантах.
     *
     * Якщо картка ще не обмежує значення (немає жодної групи з відміченими значеннями) — пари не звужуються.
     *
     * @param  list<array<string, mixed>>  $pairs
     * @return list<array<string, mixed>>
     */
    public static function filterPairs(array $pairs, Product $product, int $categoryGroupId): array
    {
        $allow = self::listingValueAllowlistByGroup($product, $categoryGroupId);

        if ($allow === []) {
            return $pairs;
        }

        $declared = self::listingDeclaredGroupIds($product, $categoryGroupId);
        $blockedEmpty = self::listingGroupsWithoutChosenValues($product, $categoryGroupId);

        $out = [];

        foreach ($pairs as $pair) {
            $gid = (int) ($pair['option_group_id'] ?? 0);
            $vid = (int) ($pair['option_value_id'] ?? 0);

            if ($gid <= 0 || $vid <= 0 || $gid === $categoryGroupId) {
                continue;
            }

            if (! isset($declared[$gid])) {
                $out[] = $pair;

                continue;
            }

            if (isset($blockedEmpty[$gid])) {
                $out[] = $pair;

                continue;
            }

            if (! isset($allow[$gid][$vid])) {
                continue;
            }

            $out[] = $pair;
        }

        return $out;
    }
}
