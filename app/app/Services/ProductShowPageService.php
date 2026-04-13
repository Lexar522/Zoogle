<?php

namespace App\Services;

use App\Models\OptionGroup;
use App\Models\OptionValue;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Support\ListingOptionValuePrices;
use App\Support\VariantOptionsAllowlist;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Збір даних для сторінки товару (PDP): опції вітрини, payload варіантів, ціни «від», галерея.
 *
 * Інваріанти контенту (для адмінки / наповнення):
 * - На картці мають бути погоджені variant_options і пари options у кожному SKU, що беруть участь у matching.
 * - Група slug «category» не показується як інтерактивна вісь; підпис категорії йде з варіанта або картки.
 * - Якщо кілька видимих SKU без жодної «вісі» опцій у JSON — на вітрині показується селект варіанта (див. needsBareVariantPicker).
 */
class ProductShowPageService
{
    public function __construct(
        private readonly VariantPricingService $variantPricing,
    ) {}

    /**
     * @param  array<string, mixed>  $nav  Результат CatalogController::catalogNavigationFilters()
     * @return array<string, mixed>
     */
    public function buildViewData(Request $request, Product $listing, array $nav): array
    {
        $categoryGroupId = $nav['categoryGroupId'];

        $optionBlocks = $this->buildUnifiedStorefrontOptionBlocks($listing, $categoryGroupId);
        $productOptionsDisplay = $optionBlocks;
        $listingQuote = $this->variantPricing->quoteProduct($listing);
        $minEffective = $listingQuote->effectivePrice;
        $listingBaseCompareAt = $listingQuote->strikePrice;
        $defaultOptionSelection = [];
        $optionsPreselectedFromVariant = false;
        $galleryPhotos = $this->galleryPhotosForListingPage($listing);
        $catalogVariantIdForScript = null;
        $variantsPayload = [];

        $categoryLabel = null;
        if ($categoryGroupId > 0) {
            foreach ($listing->variant_options ?? [] as $row) {
                if ((int) ($row['option_group_id'] ?? 0) !== $categoryGroupId) {
                    continue;
                }

                $firstCategoryValueId = collect($row['option_value_ids'] ?? [])
                    ->map(fn ($id) => (int) $id)
                    ->first();

                if ($firstCategoryValueId) {
                    $categoryLabel = OptionValue::query()
                        ->whereKey($firstCategoryValueId)
                        ->value('name');
                }

                break;
            }
        }

        $browsingCategoryName = null;
        $filterCategoryId = (int) $request->integer('category');
        if ($filterCategoryId > 0 && $categoryGroupId > 0) {
            $browsingCategoryName = OptionValue::query()
                ->where('option_group_id', $categoryGroupId)
                ->whereKey($filterCategoryId)
                ->value('name');
        }

        $categoryDisplayForPage = $browsingCategoryName ?? $categoryLabel;
        $categoryBreadcrumb = $this->resolveCategoryBreadcrumb($listing, $nav, $categoryDisplayForPage);

        $needsBareVariantPicker = false;

        $galleryUrls = collect($galleryPhotos)
            ->map(fn ($p) => asset('storage/'.$p))
            ->values()
            ->all();

        $listingBaseCompareAtForScript = $listingBaseCompareAt !== null && (float) $listingBaseCompareAt > (float) $minEffective
            ? (float) $listingBaseCompareAt
            : null;

        $needsExplicitVariantChoice = false;
        $pdpOfferLowPrice = $minEffective;
        $pdpOfferHighPrice = $minEffective;

        $productShowConfig = [
            'listingTitle' => (string) $listing->title,
            'listingPrice' => (float) $minEffective,
            'listingCompareAt' => $listingBaseCompareAtForScript,
            'listingStockMode' => $listing->is_available ? 'ok' : 'none',
            'optionBlocks' => $optionBlocks,
            'variants' => $variantsPayload,
            'catalogCategoryGroupId' => $categoryGroupId,
            'initialCategoryLabel' => (string) ($categoryDisplayForPage ?? ''),
            'initialPhotos' => $galleryUrls,
            'storefrontPreferredVariantId' => null,
            'storefrontDefaultVariantId' => null,
            'needsExplicitChoice' => $needsExplicitVariantChoice,
            'optionsPreselectedFromVariant' => $optionsPreselectedFromVariant,
            'storageBase' => rtrim(asset('storage'), '/'),
            'needsBareVariantPicker' => $needsBareVariantPicker,
        ];

        return [
            'listing' => $listing,
            'optionBlocks' => $optionBlocks,
            'productOptionsDisplay' => $productOptionsDisplay,
            'variantsPayload' => $variantsPayload,
            'categoryLabel' => $categoryLabel,
            'categoryDisplayForPage' => $categoryDisplayForPage,
            'categoryBreadcrumb' => $categoryBreadcrumb,
            'needsBareVariantPicker' => $needsBareVariantPicker,
            'defaultOptionSelection' => $defaultOptionSelection,
            'optionsPreselectedFromVariant' => $optionsPreselectedFromVariant,
            'galleryPhotos' => $galleryPhotos,
            'catalogVariantIdForScript' => $catalogVariantIdForScript,
            'storefrontPreferredVariantId' => null,
            'storefrontDefaultVariantId' => null,
            'listingBasePrice' => $minEffective,
            'listingBaseCompareAt' => $listingBaseCompareAt,
            'listingStockMode' => $productShowConfig['listingStockMode'],
            'categoryTree' => $nav['categoryTree'],
            'filters' => $nav['filters'],
            'catalogCategoryGroupId' => $categoryGroupId,
            'productShowConfig' => $productShowConfig,
            'pdpMetaDescription' => $this->buildPdpMetaDescription($listing, $minEffective, $listingBaseCompareAtForScript),
            'pdpOgImageUrl' => ($galleryPhotos[0] ?? null) ? url('storage/'.$galleryPhotos[0]) : null,
            'pdpOfferLowPrice' => $pdpOfferLowPrice,
            'pdpOfferHighPrice' => $pdpOfferHighPrice,
        ];
    }

