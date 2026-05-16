<?php

namespace App\Services;

use App\Models\OptionGroup;
use App\Models\OptionValue;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Support\ListingOptionValuePrices;
use App\Support\VariantOptionsAllowlist;

class ListingOptionSelectionService
{
    /**
     * @param  list<int>  $selectedOptionValueIds
     * @return list<int>
     */
    public function normalizeOptionValueIds(mixed $selectedOptionValueIds): array
    {
        $decoded = [];
        if (is_string($selectedOptionValueIds) && trim($selectedOptionValueIds) !== '') {
            $parsed = json_decode($selectedOptionValueIds, true);
            if (is_array($parsed)) {
                $decoded = $parsed;
            }
        } elseif (is_array($selectedOptionValueIds)) {
            $decoded = $selectedOptionValueIds;
        }

        $out = [];
        foreach ($decoded as $id) {
            $n = (int) $id;
            if ($n > 0) {
                $out[$n] = true;
            }
        }

        $ids = array_keys($out);
        sort($ids);

        return $ids;
    }

    /**
     * @return array<int, array{
     *     id: int,
     *     name: string,
     *     selection_mode: string,
     *     value_type: string,
     *     affects_variant_matching: bool,
     *     exclusive_for_variant_id: int|null,
     *     exclusive_variant_value_count: int,
     *     values: list<array{
     *         id: int,
     *         name: string,
     *         price: float|null,
     *         color_hex: string|null,
     *         swatch_image: string|null
     *     }>
     * }>
     */
    public function storefrontOptionBlocks(Product $product): array
    {
        $categoryGroupId = OptionGroup::systemCategoryGroupIdForCatalog();
        $orderedGids = collect($product->variant_options ?? [])
            ->map(fn ($row): int => (int) ($row['option_group_id'] ?? 0))
            ->filter(fn (int $gid): bool => $gid > 0 && $gid !== $categoryGroupId)
            ->unique()
            ->values()
            ->all();

        if ($orderedGids === []) {
            return [];
        }

        $groups = OptionGroup::query()
            ->whereIn('id', $orderedGids)
            ->get(['id', 'name', 'slug', 'selection_mode', 'value_type', 'is_active'])
            ->keyBy('id');

        $blocks = [];
        foreach ($orderedGids as $gid) {
            $group = $groups->get($gid);
            if (! $group || (string) $group->slug === 'category' || ! (bool) $group->is_active) {
                continue;
            }

            $allowedIds = [];
            foreach ($product->variant_options ?? [] as $row) {
                if ((int) ($row['option_group_id'] ?? 0) !== $gid) {
                    continue;
                }
                foreach (($row['option_value_ids'] ?? []) as $id) {
                    $n = (int) $id;
                    if ($n > 0) {
                        $allowedIds[$n] = true;
                    }
                }
            }

            $valueIds = array_keys($allowedIds);
            sort($valueIds);
            if ($valueIds === []) {
                continue;
            }

            $values = OptionValue::query()
                ->where('option_group_id', $gid)
                ->whereIn('id', $valueIds)
                ->orderBy('sort_order')
                ->get(['id', 'name', 'color_hex'])
                ->map(fn (OptionValue $value): array => [
                    'id' => (int) $value->id,
                    'name' => $this->mtLine((string) $value->name),
                    'price' => ListingOptionValuePrices::priceAddonForValue(
                        is_array($product->variant_options ?? null) ? $product->variant_options : [],
                        $gid,
                        (int) $value->id,
                    ),
                    'color_hex' => filled($value->color_hex) ? (string) $value->color_hex : null,
                    'swatch_image' => $this->firstSwatchImagePath($product, $gid, (int) $value->id),
                ])
                ->values()
                ->all();

            if ($values === []) {
                continue;
            }

            $blocks[$gid] = [
                'id' => $gid,
                'name' => $this->mtLine((string) ($group->name ?? '')),
                'selection_mode' => (string) ($group->selection_mode ?? 'single'),
                'value_type' => (string) ($group->value_type ?? 'text'),
                'affects_variant_matching' => $this->optionGroupPinsAnyVisibleVariant($product, $gid, $categoryGroupId),
                'exclusive_for_variant_id' => $exclusiveVariantId = $this->exclusiveVariantIdForGroupIfSingle($product, $gid, $categoryGroupId),
                'exclusive_variant_value_count' => $exclusiveVariantId
                    ? $this->variantValueCountForGroup($product, $exclusiveVariantId, $gid, $categoryGroupId)
                    : 0,
                'values' => $values,
            ];
        }

        return $blocks;
    }

