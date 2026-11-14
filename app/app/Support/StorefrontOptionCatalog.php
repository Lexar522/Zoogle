<?php

namespace App\Support;

use App\Models\OptionGroup;
use App\Models\OptionValue;
use Illuminate\Support\Collection;

/**
 * Групи й значення опцій з пам'яті запиту. Кожна картка товару, блок опцій сторінки товару і комплект
 * інакше окремо питають ті самі кілька груп і значень.
 */
final class StorefrontOptionCatalog
{
    /**
     * @return Collection<int, OptionGroup>
     */
    public static function groups(): Collection
    {
        return StorefrontMemo::remember('option-catalog.groups', fn (): Collection => OptionGroup::query()
            ->get(['id', 'name', 'slug', 'product_type', 'selection_mode', 'value_type', 'is_active'])
            ->keyBy(fn (OptionGroup $group): int => (int) $group->id));
    }

    public static function group(int $id): ?OptionGroup
    {
        return $id > 0 ? self::groups()->get($id) : null;
    }

    /**
     * @param  list<int>  $ids
     * @return Collection<int, OptionGroup>
     */
    public static function groupsByIds(array $ids): Collection
    {
        $wanted = array_flip(array_map('intval', $ids));

        return self::groups()->filter(fn (OptionGroup $group): bool => isset($wanted[(int) $group->id]));
    }

    /**
     * Усі значення групи в порядку сортування.
     *
     * @return Collection<int, OptionValue>
     */
    public static function valuesInGroup(int $groupId): Collection
    {
        if ($groupId <= 0) {
            return collect();
        }

        return StorefrontMemo::remember('option-catalog.values.'.$groupId, fn (): Collection => OptionValue::query()
            ->where('option_group_id', $groupId)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['id', 'option_group_id', 'parent_id', 'name', 'color_hex', 'sort_order', 'is_active']));
    }

    /**
     * @param  list<int>  $ids
     * @return Collection<int, OptionValue>
     */
    public static function valuesIn(int $groupId, array $ids, bool $activeOnly = false): Collection
    {
        $wanted = array_flip(array_map('intval', $ids));

        return self::valuesInGroup($groupId)
            ->filter(fn (OptionValue $value): bool => isset($wanted[(int) $value->id])
                && (! $activeOnly || (bool) $value->is_active))
            ->values();
    }
}