    /**
     * @param  array<string, mixed>  $nav
     * @return list<array{id: int, name: string}>
     */
    private function resolveCategoryBreadcrumb(Product $listing, array $nav, ?string $fallbackLabel): array
    {
        $categoryValues = $nav['categoryValues'] ?? collect();
        if (! $categoryValues instanceof Collection || $categoryValues->isEmpty()) {
            return $fallbackLabel ? [['id' => 0, 'name' => (string) $fallbackLabel]] : [];
        }

        $byId = $categoryValues->keyBy('id');
        $startId = 0;
        if ((int) ($nav['categoryValueId'] ?? 0) > 0) {
            $startId = (int) $nav['categoryValueId'];
        } elseif ((int) ($listing->category_value_id ?? 0) > 0) {
            $startId = (int) $listing->category_value_id;
        } elseif ((int) ($listing->category_parent_value_id ?? 0) > 0) {
            $startId = (int) $listing->category_parent_value_id;
        }

        if ($startId <= 0 || ! $byId->has($startId)) {
            return $fallbackLabel ? [['id' => 0, 'name' => (string) $fallbackLabel]] : [];
        }

        $path = [];
        $currentId = $startId;
        $guard = 0;
        while ($guard < 16 && $currentId > 0 && $byId->has($currentId)) {
            $node = $byId->get($currentId);
            $name = trim((string) ($node->name ?? ''));
            if ($name !== '') {
                array_unshift($path, [
                    'id' => (int) ($node->id ?? 0),
                    'name' => $name,
                ]);
            }
            $currentId = (int) ($node->parent_id ?? 0);
            $guard++;
        }

        if ($path === [] && $fallbackLabel) {
            $path[] = ['id' => 0, 'name' => (string) $fallbackLabel];
        }

        $uniq = [];
        $seen = [];
        foreach ($path as $row) {
            $key = ((int) ($row['id'] ?? 0)).'|'.((string) ($row['name'] ?? ''));
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $uniq[] = [
                'id' => (int) ($row['id'] ?? 0),
                'name' => (string) ($row['name'] ?? ''),
            ];
        }

        return $uniq;
    }

