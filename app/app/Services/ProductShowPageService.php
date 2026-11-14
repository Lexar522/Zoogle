<?php

namespace App\Services;

use App\Models\OptionGroup;
use App\Models\OptionValue;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ShopIntegrationSetting;
use App\Support\CatalogCategoryTree;
use App\Support\GalleryPhotoIndex;
use App\Support\ListingDefaultOptionPrice;
use App\Support\ListingMedia;
use App\Support\ListingOptionValuePrices;
use App\Support\ShopHeaderContacts;
use App\Support\StorefrontOptionCatalog;
use App\Support\VariantOptionsAllowlist;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
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
        private readonly ProductRecommendationsService $productRecommendations,
        private readonly CategoryCheckoutRulesService $categoryCheckoutRules,
        private readonly ListingOptionSelectionService $listingOptionSelection,
        private readonly CareArticleCatalog $careArticleCatalog,
    ) {}

    /**
     * @param  array<string, mixed>  $nav  Результат CatalogController::catalogNavigationFilters()
     * @return array<string, mixed>
     */
    public function buildViewData(Request $request, Product $listing, array $nav): array
    {
        $categoryGroupId = $nav['categoryGroupId'];

        $listing->loadMissing(['variants']);
        [$galleryPhotos, $optionBlocks] = $this->unifyGalleryWithColorPhotos(
            $listing->storefrontGalleryStoragePaths(),
            $this->buildUnifiedStorefrontOptionBlocks($listing, $categoryGroupId)
        );
        $productOptionsDisplay = $optionBlocks;
        $listingQuote = $this->variantPricing->quoteProduct($listing);
        $minEffective = $listingQuote->effectivePrice;
        $listingBaseCompareAt = $listingQuote->strikePrice;
        $defaultOptionSelection = $this->defaultOptionSelectionForBlocks($optionBlocks);
        $defaultOptionsAddon = ListingDefaultOptionPrice::addonTotalForProduct($listing);
        $displayEffective = round((float) $minEffective + $defaultOptionsAddon, 2);
        $optionsPreselectedFromVariant = false;
        $galleryItems = ListingMedia::storefrontItemsForPaths(
            $galleryPhotos,
            ListingMedia::normalizeMetaMap($listing->photo_meta ?? [])
        );
        $catalogVariantIdForScript = null;
        $variantsPayload = $this->buildVariantsPayload($listing, $categoryGroupId, $optionBlocks);

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
                    $rawCatName = OptionValue::query()
                        ->whereKey($firstCategoryValueId)
                        ->value('name');
                    $categoryLabel = is_string($rawCatName) && trim($rawCatName) !== ''
                        ? $this->mtLine($rawCatName)
                        : null;
                }

                break;
            }
        }

        $browsingCategoryName = null;
        $filterCategoryId = (int) $request->integer('category');
        if ($filterCategoryId > 0 && $categoryGroupId > 0) {
            $rawBrowseName = OptionValue::query()
                ->where('option_group_id', $categoryGroupId)
                ->whereKey($filterCategoryId)
                ->value('name');
            $browsingCategoryName = is_string($rawBrowseName) && trim($rawBrowseName) !== ''
                ? $this->mtLine($rawBrowseName)
                : null;
        }

        $categoryDisplayForPage = $browsingCategoryName ?? $categoryLabel;
        $categoryBreadcrumb = $this->resolveCategoryBreadcrumb($listing, $nav, $categoryDisplayForPage);
        $subcategoryFilterChip = $this->resolveSubcategoryFilterChip(
            $request,
            is_array($nav['categoryTree'] ?? null) ? $nav['categoryTree'] : [],
        );

        $hasMatchingAxis = collect($optionBlocks)->contains(
            fn (array $block): bool => ($block['affects_variant_matching'] ?? true) !== false
        );
        $needsBareVariantPicker = count($variantsPayload) > 1 && ! $hasMatchingAxis;

        $galleryUrls = array_map(
            static fn (array $item): string => (string) ($item['url'] ?? ''),
            $galleryItems
        );

        $listingBaseCompareAtForScript = $listingBaseCompareAt !== null && (float) $listingBaseCompareAt > (float) $displayEffective
            ? (float) $listingBaseCompareAt
            : null;

        $matchedVariantId = $this->matchVariantIdFromSelection($optionBlocks, $variantsPayload, $defaultOptionSelection);
        if ($needsBareVariantPicker && $matchedVariantId === null && $variantsPayload !== []) {
            $matchedVariantId = (int) ($variantsPayload[0]['id'] ?? 0);
            $matchedVariantId = $matchedVariantId > 0 ? $matchedVariantId : null;
        }

        $matchedVariantRow = $this->variantPayloadById($variantsPayload, $matchedVariantId);
        if ($matchedVariantRow !== null) {
            $displayEffective = round((float) $matchedVariantRow['price'] + $defaultOptionsAddon, 2);
            $listingBaseCompareAt = $matchedVariantRow['compare_at'] !== null
                ? (float) $matchedVariantRow['compare_at']
                : null;
            $listingBaseCompareAtForScript = $listingBaseCompareAt !== null && $listingBaseCompareAt > $displayEffective
                ? $listingBaseCompareAt
                : null;
            $catalogVariantIdForScript = (int) $matchedVariantRow['id'];
            $optionsPreselectedFromVariant = $defaultOptionSelection !== [];
        }

        $needsExplicitVariantChoice = $needsBareVariantPicker;
        $optionsIncomplete = $this->listingOptionSelection
            ->missingRequiredOptionGroupNames($listing, $defaultOptionSelection) !== [];
        if ($needsBareVariantPicker && $matchedVariantId === null) {
            $optionsIncomplete = true;
        }
        // Ціни для розмітки Google мають збігатися з показаною ціною і фідом Merchant (з доплатою опцій за замовчуванням)
        $offerPrices = $variantsPayload !== []
            ? array_map(fn (array $row): float => round((float) ($row['price'] ?? 0) + $defaultOptionsAddon, 2), $variantsPayload)
            : [$displayEffective];
        $pdpOfferLowPrice = min($offerPrices);
        $pdpOfferHighPrice = max($offerPrices);
        $pdpOfferCount = max(1, $variantsPayload !== [] ? count($variantsPayload) : 1);

        $checkoutRules = $this->categoryCheckoutRules->aggregateForProductIds([(int) $listing->id]);
        $pdpDefersOnlinePayment = $checkoutRules['defers_online_payment'] || $listing->defersOnlinePayment();
        $listingStockMode = is_array($matchedVariantRow)
            ? (string) ($matchedVariantRow['stock_mode'] ?? 'ok')
            : match ($listing->stockStatus()) {
                Product::STOCK_PREORDER => 'preorder',
                Product::STOCK_OUT_OF_STOCK => 'none',
                default => 'ok',
            };
        $pdpDeferModalContacts = array_map(
            static fn (array $item): array => [
                'label' => $item['label'],
                'href' => $item['href'],
                'kind' => $item['kind'],
                'external' => $item['external'],
            ],
            ShopHeaderContacts::deferModalContactItemsFrom(ShopIntegrationSetting::storefront()),
        );

        $productShowConfig = [
            'listingTitle' => (string) $listing->title,
            // База без доплат: JS додає addon з обраних (і з defaultOptionSelection після ensureDefault).
            'listingPrice' => (float) $minEffective,
            'listingCompareAt' => $listingBaseCompareAtForScript,
            'listingStockMode' => $listing->is_available ? $listingStockMode : 'none',
            'optionBlocks' => $optionBlocks,
            'defaultOptionSelection' => $defaultOptionSelection,
            'requiresOptionSelection' => $optionBlocks !== [],
            'variants' => $variantsPayload,
            'catalogCategoryGroupId' => $categoryGroupId,
            'initialCategoryLabel' => (string) ($categoryDisplayForPage ?? ''),
            'initialPhotos' => $galleryUrls,
            'initialGalleryItems' => $galleryItems,
            'storefrontPreferredVariantId' => $catalogVariantIdForScript,
            'storefrontDefaultVariantId' => $catalogVariantIdForScript,
            'needsExplicitChoice' => $needsExplicitVariantChoice,
            'optionsPreselectedFromVariant' => $optionsPreselectedFromVariant,
            'storageBase' => rtrim(asset('storage'), '/'),
            'optimizeImages' => \App\Models\ShopMediaSetting::optimizeImagesEnabled(),
            'needsBareVariantPicker' => $needsBareVariantPicker,
            'pdpDefersOnlinePayment' => $pdpDefersOnlinePayment,
            'pdpDeferModalContacts' => $pdpDeferModalContacts,
            'i18n' => [
                'pdp_no_photo' => (string) __('shop.pdp_no_photo'),
                'pdp_badge_preorder' => (string) __('shop.pdp_badge_preorder'),
                'pdp_badge_low' => (string) __('shop.pdp_badge_low'),
                'pdp_badge_in_stock' => (string) __('shop.pdp_badge_in_stock'),
                'pdp_badge_out_of_stock' => (string) __('shop.pdp_badge_out_of_stock'),
                'pdp_selected_prefix' => (string) __('shop.pdp_selected_prefix'),
                'pdp_selected_empty' => (string) __('shop.pdp_selected_empty'),
                'pdp_category_prefix' => (string) __('shop.pdp_category_prefix'),
                'pdp_unavailable_hint' => (string) __('shop.pdp_unavailable_hint'),
                'pdp_preorder_hint' => (string) __('shop.pdp_preorder_hint'),
                'pdp_lines_selected_prefix' => (string) __('shop.pdp_lines_selected_prefix'),
                'pdp_price_for_option' => (string) __('shop.pdp_price_for_option'),
                'pdp_select_options_hint' => (string) __('shop.pdp_select_options_hint'),
            ],
        ];

        $recommendedProducts = $this->productRecommendations->forProductPage($listing, 8);
        $recommendedListingQuotes = $recommendedProducts->isEmpty()
            ? []
            : $this->variantPricing->quoteManyProducts($recommendedProducts);
        $careArticles = $this->careArticleCatalog->publishedForProduct($listing);

        return [
            'listing' => $listing,
            'optionBlocks' => $optionBlocks,
            'productOptionsDisplay' => $productOptionsDisplay,
            'variantsPayload' => $variantsPayload,
            'categoryLabel' => $categoryLabel,
            'categoryDisplayForPage' => $categoryDisplayForPage,
            'categoryBreadcrumb' => $categoryBreadcrumb,
            'subcategoryFilterChip' => $subcategoryFilterChip,
            'needsBareVariantPicker' => $needsBareVariantPicker,
            'defaultOptionSelection' => $defaultOptionSelection,
            'optionsPreselectedFromVariant' => $optionsPreselectedFromVariant,
            'galleryPhotos' => $galleryPhotos,
            'galleryItems' => $galleryItems,
            'catalogVariantIdForScript' => $catalogVariantIdForScript,
            'storefrontPreferredVariantId' => $catalogVariantIdForScript,
            'storefrontDefaultVariantId' => $catalogVariantIdForScript,
            'pdpOfferCount' => $pdpOfferCount,
            'matchedVariantId' => $catalogVariantIdForScript,
            // SSR ціна з урахуванням преселекту (щоб збігалась із JS після ensureDefaultOptionSelection).
            'listingBasePrice' => $displayEffective,
            'listingBaseCompareAt' => $listingBaseCompareAt,
            'listingStockMode' => $productShowConfig['listingStockMode'],
            'optionsIncomplete' => $optionsIncomplete,
            'categoryTree' => $nav['categoryTree'],
            'categoryNavigationTree' => $nav['categoryNavigationTree'] ?? $nav['categoryTree'],
            'filters' => $nav['filters'],
            'catalogCategoryGroupId' => $categoryGroupId,
            'productShowConfig' => $productShowConfig,
            'pdpMetaDescription' => $this->buildPdpMetaDescription($listing, $displayEffective, $listingBaseCompareAtForScript, $categoryDisplayForPage),
            'pdpOgImageUrl' => $this->resolvePdpOgImageUrl($galleryItems),
            'pdpOfferLowPrice' => $pdpOfferLowPrice,
            'pdpOfferHighPrice' => $pdpOfferHighPrice,
            'recommendedProducts' => $recommendedProducts,
            'recommendedListingQuotes' => $recommendedListingQuotes,
            'careArticles' => $careArticles,
            'pdpDefersOnlinePayment' => $pdpDefersOnlinePayment,
            'pdpDeferModalContacts' => $pdpDeferModalContacts,
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
            return $fallbackLabel ? [['id' => 0, 'name' => $this->mtLine((string) $fallbackLabel)]] : [];
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
            return $fallbackLabel ? [['id' => 0, 'name' => $this->mtLine((string) $fallbackLabel)]] : [];
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
                    'name' => $this->mtLine($name),
                ]);
            }
            $currentId = (int) ($node->parent_id ?? 0);
            $guard++;
        }

        if ($path === [] && $fallbackLabel) {
            $path[] = ['id' => 0, 'name' => $this->mtLine((string) $fallbackLabel)];
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

    /**
     * @param  array<int, array{id: int, name: string, children: array<int, array<string, mixed>>}>  $categoryTree
     * @return array{label: string, href: string}|null
     */
    private function resolveSubcategoryFilterChip(Request $request, array $categoryTree): ?array
    {
        $categoryId = (int) $request->integer('category');
        if ($categoryId <= 0 || $categoryTree === []) {
            return null;
        }

        /** @var list<int> $selectedIds */
        $selectedIds = collect($request->input('subcategories', []))
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();

        if ($selectedIds === []) {
            return null;
        }

        $labels = CatalogCategoryTree::subcategoryFilterSelectedLabels($categoryTree, $selectedIds);
        if ($labels === []) {
            return null;
        }

        $chipLabels = array_map(fn (string $name): string => $this->mtLine($name), $labels);

        return [
            'label' => implode(', ', $chipLabels).' ×',
            'href' => route('catalog.index', array_filter([
                'on_sale' => $request->boolean('on_sale') ? 1 : null,
                'category' => $categoryId,
            ], fn ($value) => $value !== null && $value !== '')),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $galleryItems
     */
    private function resolvePdpOgImageUrl(array $galleryItems): ?string
    {
        foreach ($galleryItems as $item) {
            if (! is_array($item)) {
                continue;
            }

            $type = (string) ($item['type'] ?? 'image');
            if ($type === 'video') {
                $posterUrl = (string) ($item['poster_url'] ?? '');
                if ($posterUrl !== '') {
                    return $posterUrl;
                }

                continue;
            }

            $url = (string) ($item['url'] ?? '');
            if ($url !== '') {
                return $url;
            }
        }

        return null;
    }

    private function buildPdpMetaDescription(Product $listing, float $minPrice, ?float $compareAt, ?string $categoryName = null): string
    {
        $short = $listing->short_description;
        $long = $listing->description;
        $parts = array_filter([
            $short ? strip_tags((string) $short) : null,
            $long ? Str::limit(strip_tags((string) $long), 120) : null,
        ]);
        $priceLine = __('shop.pdp_meta_price_from', ['price' => number_format($minPrice, 0, '', ' ')]);
        if ($compareAt !== null && $compareAt > $minPrice) {
            $priceLine .= ' '.__('shop.pdp_meta_compare_hint');
        }
        $base = trim(implode(' ', $parts));
        $categoryName = trim((string) $categoryName);
        $haystack = mb_strtolower($base.' '.(string) $listing->title);
        if ($categoryName !== '' && ! str_contains($haystack, mb_strtolower($categoryName))) {
            $base = trim($categoryName.'. '.$base);
        }
        if ($base === '') {
            $base = trim((string) $listing->title);
        }

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
     * @param  list<array<string, mixed>>  $optionBlocks
     * @param  list<array<string, mixed>>  $variantsPayload
     * @param  array<int, int|list<int>>  $selection
     */
    public function matchVariantIdFromSelection(array $optionBlocks, array $variantsPayload, array $selection): ?int
    {
        $signature = $this->selectionMatchSignature($optionBlocks, $selection);
        if ($signature === null) {
            if (count($variantsPayload) === 1 && ($variantsPayload[0]['options'] ?? []) === []) {
                $only = (int) ($variantsPayload[0]['id'] ?? 0);

                return $only > 0 ? $only : null;
            }

            return null;
        }

        $map = $this->variantMatchSignaturesByOptionBlocks($optionBlocks, $variantsPayload);
        $id = (int) ($map[$signature] ?? 0);

        return $id > 0 ? $id : null;
    }

    /**
     * @param  list<array<string, mixed>>  $optionBlocks
     * @param  array<int, int|list<int>>  $selection
     */
    public function selectionMatchSignature(array $optionBlocks, array $selection): ?string
    {
        $parts = [];
        foreach ($optionBlocks as $block) {
            if (($block['affects_variant_matching'] ?? true) === false) {
                continue;
            }
            $gid = (int) ($block['id'] ?? 0);
            if ($gid <= 0 || ! array_key_exists($gid, $selection)) {
                continue;
            }
            $val = $selection[$gid];
            if (is_array($val)) {
                $norm = array_values(array_unique(array_map('intval', $val)));
                sort($norm);
                if ($norm === []) {
                    continue;
                }
                $parts[] = $gid.':'.implode(',', $norm);
            } else {
                $vid = (int) $val;
                if ($vid <= 0) {
                    continue;
                }
                $parts[] = $gid.':'.$vid;
            }
        }
        if ($parts === []) {
            return null;
        }
        sort($parts);

        return implode('|', $parts);
    }

    /**
     * @param  list<array<string, mixed>>  $optionBlocks
     * @return list<array<string, mixed>>
     */
    private function buildVariantsPayload(Product $listing, int $categoryGroupId, array $optionBlocks): array
    {
        $visible = $listing->variants
            ->filter(fn (ProductVariant $variant): bool => $variant->isVisibleOnStorefront())
            ->sortBy(fn (ProductVariant $variant): array => [(int) $variant->sort_order, (int) $variant->id])
            ->values();

        if ($visible->isEmpty()) {
            return [];
        }

        $quotes = $this->variantPricing->quoteManyProductVariants($visible);
        $payload = [];
        foreach ($visible as $variant) {
            $quote = $quotes[(int) $variant->id] ?? $this->variantPricing->quoteProductVariant($variant);
            $compare = $quote->strikePrice !== null && $quote->strikePrice > $quote->effectivePrice + 0.0001
                ? (float) $quote->strikePrice
                : null;
            $optionsMap = $this->storefrontVariantOptionsMap($variant, $listing, $categoryGroupId);
            $payload[] = [
                'id' => (int) $variant->id,
                'label' => $this->variantPickerLabel($variant, $optionsMap, $optionBlocks),
                'price' => (float) $quote->effectivePrice,
                'compare_at' => $compare,
                'stock_mode' => $this->variantStockMode($variant, $listing),
                'quantity' => $variant->quantity !== null ? (int) $variant->quantity : null,
                'options' => $optionsMap,
                'photos' => $this->variantPhotoPaths($variant),
            ];
        }

        return $payload;
    }

    /**
     * @param  list<array<string, mixed>>  $payload
     * @return array<string, mixed>|null
     */
    private function variantPayloadById(array $payload, ?int $id): ?array
    {
        if ($id === null || $id <= 0) {
            return null;
        }

        foreach ($payload as $row) {
            if ((int) ($row['id'] ?? 0) === $id) {
                return $row;
            }
        }

        return null;
    }

    private function variantStockMode(ProductVariant $variant, Product $listing): string
    {
        if ($listing->allowsPreorder() || $variant->allows_preorder) {
            return 'preorder';
        }
        if (! $variant->isSellable()) {
            return 'none';
        }
        if ($variant->quantity !== null && (int) $variant->quantity <= 0) {
            return 'none';
        }
        if ($variant->is_low_stock) {
            return 'low';
        }

        return 'ok';
    }

    /**
     * @param  array<int, int|list<int>>  $optionsMap
     * @param  list<array<string, mixed>>  $optionBlocks
     */
    private function variantPickerLabel(ProductVariant $variant, array $optionsMap, array $optionBlocks): string
    {
        $parts = [];
        foreach ($optionBlocks as $block) {
            $gid = (int) ($block['id'] ?? 0);
            if ($gid <= 0 || ! array_key_exists($gid, $optionsMap)) {
                continue;
            }
            $selected = $optionsMap[$gid];
            $ids = is_array($selected) ? $selected : [$selected];
            $names = [];
            foreach ($block['values'] ?? [] as $value) {
                if (in_array((int) ($value['id'] ?? 0), array_map('intval', $ids), true)) {
                    $name = trim((string) ($value['name'] ?? ''));
                    if ($name !== '') {
                        $names[] = $name;
                    }
                }
            }
            if ($names !== []) {
                $parts[] = implode(', ', $names);
            }
        }

        return $parts !== [] ? implode(' / ', $parts) : 'Варіант #'.$variant->id;
    }

    /**
     * @return list<string>
     */
    private function variantPhotoPaths(ProductVariant $variant): array
    {
        return collect(is_array($variant->photos) ? $variant->photos : [])
            ->map(fn ($path) => \App\Support\StoragePublicPath::normalizeFileUploadState($path)
                ?? (is_string($path) ? \App\Support\StoragePublicPath::normalize($path) : null))
            ->filter(fn (?string $path): bool => is_string($path) && $path !== '')
            ->values()
            ->all();
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

        $modes = StorefrontOptionCatalog::groupsByIds(array_keys($raw))
            ->map(fn (OptionGroup $group): ?string => $group->selection_mode)
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
                    $allowedIds = StorefrontOptionCatalog::valuesInGroup($groupId)
                        ->filter(fn (OptionValue $value): bool => (bool) $value->is_active)
                        ->map(fn (OptionValue $value): int => (int) $value->id)
                        ->values()
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
     * Єдина галерея PDP: фото товару, далі фото всіх кольорів у порядку опцій.
     * Фото кольору, що є копією вже наявного фото (інший файл, той самий вміст),
     * у галерею не додається — у кольорі воно замінюється на наявне, щоб вибір
     * кольору перемикав на нього, а не дублював кадр.
     *
     * @param  list<string>  $productPaths
     * @param  list<array<string, mixed>>  $optionBlocks
     * @return array{0: list<string>, 1: list<array<string, mixed>>}
     */
    private function unifyGalleryWithColorPhotos(array $productPaths, array $optionBlocks): array
    {
        $index = new GalleryPhotoIndex(Storage::disk('public'));
        foreach ($productPaths as $path) {
            $index->add($path);
        }

        $blocks = array_map(function (array $block) use ($index): array {
            if (($block['value_type'] ?? 'text') !== 'color') {
                return $block;
            }

            $block['values'] = array_map(function (array $row) use ($index): array {
                $row['gallery_photos'] = array_values(array_unique(array_map(
                    fn (string $path): string => $index->add($path),
                    $row['gallery_photos'] ?? []
                )));

                return $row;
            }, $block['values'] ?? []);

            return $block;
        }, $optionBlocks);

        return [$index->paths(), $blocks];
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
                    ->map(fn ($path) => \App\Support\StoragePublicPath::normalizeFileUploadState($path)
                        ?? (is_string($path) ? \App\Support\StoragePublicPath::normalize($path) : null))
                    ->filter(fn (?string $path): bool => is_string($path) && $path !== '')
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

        $group = StorefrontOptionCatalog::group($groupId);
        if (! $group || ! $group->is_active || $group->slug === 'category') {
            return null;
        }

        $values = StorefrontOptionCatalog::valuesIn($groupId, $allowedValueIds, activeOnly: ! $includeInactiveValues);

        if ($values->isEmpty()) {
            return null;
        }

        $variantRows = is_array($listing->variant_options ?? null) ? $listing->variant_options : [];

        $catalogValueType = ($group->value_type ?? 'text') === 'color' ? 'color' : 'text';
        $anyColorHex = $values->contains(fn (OptionValue $v): bool => filled($v->color_hex));
        if ($catalogValueType !== 'color' && $anyColorHex) {
            $catalogValueType = 'color';
        }

        // Колір на вітрині — лише один варіант (навіть якщо в довіднику стоїть multiple).
        $selectionMode = $catalogValueType === 'color'
            ? 'single'
            : (string) ($group->selection_mode ?? 'single');

        return [
            'id' => $group->id,
            'name' => $this->mtLine((string) $group->name),
            'selection_mode' => $selectionMode,
            'value_type' => $catalogValueType,
            'exclusivity_key' => \App\Support\OptionGroupExclusivity::keyFromName((string) $group->name),
            'default_option_value_id' => $this->defaultOptionValueIdFromListing($listing, $groupId, $allowedValueIds),
            'values' => $values->map(function (OptionValue $v) use ($variantRows, $groupId): array {
                $addon = ListingOptionValuePrices::priceAddonForValue($variantRows, $groupId, (int) $v->id);

                return [
                    'id' => $v->id,
                    'name' => $this->mtLine((string) $v->name),
                    'price' => $addon > 0 ? $addon : null,
                    'is_base_price' => $addon <= 0,
                    'color_hex' => $v->color_hex,
                ];
            })->values()->all(),
        ];
    }

    /**
     * @param  list<int>  $allowedValueIds
     */
    private function defaultOptionValueIdFromListing(Product $listing, int $groupId, array $allowedValueIds): ?int
    {
        foreach (($listing->variant_options ?? []) as $row) {
            if ((int) ($row['option_group_id'] ?? 0) !== $groupId) {
                continue;
            }

            $id = (int) ($row['default_option_value_id'] ?? 0);
            if ($id > 0 && in_array($id, $allowedValueIds, true)) {
                return $id;
            }

            break;
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

    /**
     * Преселект: спочатку default з адмінки, інакше значення без доплати / з мінімальною.
     *
     * @param  list<array<string, mixed>>  $optionBlocks
     * @return array<int, int|list<int>>
     */
    private function defaultOptionSelectionForBlocks(array $optionBlocks): array
    {
        $selection = [];
        $claimedExclusivityKeys = [];

        foreach ($optionBlocks as $block) {
            $groupId = (int) ($block['id'] ?? 0);
            if ($groupId <= 0) {
                continue;
            }

            $values = is_array($block['values'] ?? null) ? $block['values'] : [];
            if (count($values) < 2) {
                continue;
            }

            $exclusivityKey = is_string($block['exclusivity_key'] ?? null)
                ? trim((string) $block['exclusivity_key'])
                : '';
            if ($exclusivityKey === '') {
                $exclusivityKey = (string) (\App\Support\OptionGroupExclusivity::keyFromName((string) ($block['name'] ?? '')) ?? '');
            }
            if ($exclusivityKey !== '' && isset($claimedExclusivityKeys[$exclusivityKey])) {
                // У сімʼї «Розмір: …» преселектимо лише першу групу — не всі одразу.
                continue;
            }

            $preferredId = (int) ($block['default_option_value_id'] ?? 0);
            $valueIds = collect($values)
                ->map(fn (array $val): int => (int) ($val['id'] ?? 0))
                ->filter(fn (int $id): bool => $id > 0)
                ->values()
                ->all();

            $baseValueId = ($preferredId > 0 && in_array($preferredId, $valueIds, true))
                ? $preferredId
                : $this->basePriceOptionValueId($values);

            if ($baseValueId === null) {
                continue;
            }

            $selection[$groupId] = ($block['selection_mode'] ?? 'single') === 'multiple'
                ? [$baseValueId]
                : $baseValueId;

            if ($exclusivityKey !== '') {
                $claimedExclusivityKeys[$exclusivityKey] = $groupId;
            }
        }

        return $selection;
    }

    /**
     * @param  list<array<string, mixed>>  $values
     */
    private function basePriceOptionValueId(array $values): ?int
    {
        foreach ($values as $val) {
            $price = $val['price'] ?? null;
            if ($price === null || (float) $price <= 0) {
                $id = (int) ($val['id'] ?? 0);

                return $id > 0 ? $id : null;
            }
        }

        $lowestId = null;
        $lowestPrice = null;
        foreach ($values as $val) {
            $price = (float) ($val['price'] ?? 0);
            $id = (int) ($val['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            if ($lowestPrice === null || $price < $lowestPrice) {
                $lowestPrice = $price;
                $lowestId = $id;
            }
        }

        return $lowestId;
    }
}
