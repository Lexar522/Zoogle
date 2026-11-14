<?php

namespace App\Support;

use App\Models\OptionValue;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Готове дерево категорій вітрини. Скидається, коли категорію або шапку змінюють в адмінці.
 */
final class StorefrontCategoryTreeCache
{
    private const VERSION_KEY = 'storefront.category-tree-version';

    /**
     * @return array{
     *     categoryValues: Collection<int, OptionValue>,
     *     categoryTree: array<int, mixed>,
     *     categoryNavigationTree: array<int, mixed>
     * }
     */
    public static function forGroup(int $groupId, bool $hasParentColumn): array
    {
        if ($groupId <= 0) {
            return [
                'categoryValues' => collect(),
                'categoryTree' => [],
                'categoryNavigationTree' => [],
            ];
        }

        $version = (int) Cache::get(self::VERSION_KEY, 1);
        $key = 'storefront.category-tree.'.$version.'.'.$groupId.'.'.($hasParentColumn ? '1' : '0');

        /** @var array{values: list<array{id: int, name: string, parent_id: int|null}>, categoryTree: array<int, mixed>, categoryNavigationTree: array<int, mixed>} $payload */
        $payload = Cache::remember($key, now()->addDay(), function () use ($groupId, $hasParentColumn): array {
            return self::build($groupId, $hasParentColumn);
        });

        $categoryValues = collect($payload['values'])->map(function (array $row): OptionValue {
            $value = new OptionValue;
            $value->forceFill([
                'id' => $row['id'],
                'name' => $row['name'],
                'parent_id' => $row['parent_id'],
            ]);
            $value->exists = true;

            return $value;
        });

        return [
            'categoryValues' => $categoryValues,
            'categoryTree' => $payload['categoryTree'],
            'categoryNavigationTree' => $payload['categoryNavigationTree'],
        ];
    }

    public static function bump(): void
    {
        $version = (int) Cache::get(self::VERSION_KEY, 1);
        Cache::forever(self::VERSION_KEY, $version + 1);
    }

    /**
     * @return array{values: list<array{id: int, name: string, parent_id: int|null}>, categoryTree: array<int, mixed>, categoryNavigationTree: array<int, mixed>}
     */
    private static function build(int $groupId, bool $hasParentColumn): array
    {
        $columns = ['id', 'name'];
        if ($hasParentColumn) {
            $columns[] = 'parent_id';
        }

        $categoryValues = OptionValue::query()
            ->where('option_group_id', $groupId)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get($columns);

        $categoryTree = $hasParentColumn
            ? (function () use ($categoryValues): array {
                $byParent = $categoryValues->groupBy(fn (OptionValue $value): int => (int) ($value->parent_id ?? 0));

                $buildNode = function (OptionValue $node) use (&$buildNode, $byParent): array {
                    $children = $byParent
                        ->get((int) $node->id, collect())
                        ->map(fn (OptionValue $child): array => $buildNode($child))
                        ->values()
                        ->all();

                    return [
                        'id' => (int) $node->id,
                        'name' => (string) $node->name,
                        'children' => $children,
                    ];
                };

                return $byParent
                    ->get(0, collect())
                    ->map(fn (OptionValue $root): array => $buildNode($root))
                    ->values()
                    ->all();
            })()
            : $categoryValues
                ->map(fn (OptionValue $parent): array => [
                    'id' => (int) $parent->id,
                    'name' => (string) $parent->name,
                    'children' => [],
                ])
                ->values()
                ->all();

        return [
            'values' => $categoryValues->map(fn (OptionValue $value): array => [
                'id' => (int) $value->id,
                'name' => (string) $value->name,
                'parent_id' => $value->parent_id !== null ? (int) $value->parent_id : null,
            ])->values()->all(),
            'categoryTree' => $categoryTree,
            'categoryNavigationTree' => CatalogCategoryTree::buildNavigationTree($categoryTree),
        ];
    }
}