    private function buildPdpMetaDescription(Product $listing, float $minPrice, ?float $compareAt): string
    {
        $parts = array_filter([
            $listing->short_description ? strip_tags($listing->short_description) : null,
            $listing->description ? Str::limit(strip_tags($listing->description), 120) : null,
        ]);
        $priceLine = 'від '.number_format($minPrice, 0, '', ' ').' ₴';
        if ($compareAt !== null && $compareAt > $minPrice) {
            $priceLine .= ' (акційна ціна)';
        }
        $base = implode(' ', $parts);

        return Str::limit(trim($base.' '.$priceLine), 160);
    }

    /**
     * Детермінований map «сигнатура options (лише matching-групи) → id варіанта» для валідації та API.
     * При колізії однакової сигнатури лишається варіант з меншим id.
     *
     * @param  list<array<string, mixed>>  $optionBlocks
     * @param  list<array<string, mixed>>  $variantsPayload
     * @return array<string, int>
     */
    public function variantMatchSignaturesByOptionBlocks(array $optionBlocks, array $variantsPayload): array
    {
        $out = [];
        foreach ($variantsPayload as $row) {
            $opts = $row['options'] ?? [];
            if (! is_array($opts)) {
                continue;
            }
            $parts = [];
            foreach ($optionBlocks as $block) {
                if (($block['affects_variant_matching'] ?? true) === false) {
                    continue;
                }
                $gid = (int) ($block['id'] ?? 0);
                if ($gid <= 0) {
                    continue;
                }
                if (! array_key_exists($gid, $opts)) {
                    continue;
                }
                $val = $opts[$gid];
                if (is_array($val)) {
                    $norm = array_values(array_unique(array_map('intval', $val)));
                    sort($norm);
                    $parts[] = $gid.':'.implode(',', $norm);
                } else {
                    $parts[] = $gid.':'.(int) $val;
                }
            }
            if ($parts === []) {
                continue;
            }
            sort($parts);
            $key = implode('|', $parts);
            $vid = (int) ($row['id'] ?? 0);
            if ($vid <= 0) {
                continue;
            }
            if (! isset($out[$key]) || $vid < $out[$key]) {
                $out[$key] = $vid;
            }
        }

        return $out;
    }

    /**
     * @return list<string>
     */
    private function galleryPhotosForListingPage(Product $listing): array
    {
        $listingPhotos = is_array($listing->photos ?? null) ? $listing->photos : [];

        if ($listingPhotos !== []) {
            return $listingPhotos;
        }

        return [];
    }

    /**
     * @return int|list<int>|null
     */
    private function firstOptionValueDefaultForBlock(array $block): int|array|null
    {
        $vals = $block['values'] ?? [];
        if ($vals === []) {
            return null;
        }

        $firstId = (int) ($vals[0]['id'] ?? 0);
        if ($firstId <= 0) {
            return null;
        }

        return ($block['selection_mode'] ?? 'single') === 'multiple' ? [$firstId] : $firstId;
    }

    /**
     * @return array<int, int|list<int>>
     */
    private function storefrontVariantOptionsMap(ProductVariant $variant, Product $listing, int $categoryGroupId): array
    {
        $pairs = VariantOptionsAllowlist::filterPairs($variant->options ?? [], $listing, $categoryGroupId);

        return $this->variantOptionsMapFromPairs($pairs, $categoryGroupId);
    }

