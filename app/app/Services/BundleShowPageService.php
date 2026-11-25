<?php

namespace App\Services;

use App\Models\Bundle;
use App\Models\OptionGroup;
use App\Models\OptionValue;
use App\Support\ListingOptionValuePrices;
use App\Support\OptionLabel;
use App\Support\StorefrontOptionCatalog;

class BundleShowPageService
{
    /**
     * @return list<array{
     *   id: int,
     *   name: string,
     *   selection_mode: string,
     *   value_type: string,
     *   values: list<array{id: int, name: string, price: float|null, color_hex: mixed, swatch_image: ?string}>
     * }>
     */
    public function buildOptionDisplayBlocks(Bundle $bundle): array
    {
        $categoryGroupId = OptionGroup::systemCategoryGroupIdForCatalog();
        $variantRows = is_array($bundle->variant_options ?? null) ? $bundle->variant_options : [];

        $blocks = [];
        $seenGroups = [];

        foreach ($variantRows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $groupId = (int) ($row['option_group_id'] ?? 0);
            if ($groupId <= 0 || ($categoryGroupId > 0 && $groupId === $categoryGroupId)) {
                continue;
            }

            if (isset($seenGroups[$groupId])) {
                continue;
            }

            $allowedIds = collect($row['option_value_ids'] ?? [])
                ->map(fn ($id) => (int) $id)
                ->filter(fn (int $id) => $id > 0)
                ->values()
                ->all();

            if ($allowedIds === []) {
                continue;
            }

            $seenGroups[$groupId] = true;

            $block = $this->makeOptionBlock($groupId, $allowedIds, $variantRows, $row);
            if ($block !== null) {
                $blocks[] = $block;
            }
        }

        return $blocks;
    }