    /**
     * @param  list<int>  $selectedOptionValueIds
     * @return list<list<int>>
     */
    public function splitIntoCartLineOptionSets(Product $product, array $selectedOptionValueIds): array
    {
        $safeSelection = $this->sanitizeSelectionForProduct($product, $selectedOptionValueIds);
        if ($safeSelection === []) {
            return [[]];
        }

        $optionBlocks = $this->storefrontOptionBlocks($product);
        if ($optionBlocks === []) {
            return [$safeSelection];
        }

        $blockByValueId = $this->blockByValueId($optionBlocks);

        $groups = [];
        $nonMatchingIds = [];
        foreach ($safeSelection as $valueId) {
            $block = $blockByValueId[$valueId] ?? null;
            if (! $block) {
                continue;
            }

            if (! $this->blockAffectsVariantMatching($block)) {
                $nonMatchingIds[] = $valueId;

                continue;
            }

            $gid = (int) ($block['id'] ?? 0);
            $groups[$gid][] = $valueId;
        }

        if ($groups === []) {
            return [$safeSelection];
        }

        $lineSets = [[]];
        foreach ($groups as $gid => $ids) {
            $ids = array_values(array_unique(array_map('intval', $ids)));
            sort($ids);
            if ($ids === []) {
                continue;
            }

            $block = $optionBlocks[$gid];
            $shouldSplit = ($block['selection_mode'] ?? 'single') === 'multiple'
                && count($ids) > 1;

            $choices = $shouldSplit ? array_map(fn (int $id): array => [$id], $ids) : [$ids];
            $next = [];
            foreach ($lineSets as $prefix) {
                foreach ($choices as $choice) {
                    $row = array_values(array_unique(array_merge($prefix, $choice)));
                    sort($row);
                    $next[] = $row;
                }
            }
            $lineSets = $next;
        }

        if ($lineSets === []) {
            return [$safeSelection];
        }

        $unique = [];
        $seen = [];
        foreach ($lineSets as $row) {
            $row = array_values(array_unique(array_merge($row, $nonMatchingIds)));
            sort($row);
            $key = implode(',', $row);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $unique[] = $row;
        }

        return $unique;
    }