    private function optionGroupPinsAnyVisibleVariant(Product $listing, int $groupId, int $categoryGroupId): bool
    {
        foreach ($listing->variants->filter(fn (ProductVariant $v): bool => $v->isVisibleOnStorefront()) as $variant) {
            $map = $this->storefrontVariantOptionsMap($variant, $listing, $categoryGroupId);
            foreach (array_keys($map) as $key) {
                if ((int) $key === $groupId) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param  list<array<string, mixed>>  $pairs
     * @return array<int, int|list<int>>
     */
    private function variantOptionsMapFromPairs(array $pairs, int $categoryGroupId): array
    {
        /** @var array<int, list<int>> $raw */
        $raw = [];
        foreach ($pairs as $pair) {
            $gid = (int) ($pair['option_group_id'] ?? 0);
            $vid = (int) ($pair['option_value_id'] ?? 0);
            if ($gid > 0 && $vid > 0 && $gid !== $categoryGroupId) {
                $raw[$gid][] = $vid;
            }
        }

        if ($raw === []) {
            return [];
        }

        $modes = OptionGroup::query()
            ->whereIn('id', array_keys($raw))
            ->pluck('selection_mode', 'id')
            ->all();

        $optionsMap = [];
        foreach ($raw as $gid => $vids) {
            $vids = array_values(array_unique($vids));
            sort($vids);
            $mode = $modes[$gid] ?? 'single';
            if ($mode === 'multiple') {
                $optionsMap[$gid] = $vids;
            } else {
                $optionsMap[$gid] = (int) ($vids[0] ?? 0);
            }
        }

        return $optionsMap;
    }

    /**
     * @return list<array{id: int, name: string, selection_mode: string, value_type: string, exclusive_for_variant_id?: int|null, affects_variant_matching: bool, values: list<array{id: int, name: string, price: float|null, color_hex: mixed}>}>
     */
    private function buildUnifiedStorefrontOptionBlocks(Product $listing, int $categoryGroupId): array
    {
        if ($categoryGroupId <= 0) {
            $categoryGroupId = OptionGroup::systemCategoryGroupIdForCatalog();
        }

        $orderedGids = [];
        $seenListing = [];
        foreach ($listing->variant_options ?? [] as $row) {
            $gid = (int) ($row['option_group_id'] ?? 0);
            if ($gid <= 0 || ($categoryGroupId > 0 && $gid === $categoryGroupId)) {
                continue;
            }
            if (isset($seenListing[$gid])) {
                continue;
            }
            $seenListing[$gid] = true;
            $orderedGids[] = $gid;
        }

        $variantGids = [];
        foreach ($listing->variants->filter(fn (ProductVariant $v): bool => $v->isVisibleOnStorefront()) as $variant) {
            foreach (VariantOptionsAllowlist::filterPairs($variant->options ?? [], $listing, $categoryGroupId) as $pair) {
                $g = (int) ($pair['option_group_id'] ?? 0);
                if ($g > 0 && ($categoryGroupId <= 0 || $g !== $categoryGroupId)) {
                    $variantGids[$g] = true;
                }
            }
        }

        $append = array_keys($variantGids);
        sort($append);
        foreach ($append as $gid) {
            if (! in_array($gid, $orderedGids, true)) {
                $orderedGids[] = $gid;
            }
        }

        if ($orderedGids === []) {
            return [];
        }

        $declaredOnListing = [];
        foreach ($listing->variant_options ?? [] as $row) {
            $g = (int) ($row['option_group_id'] ?? 0);
            if ($g > 0 && ($categoryGroupId <= 0 || $g !== $categoryGroupId)) {
                $declaredOnListing[$g] = true;
            }
        }

        $blocks = [];
        foreach ($orderedGids as $groupId) {
            $fromListing = $this->mergedListingValueIdsForGroup($listing, $groupId, $categoryGroupId);
            $fromVariants = $this->valueIdsUsedByVariantsForGroup($listing, $groupId, $categoryGroupId);
            $allowedIds = array_values(array_unique(array_merge($fromListing, $fromVariants)));
            sort($allowedIds);

            if ($allowedIds === []) {
                if (isset($declaredOnListing[$groupId]) || isset($variantGids[$groupId])) {
                    $allowedIds = OptionValue::query()
                        ->where('option_group_id', $groupId)
                        ->where('is_active', true)
                        ->orderBy('sort_order')
                        ->pluck('id')
                        ->map(fn ($id): int => (int) $id)
                        ->all();
                }
            }

            if ($allowedIds === []) {
                continue;
            }

            $block = $this->makeOptionBlock($listing, $groupId, $allowedIds, includeInactiveValues: true);
            if ($block !== null) {
                $block = $this->finalizeStorefrontOptionBlockWithPhotoSwatches($listing, $block, $categoryGroupId);
                $block['exclusive_for_variant_id'] = $this->exclusiveVariantIdForGroupIfSingle(
                    $listing,
                    $groupId,
                    $categoryGroupId
                );
                $block['affects_variant_matching'] = $this->optionGroupPinsAnyVisibleVariant($listing, $groupId, $categoryGroupId);
                $block['drives_gallery'] = ($block['value_type'] ?? 'text') === 'color';
                $blocks[] = $block;
            }
        }

        return $blocks;
    }

    private function exclusiveVariantIdForGroupIfSingle(
        Product $listing,
        int $groupId,
        int $categoryGroupId
    ): ?int {
        $variantIds = [];
        foreach ($listing->variants->filter(fn (ProductVariant $v): bool => $v->isVisibleOnStorefront()) as $variant) {
            foreach (VariantOptionsAllowlist::filterPairs($variant->options ?? [], $listing, $categoryGroupId) as $pair) {
                if ((int) ($pair['option_group_id'] ?? 0) === $groupId) {
                    $variantIds[$variant->id] = true;
                    break;
                }
            }
        }

        $ids = array_keys($variantIds);

        return count($ids) === 1 ? (int) $ids[0] : null;
    }

    /**
     * @param  array{id: int, name: string, selection_mode: string, value_type: string, values: list<array<string, mixed>>}  $block
     * @return array{id: int, name: string, selection_mode: string, value_type: string, values: list<array<string, mixed>>}
     */
    private function finalizeStorefrontOptionBlockWithPhotoSwatches(Product $listing, array $block, int $categoryGroupId): array
    {
        $gid = (int) $block['id'];
        $isColorBlock = ($block['value_type'] ?? 'text') === 'color';
        $values = [];
        foreach ($block['values'] as $row) {
            $vid = (int) ($row['id'] ?? 0);
            $row['swatch_image'] = $isColorBlock && ! filled($row['color_hex'] ?? null)
                ? $this->firstStoragePhotoForVariantOptionValue($listing, $gid, $vid, $categoryGroupId)
                : null;
            $row['gallery_photos'] = $isColorBlock
                ? $this->listingOptionValueGalleryPhotos($listing, $gid, $vid)
                : [];
            $values[] = $row;
        }
        $block['values'] = $values;

        return $block;
    }

    private function firstStoragePhotoForVariantOptionValue(
        Product $listing,
        int $groupId,
        int $valueId,
        int $categoryGroupId
    ): ?string {
        foreach ($listing->variants->filter(fn (ProductVariant $v): bool => $v->isVisibleOnStorefront()) as $variant) {
            $matches = false;
            foreach (VariantOptionsAllowlist::filterPairs($variant->options ?? [], $listing, $categoryGroupId) as $pair) {
                if ((int) ($pair['option_group_id'] ?? 0) === $groupId && (int) ($pair['option_value_id'] ?? 0) === $valueId) {
                    $matches = true;
                    break;
                }
            }
            if (! $matches) {
                continue;
            }

            $photos = is_array($variant->photos) ? $variant->photos : [];
            $first = $photos[0] ?? null;
            if (is_string($first) && trim($first) !== '') {
                return $first;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function listingOptionValueGalleryPhotos(Product $listing, int $groupId, int $valueId): array
    {
        foreach ($listing->variant_options ?? [] as $row) {
            if ((int) ($row['option_group_id'] ?? 0) !== $groupId) {
                continue;
            }

            foreach (($row['value_photos'] ?? []) as $photoRow) {
                if ((int) ($photoRow['option_value_id'] ?? 0) !== $valueId) {
                    continue;
                }

                return collect($photoRow['photos'] ?? [])
                    ->map(fn ($path) => is_string($path) ? trim($path) : '')
                    ->filter(fn (string $path): bool => $path !== '')
                    ->values()
                    ->all();
            }
        }

        return [];
    }

    /**
     * @return list<int>
     */
    private function mergedListingValueIdsForGroup(Product $listing, int $groupId, int $categoryGroupId): array
    {
        $found = [];
        foreach ($listing->variant_options ?? [] as $row) {
            if ((int) ($row['option_group_id'] ?? 0) !== $groupId) {
                continue;
            }
            if ($categoryGroupId > 0 && $groupId === $categoryGroupId) {
                continue;
            }
            foreach ($row['option_value_ids'] ?? [] as $id) {
                $i = (int) $id;
                if ($i > 0) {
                    $found[$i] = true;
                }
            }
        }

        return array_keys($found);
    }

    /**
     * @return list<int>
     */
    private function valueIdsUsedByVariantsForGroup(Product $listing, int $groupId, int $categoryGroupId): array
    {
        $found = [];
        foreach ($listing->variants->filter(fn (ProductVariant $v): bool => $v->isVisibleOnStorefront()) as $variant) {
            foreach (VariantOptionsAllowlist::filterPairs($variant->options ?? [], $listing, $categoryGroupId) as $pair) {
                if ((int) ($pair['option_group_id'] ?? 0) !== $groupId) {
                    continue;
                }
                $vid = (int) ($pair['option_value_id'] ?? 0);
                if ($vid > 0) {
                    $found[$vid] = true;
                }
            }
        }

        return array_keys($found);
    }

    /**
     * @param  list<int>  $allowedValueIds
     * @return array{id: int, name: string, selection_mode: string, value_type: string, values: list<array{id: int, name: string, price: float|null, color_hex: mixed}>}|null
     */
    private function makeOptionBlock(Product $listing, int $groupId, array $allowedValueIds, bool $includeInactiveValues = false): ?array
    {
        if ($groupId <= 0 || $allowedValueIds === []) {
            return null;
        }

        $group = OptionGroup::query()->find($groupId);
        if (! $group || ! $group->is_active || $group->slug === 'category') {
            return null;
        }

        $values = OptionValue::query()
            ->where('option_group_id', $groupId)
            ->whereIn('id', $allowedValueIds)
            ->when(! $includeInactiveValues, fn ($q) => $q->where('is_active', true))
            ->orderBy('sort_order')
            ->get(['id', 'name', 'color_hex']);

        if ($values->isEmpty()) {
            return null;
        }

        $variantRows = is_array($listing->variant_options ?? null) ? $listing->variant_options : [];

        $catalogValueType = ($group->value_type ?? 'text') === 'color' ? 'color' : 'text';
        $anyColorHex = $values->contains(fn (OptionValue $v): bool => filled($v->color_hex));
        if ($catalogValueType !== 'color' && $anyColorHex) {
            $catalogValueType = 'color';
        }

        return [
            'id' => $group->id,
            'name' => $group->name,
            'selection_mode' => $group->selection_mode ?? 'single',
            'value_type' => $catalogValueType,
            'values' => $values->map(function (OptionValue $v) use ($variantRows, $groupId): array {
                $addon = ListingOptionValuePrices::priceAddonForValue($variantRows, $groupId, (int) $v->id);

                return [
                    'id' => $v->id,
                    'name' => $v->name,
                    'price' => $addon > 0 ? $addon : null,
                    'color_hex' => $v->color_hex,
                ];
            })->values()->all(),
        ];
    }
}
