<?php

namespace App\Support;

use App\Models\OptionValue;
use Illuminate\Validation\ValidationException;

final class CatalogCategoryTree
{
    public const MAX_DEPTH = 5;

    /**
     * @return list<int> ids from root to node (inclusive)
     */
    public static function ancestorsChainFromNode(int $valueId, int $categoryGroupId): array
    {
        if ($valueId <= 0 || $categoryGroupId <= 0) {
            return [];
        }

        $rev = [];
        $current = $valueId;
        for ($g = 0; $g < 32 && $current > 0; $g++) {
            $row = OptionValue::query()
                ->whereKey($current)
                ->where('option_group_id', $categoryGroupId)
                ->first(['id', 'parent_id']);
            if (! $row) {
                break;
            }
            $rev[] = (int) $row->id;
            $current = (int) ($row->parent_id ?? 0);
        }

        return array_reverse($rev);
    }

    public static function rootIdFromNode(int $valueId, int $categoryGroupId): int
    {
        $chain = self::ancestorsChainFromNode($valueId, $categoryGroupId);

        return (int) ($chain[0] ?? 0);
    }

    /**
     * @param  array<int, int>  $levelsByOneBasedIndex
     * @return array{category_parent_value_id: int, category_value_id: ?int, category_option_id_for_variant_row: int}
     */
    public static function resolveStoredCategoryColumns(array $levelsByOneBasedIndex, int $categoryGroupId): array
    {
        $ordered = [];
        for ($i = 1; $i <= self::MAX_DEPTH; $i++) {
            $v = (int) ($levelsByOneBasedIndex[$i] ?? 0);
            if ($v > 0) {
                $ordered[] = $v;
            }
        }

        if ($ordered === []) {
            throw new \InvalidArgumentException('Порожня категорія.');
        }

        $prevId = 0;
        foreach ($ordered as $idx => $vid) {
            $row = OptionValue::query()
                ->whereKey($vid)
                ->where('option_group_id', $categoryGroupId)
                ->where('is_active', true)
                ->first(['id', 'parent_id']);

            if (! $row) {
                throw new \InvalidArgumentException('Некоректне або неактивне значення категорії.');
            }

            $actualParent = $row->parent_id !== null ? (int) $row->parent_id : null;
            if ($idx === 0) {
                if ($actualParent !== null) {
                    throw new \InvalidArgumentException('Перший рівень має бути коренем каталогу.');
                }
            } elseif ($actualParent !== $prevId) {
                throw new \InvalidArgumentException('Ланцюг категорій не послідовний.');
            }
            $prevId = $vid;
        }

        $deepest = $ordered[array_key_last($ordered)];
        $root = $ordered[0];

        if (count($ordered) === 1) {
            return [
                'category_parent_value_id' => $root,
                'category_value_id' => null,
                'category_option_id_for_variant_row' => $root,
            ];
        }

        return [
            'category_parent_value_id' => $root,
            'category_value_id' => $deepest,
            'category_option_id_for_variant_row' => $deepest,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, int|null>
     */
    public static function levelFieldsForFormFill(array $data, int $categoryGroupId): array
    {
        $leaf = (int) ($data['category_value_id'] ?? 0);
        $parentCol = (int) ($data['category_parent_value_id'] ?? 0);

        $effective = $leaf > 0 ? $leaf : $parentCol;
        if ($effective <= 0 && $categoryGroupId > 0) {
            $rows = is_array($data['variant_options'] ?? null) ? $data['variant_options'] : [];
            foreach ($rows as $row) {
                if ((int) ($row['option_group_id'] ?? 0) !== $categoryGroupId) {
                    continue;
                }
                $first = collect($row['option_value_ids'] ?? [])
                    ->map(fn ($x) => (int) $x)
                    ->filter(fn (int $x) => $x > 0)
                    ->first();
                if ($first) {
                    $effective = $first;
                    break;
                }
            }
        }

        $out = [];
        if ($effective <= 0) {
            for ($i = 1; $i <= self::MAX_DEPTH; $i++) {
                $out['category_level_'.$i.'_id'] = null;
            }

            return $out;
        }

        $chain = self::ancestorsChainFromNode($effective, $categoryGroupId);
        $pad = array_values($chain);
        for ($i = 1; $i <= self::MAX_DEPTH; $i++) {
            $out['category_level_'.$i.'_id'] = $pad[$i - 1] ?? null;
        }

        return $out;
    }

    /**
     * З `category_level_*` у даних форми — колонки продукту/комплекту + рядок категорії в variant_options.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function applyCategoryLevelsToFormData(array $data, int $categoryGroupId): array
    {
        for ($i = 1; $i <= self::MAX_DEPTH; $i++) {
            unset($data['category_sub_value_id']);
        }

        if ($categoryGroupId <= 0) {
            for ($i = 1; $i <= self::MAX_DEPTH; $i++) {
                unset($data['category_level_'.$i.'_id']);
            }

            return $data;
        }

        $levels = [];
        for ($i = 1; $i <= self::MAX_DEPTH; $i++) {
            $key = 'category_level_'.$i.'_id';
            $levels[$i] = isset($data[$key]) ? (int) $data[$key] : 0;
            unset($data[$key]);
        }

        if ($levels[1] <= 0) {
            throw ValidationException::withMessages([
                'category_level_1_id' => 'Оберіть категорію (рівень 1).',
            ]);
        }

        try {
            $resolved = self::resolveStoredCategoryColumns($levels, $categoryGroupId);
        } catch (\InvalidArgumentException $e) {
            throw ValidationException::withMessages([
                'category_level_1_id' => $e->getMessage(),
            ]);
        }

        $data['category_parent_value_id'] = $resolved['category_parent_value_id'];
        $data['category_value_id'] = $resolved['category_value_id'];

        $optId = $resolved['category_option_id_for_variant_row'];
        $rows = is_array($data['variant_options'] ?? null) ? $data['variant_options'] : [];
        $rows = array_values(array_filter($rows, fn ($row): bool => (int) ($row['option_group_id'] ?? 0) !== $categoryGroupId));
        array_unshift($rows, [
            'option_group_id' => $categoryGroupId,
            'option_value_ids' => [$optId],
        ]);
        $data['variant_options'] = $rows;

        return $data;
    }

    /**
     * Для repeater опцій: синтетичні category_parent_value_id / category_value_id з рівнів форми.
     *
     * @param  array<int, int>  $levels
     */
    public static function syntheticListingCategoryColumns(array $levels, int $categoryGroupId): array
    {
        $deepest = 0;
        for ($i = self::MAX_DEPTH; $i >= 1; $i--) {
            if (($levels[$i] ?? 0) > 0) {
                $deepest = $levels[$i];
                break;
            }
        }

        if ($deepest <= 0 || $categoryGroupId <= 0) {
            return ['category_parent_value_id' => null, 'category_value_id' => null];
        }

        $root = self::rootIdFromNode($deepest, $categoryGroupId);

        return [
            'category_parent_value_id' => $root > 0 ? $root : null,
            'category_value_id' => $deepest !== $root ? $deepest : null,
        ];
    }
}