    /**
     * @param  list<int>  $selectedOptionValueIds
     * @return list<int>
     */
    public function sanitizeSelectionForProduct(Product $product, array $selectedOptionValueIds): array
    {
        if ($selectedOptionValueIds === []) {
            return [];
        }

        $allowed = [];
        foreach ($this->storefrontOptionBlocks($product) as $block) {
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
    public function priceAddonForSelection(Product $product, array $selectedOptionValueIds): float
    {
        $safeIds = $this->sanitizeSelectionForProduct($product, $selectedOptionValueIds);
        if ($safeIds === []) {
            return 0.0;
        }

        $priceById = [];
        foreach ($this->storefrontOptionBlocks($product) as $block) {
            foreach ($block['values'] as $row) {
                $id = (int) ($row['id'] ?? 0);
                if ($id <= 0) {
                    continue;
                }
                $priceById[$id] = (float) ($row['price'] ?? 0);
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
     * @return list<array{
     *     group_id: int,
     *     group_name: string,
     *     value_id: int,
     *     value_name: string,
     *     value_type: string,
     *     color_hex: string|null,
     *     swatch_image: string|null,
     *     price_addon: float,
     *     label: string
     * }>
     */
    public function selectionDisplayItems(Product $product, array $selectedOptionValueIds): array
    {
        $safeIds = $this->sanitizeSelectionForProduct($product, $selectedOptionValueIds);
        if ($safeIds === []) {
            return [];
        }

        $blocks = $this->storefrontOptionBlocks($product);
        $byValueId = [];

        foreach ($blocks as $block) {
            foreach ($block['values'] as $value) {
                $byValueId[(int) $value['id']] = [
                    'group_id' => (int) $block['id'],
                    'group_name' => (string) ($block['name'] ?? ''),
                    'value_id' => (int) $value['id'],
                    'value_name' => trim((string) ($value['name'] ?? '')),
                    'value_type' => (string) ($block['value_type'] ?? 'text'),
                    'color_hex' => filled($value['color_hex'] ?? null) ? (string) $value['color_hex'] : null,
                    'swatch_image' => filled($value['swatch_image'] ?? null) ? (string) $value['swatch_image'] : null,
                    'price_addon' => (float) ($value['price'] ?? 0),
                ];
            }
        }

        $items = [];
        foreach ($safeIds as $id) {
            $row = $byValueId[$id] ?? null;
            if (! $row) {
                continue;
            }

            $label = trim(($row['group_name'] !== '' ? $row['group_name'].': ' : '').$row['value_name']);
            $row['label'] = $label !== '' ? $label : (string) $row['value_name'];
            $items[] = $row;
        }

        return $items;
    }

    /**
     * @param  list<int>  $selectedOptionValueIds
     * @return list<string>
     */
    public function describeSelection(Product $product, array $selectedOptionValueIds): array
    {
        $safeIds = $this->sanitizeSelectionForProduct($product, $selectedOptionValueIds);
        if ($safeIds === []) {
            return [];
        }

        $values = OptionValue::query()
            ->whereIn('id', $safeIds)
            ->get(['id', 'name', 'option_group_id'])
            ->keyBy('id');
        $groupNames = OptionGroup::query()
            ->whereIn('id', $values->pluck('option_group_id')->map(fn ($id): int => (int) $id)->unique()->values()->all())
            ->pluck('name', 'id')
            ->all();

        $parts = [];
        foreach ($safeIds as $id) {
            $value = $values->get($id);
            if (! $value) {
                continue;
            }
            $groupName = (string) ($groupNames[(int) $value->option_group_id] ?? '');
            $valueName = trim((string) $value->name);
            if ($groupName === '' || $valueName === '') {
                continue;
            }
            $parts[] = $groupName.': '.$valueName;
        }

        return $parts;
    }

    /**
     * Підбирає варіант за набором option_value_ids (після sanitize), або null якщо це лише «база» товару без SKU.
     *
     * @param  list<int>  $lineOptionValueIds
     */
    public function resolveVariantForLine(Product $product, array $lineOptionValueIds): ?ProductVariant
    {
        $product->loadMissing(['variants']);
        $categoryGroupId = OptionGroup::systemCategoryGroupIdForCatalog();
        $safeIds = $this->sanitizeSelectionForProduct($product, $lineOptionValueIds);
        sort($safeIds);
        $blocks = $this->storefrontOptionBlocks($product);
        $blockByValueId = $this->blockByValueId($blocks);
        $blockByGroupId = [];
        foreach ($blocks as $block) {
            $blockByGroupId[(int) ($block['id'] ?? 0)] = $block;
        }
        $matchingIds = array_values(array_filter(
            $safeIds,
            fn (int $id): bool => $this->blockAffectsVariantMatching($blockByValueId[$id] ?? null)
        ));
        sort($matchingIds);

        $visible = $product->variants->filter(fn (ProductVariant $v): bool => $v->isVisibleOnStorefront());

        if ($matchingIds === []) {
            if ($visible->count() === 1) {
                $only = $visible->first();
                $pairs = VariantOptionsAllowlist::filterPairs($only->options ?? [], $product, $categoryGroupId);
                if ($pairs === []) {
                    return $only;
                }
            }

            return null;
        }

        $best = null;
        $bestId = \PHP_INT_MAX;
        foreach ($visible as $variant) {
            $pairs = VariantOptionsAllowlist::filterPairs($variant->options ?? [], $product, $categoryGroupId);
            $vids = collect($pairs)
                ->filter(function ($p) use ($blockByGroupId): bool {
                    $gid = (int) ($p['option_group_id'] ?? 0);

                    return $this->blockAffectsVariantMatching($blockByGroupId[$gid] ?? null);
                })
                ->map(fn ($p) => (int) ($p['option_value_id'] ?? 0))
                ->filter(fn (int $id): bool => $id > 0)
                ->unique()
                ->sort()
                ->values()
                ->all();

            if ($vids === $matchingIds && (int) $variant->id < $bestId) {
                $best = $variant;
                $bestId = (int) $variant->id;
            }
        }

        return $best;
    }

    /**
     * @param  array<int, array<string, mixed>>  $blocks
     * @return array<int, array<string, mixed>>
     */
    private function blockByValueId(array $blocks): array
    {
        $out = [];
        foreach ($blocks as $block) {
            foreach (($block['values'] ?? []) as $value) {
                $vid = (int) ($value['id'] ?? 0);
                if ($vid > 0) {
                    $out[$vid] = $block;
                }
            }
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>|null  $block
     */
    private function blockAffectsVariantMatching(?array $block): bool
    {
        if (! $block) {
            return false;
        }

        if (($block['affects_variant_matching'] ?? true) === false) {
            return false;
        }

        if (! filled($block['exclusive_for_variant_id'] ?? null)) {
            return true;
        }

        return (int) ($block['exclusive_variant_value_count'] ?? 0) <= 1;
    }

    private function exclusiveVariantIdForGroupIfSingle(Product $product, int $groupId, int $categoryGroupId): ?int
    {
        $variantIds = [];
        foreach ($product->variants->filter(fn (ProductVariant $v): bool => $v->isVisibleOnStorefront()) as $variant) {
            foreach (VariantOptionsAllowlist::filterPairs($variant->options ?? [], $product, $categoryGroupId) as $pair) {
                if ((int) ($pair['option_group_id'] ?? 0) === $groupId) {
                    $variantIds[$variant->id] = true;
                    break;
                }
            }
        }

        $ids = array_keys($variantIds);

        return count($ids) === 1 ? (int) $ids[0] : null;
    }

    private function optionGroupPinsAnyVisibleVariant(Product $product, int $groupId, int $categoryGroupId): bool
    {
        foreach ($product->variants->filter(fn (ProductVariant $v): bool => $v->isVisibleOnStorefront()) as $variant) {
            foreach (VariantOptionsAllowlist::filterPairs($variant->options ?? [], $product, $categoryGroupId) as $pair) {
                if ((int) ($pair['option_group_id'] ?? 0) === $groupId) {
                    return true;
                }
            }
        }

        return false;
    }

    private function variantValueCountForGroup(Product $product, int $variantId, int $groupId, int $categoryGroupId): int
    {
        $variant = $product->variants->firstWhere('id', $variantId);
        if (! $variant) {
            return 0;
        }

        $count = 0;
        foreach (VariantOptionsAllowlist::filterPairs($variant->options ?? [], $product, $categoryGroupId) as $pair) {
            if ((int) ($pair['option_group_id'] ?? 0) === $groupId && (int) ($pair['option_value_id'] ?? 0) > 0) {
                $count++;
            }
        }

        return $count;
    }

    private function firstSwatchImagePath(Product $product, int $groupId, int $valueId): ?string
    {
        foreach ($product->variant_options ?? [] as $row) {
            if ((int) ($row['option_group_id'] ?? 0) !== $groupId) {
                continue;
            }

            foreach ($row['value_photos'] ?? [] as $photoRow) {
                if ((int) ($photoRow['option_value_id'] ?? 0) !== $valueId) {
                    continue;
                }

                foreach ($photoRow['photos'] ?? [] as $path) {
                    if (is_string($path) && trim($path) !== '') {
                        return ltrim(trim($path), '/');
                    }
                }
            }
        }

        return null;
    }

    private function mtLine(string $text): string
    {
        $text = trim($text);
        if ($text === '') {
            return '';
        }

        return $text;
    }
}
