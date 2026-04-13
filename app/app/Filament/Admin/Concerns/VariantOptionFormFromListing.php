<?php

namespace App\Filament\Admin\Concerns;

use App\Models\OptionGroup;
use App\Models\OptionValue;
use App\Support\VariantOptionsAllowlist;

/**
 * Групи та значення в формі варіанта: порядок і підказки з картки + повний перелік груп зі scope довідника.
 */
trait VariantOptionFormFromListing
{
    abstract protected function productTypeScopeKeysForListing(): array;

    protected function systemCategoryGroupId(): int
    {
        return OptionGroup::systemCategoryGroupIdForCatalog();
    }

    /**
     * Чи в картці товару є хоча б один рядок опції (крім категорії).
     */
    protected function listingHasProductOptionRows(): bool
    {
        $cat = $this->systemCategoryGroupId();

        foreach ($this->getOwnerRecord()->variant_options ?? [] as $row) {
            $gid = (int) ($row['option_group_id'] ?? 0);
            if ($gid > 0 && $gid !== $cat) {
                return true;
            }
        }

        return false;
    }

    /**
     * Групи в порядку картки товару (не «категорія»; неактивні з позначкою в підписі).
     *
     * @return array<int, string>
     */
    protected function getGroupOptionsFromListingOrdered(): array
    {
        $cat = $this->systemCategoryGroupId();
        $rows = $this->getOwnerRecord()->variant_options ?? [];

        // Порядок першої появи групи + об’єднані id з усіх рядків (дублікати repeater).
        $order = [];
        $mergedValueIds = [];
        foreach ($rows as $row) {
            $gid = (int) ($row['option_group_id'] ?? 0);
            if ($gid <= 0 || $gid === $cat) {
                continue;
            }
            if (! isset($order[$gid])) {
                $order[$gid] = count($order);
            }
            $ids = collect($row['option_value_ids'] ?? [])
                ->map(fn ($id) => (int) $id)
                ->filter(fn (int $id) => $id > 0)
                ->all();
            $mergedValueIds[$gid] = array_values(array_unique(array_merge($mergedValueIds[$gid] ?? [], $ids)));
        }

        if ($order === []) {
            return [];
        }

        asort($order);
        $result = [];

        foreach (array_keys($order) as $gid) {
            $group = OptionGroup::query()
                ->whereKey($gid)
                ->where('slug', '!=', 'category')
                ->first(['id', 'name', 'is_active']);

            if (! $group) {
                continue;
            }

            $valueCount = count($mergedValueIds[$gid] ?? []);

            $label = $group->name;
            if (! $group->is_active) {
                $label .= ' (неактивна група)';
            }
            if ($valueCount === 0) {
                $label .= ' — відмітьте значення в «Опціях товару»';
            }

            $result[$group->id] = $label;
        }

        return $result;
    }

    /**
     * Fallback: довідник за scope + підлиття груп з картки (старий режим без рядків опцій).
     *
     * @return array<int, string>
     */
    protected function getGroupOptionsFromScopeFallback(): array
    {
        $scopeKeys = $this->productTypeScopeKeysForListing();

        $result = OptionGroup::query()
            ->whereIn('product_type', $scopeKeys)
            ->where('slug', '!=', 'category')
            ->where('is_active', true)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->mapWithKeys(fn ($name, $id): array => [(int) $id => $name])
            ->all();

        foreach ($this->getOwnerRecord()->variant_options ?? [] as $group) {
            $groupId = (int) ($group['option_group_id'] ?? 0);

            if ($groupId <= 0) {
                continue;
            }

            $optionGroup = OptionGroup::query()
                ->whereKey($groupId)
                ->where('slug', '!=', 'category')
                ->first(['id', 'name']);

            if ($optionGroup) {
                $result[$optionGroup->id] = $optionGroup->name;
            }
        }

        asort($result, SORT_NATURAL | SORT_FLAG_CASE);

        return $result;
    }

    /**
     * @return array<int, string>
     */
    protected function getGroupOptions(): array
    {
        $fromScope = $this->getGroupOptionsFromScopeFallback();

        if (! $this->listingHasProductOptionRows()) {
            return $fromScope;
        }

        $fromListing = $this->getGroupOptionsFromListingOrdered();
        if ($fromListing === []) {
            return $fromScope;
        }

        // Спочатку групи з картки (порядок як на сайті + підказки), далі всі інші з scope —
        // щоб варіант міг жити і своїми власними осями, навіть якщо вони не винесені в картку товару.
        $merged = $fromListing;
        foreach ($fromScope as $id => $label) {
            if (! isset($merged[$id])) {
                $merged[$id] = $label;
            }
        }

        return $merged;
    }

    /**
     * @return array<int, string>
     */
    protected function getValueOptionsByGroup(int $groupId): array
    {
        $groups = $this->getOwnerRecord()->variant_options ?? [];

        // Усі рядки картки з цією групою (не лише перший — інакше порожній рядок «перемагає» і стать/колір зникають).
        $allowedIds = [];
        foreach ($groups as $group) {
            if (((int) ($group['option_group_id'] ?? 0)) !== $groupId) {
                continue;
            }

            $chunk = collect($group['option_value_ids'] ?? [])
                ->map(fn ($id) => (int) $id)
                ->filter(fn (int $id) => $id > 0)
                ->values()
                ->all();
            $allowedIds = array_values(array_unique(array_merge($allowedIds, $chunk)));
        }

        if ($this->listingHasProductOptionRows()) {
            if ($allowedIds === []) {
                return OptionValue::query()
                    ->where('option_group_id', $groupId)
                    ->where('is_active', true)
                    ->orderBy('sort_order')
                    ->pluck('name', 'id')
                    ->all();
            }

            return OptionValue::query()
                ->where('option_group_id', $groupId)
                ->whereIn('id', $allowedIds)
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->pluck('name', 'id')
                ->all();
        }

        if ($allowedIds === []) {
            return OptionValue::query()
                ->where('option_group_id', $groupId)
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->pluck('name', 'id')
                ->all();
        }

        return OptionValue::query()
            ->where('option_group_id', $groupId)
            ->whereIn('id', $allowedIds)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->pluck('name', 'id')
            ->all();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function hydrateVariantOptionsForForm(array $data): array
    {
        $flat = is_array($data['options'] ?? null) ? $data['options'] : [];
        $flat = VariantOptionsAllowlist::filterPairs($flat, $this->getOwnerRecord(), $this->systemCategoryGroupId());
        $data['options'] = $this->denormalizeVariantOptionsRepeaterForForm($flat);

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function dehydrateVariantOptionsForSave(array $data): array
    {
        $data['options'] = VariantOptionsAllowlist::filterPairs(
            $this->normalizeVariantOptionsRepeaterForSave($data['options'] ?? []),
            $this->getOwnerRecord(),
            $this->systemCategoryGroupId()
        );

        return $data;
    }
}