    /**
     * @param  list<array<string, mixed>>  $variantRows
     * @param  array<string, mixed>  $sourceRow
     * @return array{
     *   id: int,
     *   name: string,
     *   selection_mode: string,
     *   value_type: string,
     *   values: list<array{id: int, name: string, price: float|null, color_hex: mixed, swatch_image: ?string}>
     * }|null
     */
    private function makeOptionBlock(int $groupId, array $allowedValueIds, array $variantRows, array $sourceRow): ?array
    {
        if ($groupId <= 0 || $allowedValueIds === []) {
            return null;
        }

        $group = StorefrontOptionCatalog::group($groupId);
        if (! $group || ! $group->is_active || $group->slug === 'category') {
            return null;
        }

        $values = StorefrontOptionCatalog::valuesIn($groupId, $allowedValueIds, activeOnly: true);

        if ($values->isEmpty()) {
            return null;
        }

        $rowValueType = (string) ($sourceRow['option_value_type'] ?? '');
        $catalogValueType = in_array($rowValueType, ['text', 'color'], true)
            ? $rowValueType
            : ((($group->value_type ?? 'text') === 'color') ? 'color' : 'text');

        $anyColorHex = $values->contains(fn (OptionValue $value): bool => filled($value->color_hex));
        if ($catalogValueType !== 'color' && $anyColorHex) {
            $catalogValueType = 'color';
        }

        $isColorBlock = $catalogValueType === 'color';
        $selectionMode = $isColorBlock
            ? 'single'
            : (string) ($group->selection_mode ?? 'single');

        return [
            'id' => (int) $group->id,
            'name' => trim((string) $group->name),
            'selection_mode' => $selectionMode,
            'value_type' => $catalogValueType,
            'exclusivity_key' => \App\Support\OptionGroupExclusivity::keyFromName((string) $group->name),
            'values' => $values->map(function (OptionValue $value) use ($variantRows, $groupId, $sourceRow, $isColorBlock): array {
                $valueId = (int) $value->id;
                $addon = ListingOptionValuePrices::priceAddonForValue($variantRows, $groupId, $valueId);
                $swatchImage = $isColorBlock && ! filled($value->color_hex)
                    ? $this->firstSwatchPhotoFromRow($sourceRow, $valueId)
                    : null;

                return [
                    'id' => $valueId,
                    'name' => trim((string) $value->name),
                    'price' => $addon > 0 ? $addon : null,
                    'color_hex' => $value->color_hex,
                    'swatch_image' => $swatchImage,
                ];
            })->values()->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function firstSwatchPhotoFromRow(array $row, int $valueId): ?string
    {
        foreach ($row['value_photos'] ?? [] as $photoRow) {
            if (! is_array($photoRow) || (int) ($photoRow['option_value_id'] ?? 0) !== $valueId) {
                continue;
            }

            foreach ($photoRow['photos'] ?? [] as $path) {
                if (is_string($path) && trim($path) !== '') {
                    return ltrim(trim($path), '/');
                }
            }
        }

        return null;
    }

    /**
     * @param  list<int>  $selectedOptionValueIds
     * @return list<int>
     */
    public function sanitizeSelectionForBundle(Bundle $bundle, array $selectedOptionValueIds): array
    {
        if ($selectedOptionValueIds === []) {
            return [];
        }

        $allowed = [];
        foreach ($this->buildOptionDisplayBlocks($bundle) as $block) {
            foreach ($block['values'] as $row) {
                $id = (int) ($row['id'] ?? 0);
                if ($id > 0) {
                    $allowed[$id] = true;
                }
            }
        }

        $safeIds = array_values(array_filter(
            $selectedOptionValueIds,
            fn (int $id): bool => isset($allowed[$id])
        ));
        sort($safeIds);

        return $safeIds;
    }

    /**
     * @param  list<int>  $selectedOptionValueIds
     */
    public function priceAddonForSelection(Bundle $bundle, array $selectedOptionValueIds): float
    {
        $safeIds = $this->sanitizeSelectionForBundle($bundle, $selectedOptionValueIds);
        if ($safeIds === []) {
            return 0.0;
        }

        $priceById = [];
        foreach ($this->buildOptionDisplayBlocks($bundle) as $block) {
            foreach ($block['values'] as $row) {
                $id = (int) ($row['id'] ?? 0);
                if ($id > 0) {
                    $priceById[$id] = (float) ($row['price'] ?? 0);
                }
            }
        }

        $sum = 0.0;
        foreach ($safeIds as $id) {
            $sum += (float) ($priceById[$id] ?? 0.0);
        }

        return round($sum, 2);
    }

    /**
     * @param  list<int>  $selectedOptionValueIds
     * @return list<array{label: string, group_name: string, value_name: string, value_type: string, color_hex: ?string, swatch_image: ?string}>
     */
    public function selectionDisplayItems(Bundle $bundle, array $selectedOptionValueIds): array
    {
        $safeIds = $this->sanitizeSelectionForBundle($bundle, $selectedOptionValueIds);
        if ($safeIds === []) {
            return [];
        }

        $byValueId = [];
        foreach ($this->buildOptionDisplayBlocks($bundle) as $block) {
            foreach ($block['values'] as $value) {
                $byValueId[(int) $value['id']] = [
                    'group_name' => (string) ($block['name'] ?? ''),
                    'value_name' => trim((string) ($value['name'] ?? '')),
                    'value_type' => (string) ($block['value_type'] ?? 'text'),
                    'color_hex' => filled($value['color_hex'] ?? null) ? (string) $value['color_hex'] : null,
                    'swatch_image' => filled($value['swatch_image'] ?? null) ? (string) $value['swatch_image'] : null,
                ];
            }
        }

        $items = [];
        foreach ($safeIds as $id) {
            $row = $byValueId[$id] ?? null;
            if ($row === null) {
                continue;
            }

            $label = OptionLabel::join((string) $row['group_name'], (string) $row['value_name']);
            $row['label'] = $label !== '' ? $label : $row['value_name'];
            $items[] = $row;
        }

        return $items;
    }

    /**
     * @param  list<int>  $selectedOptionValueIds
     */
    public function selectionIsCompleteForBundle(Bundle $bundle, array $selectedOptionValueIds): bool
    {
        $blocks = $this->buildOptionDisplayBlocks($bundle);
        if ($blocks === []) {
            return true;
        }

        $safeIds = $this->sanitizeSelectionForBundle($bundle, $selectedOptionValueIds);

        foreach ($blocks as $block) {
            $groupValueIds = array_map(
                fn (array $row): int => (int) ($row['id'] ?? 0),
                $block['values']
            );
            $selectedInGroup = array_values(array_intersect($safeIds, $groupValueIds));

            if (($block['selection_mode'] ?? 'single') === 'multiple') {
                if ($selectedInGroup === []) {
                    return false;
                }

                continue;
            }

            if (count($selectedInGroup) !== 1) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  list<int>  $optionValueIds
     */
    public static function makeCartKey(int $bundleId, array $optionValueIds): string
    {
        if ($optionValueIds === []) {
            return 'bundle:'.$bundleId;
        }

        $ids = array_values(array_unique(array_map('intval', $optionValueIds)));
        sort($ids);

        return 'bundle:'.$bundleId.':'.implode(',', $ids);
    }
}
