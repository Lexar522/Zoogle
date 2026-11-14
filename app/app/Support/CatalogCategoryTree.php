<?php

namespace App\Support;

use App\Models\OptionValue;
use Illuminate\Validation\ValidationException;

final class CatalogCategoryTree
{
    public const MAX_DEPTH = 5;

    /** Максимальна глибина дерева категорій у навігації вітрини (рівні від кореня, включно). */
    public const NAV_MAX_DEPTH = 3;

    /**
     * @return list<int> ids from root to node (inclusive)
     */
    public static function ancestorsChainFromNode(int $valueId, int $categoryGroupId): array
    {
        if ($valueId <= 0 || $categoryGroupId <= 0) {
            return [];
        }

        // Одне читання групи з пам'яті запиту замість запиту на кожен рівень (сторінка товару робила їх по 6).
        $byId = StorefrontOptionCatalog::valuesInGroup($categoryGroupId)->keyBy(fn (OptionValue $value): int => (int) $value->id);

        $rev = [];
        $current = $valueId;
        for ($g = 0; $g < 32 && $current > 0; $g++) {
            $row = $byId->get($current);
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
     * @param  array<int, array{id: int, name: string, children: array<int, array<string, mixed>>}>  $tree
     * @return array{id: int, name: string, children: array<int, array<string, mixed>>}|null
     */
    public static function findNodeInTree(array $tree, int $targetId): ?array
    {
        if ($targetId <= 0) {
            return null;
        }

        foreach ($tree as $node) {
            $nodeId = (int) ($node['id'] ?? 0);
            if ($nodeId <= 0) {
                continue;
            }

            if ($nodeId === $targetId) {
                return $node;
            }

            $children = $node['children'] ?? [];
            $found = self::findNodeInTree(is_array($children) ? $children : [], $targetId);
            if ($found !== null) {
                return $found;
            }
        }

        return null;
    }

    /**
     * @param  array<int, array{id: int, name: string, children: array<int, array<string, mixed>>}>  $tree
     * @return list<array{id: int, name: string}>
     */
    public static function findPathInTree(array $tree, int $targetId, array $trail = []): array
    {
        if ($targetId <= 0) {
            return [];
        }

        foreach ($tree as $node) {
            $nodeId = (int) ($node['id'] ?? 0);
            if ($nodeId <= 0) {
                continue;
            }

            $currentTrail = array_merge($trail, [[
                'id' => $nodeId,
                'name' => (string) ($node['name'] ?? ''),
            ]]);

            if ($nodeId === $targetId) {
                return $currentTrail;
            }

            $children = $node['children'] ?? [];
            $found = self::findPathInTree(is_array($children) ? $children : [], $targetId, $currentTrail);
            if ($found !== []) {
                return $found;
            }
        }

        return [];
    }

    /**
     * Дерево для навігації вітрини: до {@see NAV_MAX_DEPTH} рівнів від кореня.
     *
     * @param  array<int, array{id: int, name: string, children: array<int, array<string, mixed>>}>  $fullTree
     * @return list<array{id: int, name: string, children: array<int, array<string, mixed>>}>
     */
    public static function buildNavigationTree(array $fullTree): array
    {
        return self::truncateTreeForNavigation($fullTree, 1);
    }

    /**
     * @param  array<int, array{id: int, name: string, children: array<int, array<string, mixed>>}>  $nodes
     * @return list<array{id: int, name: string, children: array<int, array<string, mixed>>}>
     */
    private static function truncateTreeForNavigation(array $nodes, int $depth = 1): array
    {
        return array_values(array_map(function (array $node) use ($depth): array {
            $children = is_array($node['children'] ?? null) ? $node['children'] : [];
            $result = [
                'id' => (int) ($node['id'] ?? 0),
                'name' => (string) ($node['name'] ?? ''),
                'children' => [],
            ];

            if ($depth < self::NAV_MAX_DEPTH && $children !== []) {
                $result['children'] = self::truncateTreeForNavigation($children, $depth + 1);
            }

            return $result;
        }, $nodes));
    }

    /**
     * Групи для чекбокс-фільтра: лише наступний рівень нижче обраної категорії.
     *
     * @param  array<int, array{id: int, name: string, children: array<int, array<string, mixed>>}>  $tree
     * @return list<array{parentId: int, parentName: string, depth: int, items: list<array{id: int, name: string}>}>
     */
    public static function buildSubcategoryFilterGroups(array $tree, int $selectedId): array
    {
        return self::resolveSubcategoryFilterGroups($tree, $selectedId);
    }

    /**
     * Групи фільтра: прямі діти обраної категорії або прямі діти застосованих підкатегорій.
     *
     * @param  array<int, array{id: int, name: string, children: array<int, array<string, mixed>>}>  $tree
     * @param  list<int>  $appliedSubcategoryIds
     * @return list<array{parentId: int, parentName: string, depth: int, items: list<array{id: int, name: string}>}>
     */
    public static function resolveSubcategoryFilterGroups(
        array $tree,
        int $selectedId,
        array $appliedSubcategoryIds = [],
    ): array {
        if ($selectedId <= 0) {
            return [];
        }

        if (self::findNodeInTree($tree, $selectedId) === null) {
            return [];
        }

        $anchorIds = $appliedSubcategoryIds !== []
            ? array_values(array_unique(array_filter(
                array_map('intval', $appliedSubcategoryIds),
                fn (int $id): bool => $id > 0,
            )))
            : [$selectedId];

        $groups = [];
        foreach ($anchorIds as $anchorId) {
            if ($appliedSubcategoryIds !== [] && ! self::isDescendantOfCategory($tree, $selectedId, $anchorId)) {
                continue;
            }

            $node = self::findNodeInTree($tree, $anchorId);
            if ($node === null) {
                continue;
            }

            $group = self::directChildFilterGroup($node);
            if ($group !== null) {
                $groups[] = $group;
            }
        }

        return $groups;
    }

    /**
     * @param  array<int, array{id: int, name: string, children: array<int, array<string, mixed>>}>  $tree
     */
    public static function isDescendantOfCategory(array $tree, int $categoryId, int $nodeId): bool
    {
        if ($categoryId <= 0 || $nodeId <= 0 || $categoryId === $nodeId) {
            return false;
        }

        $categoryNode = self::findNodeInTree($tree, $categoryId);
        if ($categoryNode === null) {
            return false;
        }

        return in_array($nodeId, self::collectDescendantIds($categoryNode), true);
    }

    /**
     * @param  array{id: int, name: string, children: array<int, array<string, mixed>>}  $node
     * @return list<int>
     */
    public static function collectDescendantIds(array $node): array
    {
        $ids = [];

        foreach ($node['children'] ?? [] as $child) {
            if (! is_array($child)) {
                continue;
            }

            $childId = (int) ($child['id'] ?? 0);
            if ($childId <= 0) {
                continue;
            }

            $ids[] = $childId;
            $ids = array_merge($ids, self::collectDescendantIds($child));
        }

        return $ids;
    }

    /**
     * Для фільтра каталогу: кожен обраний id + усі нащадки в дереві (товар на 4–5 рівні
     * має потрапляти в видачу при виборі батьківської підкатегорії 2–3 рівня).
     *
     * @param  array<int, array{id: int, name: string, children: array<int, array<string, mixed>>}>  $tree
     * @param  list<int>  $anchorIds
     * @return list<int>
     */
    public static function expandCategoryFilterIdsWithDescendants(array $tree, array $anchorIds): array
    {
        $ids = [];

        foreach ($anchorIds as $anchorId) {
            $id = (int) $anchorId;
            if ($id <= 0) {
                continue;
            }

            $ids[] = $id;

            $node = self::findNodeInTree($tree, $id);
            if ($node !== null) {
                $ids = array_merge($ids, self::collectDescendantIds($node));
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * @param  array{id: int, name: string, children: array<int, array<string, mixed>>}  $node
     * @return array{parentId: int, parentName: string, depth: int, items: list<array{id: int, name: string}>}|null
     */
    private static function directChildFilterGroup(array $node, int $depth = 0): ?array
    {
        $children = $node['children'] ?? [];
        if (! is_array($children) || $children === []) {
            return null;
        }

        $parentId = (int) ($node['id'] ?? 0);
        if ($parentId <= 0) {
            return null;
        }

        $items = [];
        foreach ($children as $child) {
            if (! is_array($child)) {
                continue;
            }

            $itemId = (int) ($child['id'] ?? 0);
            if ($itemId <= 0) {
                continue;
            }

            $items[] = [
                'id' => $itemId,
                'name' => (string) ($child['name'] ?? ''),
            ];
        }

        if ($items === []) {
            return null;
        }

        return [
            'parentId' => $parentId,
            'parentName' => (string) ($node['name'] ?? ''),
            'depth' => $depth,
            'items' => $items,
        ];
    }

    /**
     * Залишає лише пункти з дозволеного піддерева поточної категорії.
     *
     * @param  list<array{parentId: int, parentName: string, depth?: int, items: list<array{id: int, name: string}>}>  $groups
     * @param  list<int>  $allowedIds
     * @return list<array{parentId: int, parentName: string, items: list<array{id: int, name: string}>}>
     */
    public static function pruneSubcategoryFilterGroups(array $groups, array $allowedIds): array
    {
        if ($groups === [] || $allowedIds === []) {
            return [];
        }

        $allowedSet = array_flip(array_map('intval', $allowedIds));
        $pruned = [];

        foreach ($groups as $group) {
            $items = [];
            foreach ($group['items'] ?? [] as $item) {
                $itemId = (int) ($item['id'] ?? 0);
                if ($itemId > 0 && isset($allowedSet[$itemId])) {
                    $items[] = $item;
                }
            }

            if ($items === []) {
                continue;
            }

            $pruned[] = [
                'parentId' => (int) ($group['parentId'] ?? 0),
                'parentName' => (string) ($group['parentName'] ?? ''),
                'depth' => (int) ($group['depth'] ?? 0),
                'items' => $items,
            ];
        }

        return $pruned;
    }

    /**
     * @param  list<array{parentId: int, parentName: string, depth?: int, items: list<array{id: int, name: string}>}>  $groups
     * @return list<int>
     */
    public static function subcategoryFilterGroupItemIds(array $groups): array
    {
        $ids = [];
        foreach ($groups as $group) {
            foreach ($group['items'] ?? [] as $item) {
                $id = (int) ($item['id'] ?? 0);
                if ($id > 0) {
                    $ids[] = $id;
                }
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * @param  array<int, array{id: int, name: string, children: array<int, array<string, mixed>>}>  $tree
     * @param  list<int>  $selectedIds
     * @return list<string>
     */
    public static function subcategoryFilterSelectedLabels(array $tree, array $selectedIds): array
    {
        $labels = [];

        foreach ($selectedIds as $selectedId) {
            $id = (int) $selectedId;
            if ($id <= 0) {
                continue;
            }

            $node = self::findNodeInTree($tree, $id);
            if ($node === null) {
                continue;
            }

            $name = trim((string) ($node['name'] ?? ''));
            if ($name !== '') {
                $labels[] = $name;
            }
        }

        return $labels;
    }

    /**
     * @param  array<int, array{id: int, name: string, children: array<int, array<string, mixed>>}>  $categoryTree
     * @return array{
     *   headerFeaturedLeft: ?array{id: int, name: string, children: array<int, array<string, mixed>>},
     *   headerFeaturedRight: ?array{id: int, name: string, children: array<int, array<string, mixed>>},
     *   headerRestCategories: list<array{id: int, name: string, children: array<int, array<string, mixed>>}>
     * }
     */
    public static function partitionForHeaderHub(array $categoryTree, ?int $leftId, ?int $rightId): array
    {
        if ($leftId === null && $rightId === null) {
            return [
                'headerFeaturedLeft' => $categoryTree[0] ?? null,
                'headerFeaturedRight' => $categoryTree[1] ?? null,
                'headerRestCategories' => array_values(array_slice($categoryTree, 2)),
            ];
        }

        $byId = collect($categoryTree)->keyBy('id');

        $leftNode = ($leftId ?? 0) > 0 ? $byId->get($leftId) : null;
        $rightNode = ($rightId ?? 0) > 0 ? $byId->get($rightId) : null;
        $leftNode = is_array($leftNode) ? $leftNode : null;
        $rightNode = is_array($rightNode) ? $rightNode : null;

        $featuredIdSet = array_filter([
            $leftNode['id'] ?? null,
            $rightNode['id'] ?? null,
        ]);
        $featuredIdSet = array_flip(array_map('intval', $featuredIdSet));

        $rest = array_values(array_filter(
            $categoryTree,
            fn (array $node): bool => ! isset($featuredIdSet[(int) $node['id']])
        ));

        return [
            'headerFeaturedLeft' => $leftNode,
            'headerFeaturedRight' => $rightNode,
            'headerRestCategories' => $rest,
        ];
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

    /**
     * Підпис шляху «Корінь → … → Вузол» для select додаткових категорій.
     */
    public static function pathLabelForValueId(int $valueId, int $categoryGroupId, string $separator = ' → '): string
    {
        if ($valueId <= 0 || $categoryGroupId <= 0) {
            return '';
        }

        $chain = self::ancestorsChainFromNode($valueId, $categoryGroupId);
        if ($chain === []) {
            return '';
        }

        $names = OptionValue::query()
            ->where('option_group_id', $categoryGroupId)
            ->whereIn('id', $chain)
            ->get(['id', 'name', 'is_active'])
            ->keyBy('id');

        $parts = [];
        foreach ($chain as $id) {
            $row = $names->get($id);
            if ($row === null) {
                continue;
            }
            $label = (string) $row->name;
            if (! $row->is_active) {
                $label .= ' (неактивна)';
            }
            $parts[] = $label;
        }

        return implode($separator, $parts);
    }

    /**
     * Плоский список усіх вузлів категорії: id => «Шлях → …».
     *
     * @return array<int, string>
     */
    public static function flatPathOptionsForSelect(int $categoryGroupId): array
    {
        if ($categoryGroupId <= 0) {
            return [];
        }

        $values = OptionValue::query()
            ->where('option_group_id', $categoryGroupId)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'parent_id', 'is_active']);

        if ($values->isEmpty()) {
            return [];
        }

        $byId = $values->keyBy('id');
        $options = [];

        foreach ($values as $value) {
            $parts = [];
            $current = $value;
            $guard = 0;
            while ($current !== null && $guard < 32) {
                $label = (string) $current->name;
                if (! $current->is_active) {
                    $label .= ' (неактивна)';
                }
                array_unshift($parts, $label);
                $parentId = (int) ($current->parent_id ?? 0);
                $current = $parentId > 0 ? $byId->get($parentId) : null;
                $guard++;
            }

            if ($parts !== []) {
                $options[(int) $value->id] = implode(' → ', $parts);
            }
        }

        asort($options, SORT_NATURAL | SORT_FLAG_CASE);

        return $options;
    }

    /**
     * Найглибший обраний рівень каскаду (для статей: вузол «напрямку»).
     *
     * @param  array<string, mixed>  $data
     */
    public static function deepestLevelIdFromFormData(array $data): int
    {
        $deepest = 0;
        for ($i = 1; $i <= self::MAX_DEPTH; $i++) {
            $id = (int) ($data['category_level_'.$i.'_id'] ?? 0);
            if ($id > 0) {
                $deepest = $id;
            }
        }

        return $deepest;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function stripLevelFieldsFromFormData(array $data): array
    {
        for ($i = 1; $i <= self::MAX_DEPTH; $i++) {
            unset($data['category_level_'.$i.'_id']);
        }

        return $data;
    }

    /**
     * Нащадки вузла в БД (без самого вузла).
     *
     * @return list<int>
     */
    public static function descendantIdsFromDatabase(int $valueId, int $categoryGroupId): array
    {
        if ($valueId <= 0 || $categoryGroupId <= 0) {
            return [];
        }

        $ids = [];
        $frontier = [$valueId];
        $guard = 0;
        while ($frontier !== [] && $guard < 32) {
            $children = OptionValue::query()
                ->where('option_group_id', $categoryGroupId)
                ->whereIn('parent_id', $frontier)
                ->pluck('id')
                ->map(fn ($id): int => (int) $id)
                ->all();
            $ids = array_merge($ids, $children);
            $frontier = $children;
            $guard++;
        }

        return array_values(array_unique($ids));
    }
}
