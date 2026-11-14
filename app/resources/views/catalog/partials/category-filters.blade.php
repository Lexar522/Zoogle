
@php
    $filters = $filters ?? ['q' => '', 'category' => 0, 'on_sale' => false, 'bundles' => false];
    $categoryTree = $categoryTree ?? [];
    $categoryNavigationTree = $categoryNavigationTree ?? $categoryTree;
@endphp
@php
    $inHeader = (bool) ($inHeader ?? false);
    $headerFeaturedLeft = $inHeader ? ($headerFeaturedLeft ?? ($categoryNavigationTree[0] ?? null)) : null;
    $headerFeaturedRight = $inHeader ? ($headerFeaturedRight ?? ($categoryNavigationTree[1] ?? null)) : null;
    $headerRestCategories = $inHeader
        ? ($headerRestCategories ?? array_values(array_slice($categoryNavigationTree, 2)))
        : $categoryNavigationTree;
    $headerBannerSlotTop = $headerBannerSlotTop ?? [];
    $headerBannerSlotBottom = $headerBannerSlotBottom ?? [];
    $headerBannersMobile = array_values(array_merge(
        is_array($headerBannerSlotTop) ? $headerBannerSlotTop : [],
        is_array($headerBannerSlotBottom) ? $headerBannerSlotBottom : []
    ));
    $headerFeaturedLeftPanelMaxHeightPx = max(80, (int) ($headerFeaturedLeftPanelMaxHeightPx ?? $headerFeaturedPanelMaxHeightPx ?? \App\Models\ShopHeaderSetting::DEFAULT_FEATURED_PANEL_MAX_HEIGHT_PX));
    $headerFeaturedRightPanelMaxHeightPx = max(80, (int) ($headerFeaturedRightPanelMaxHeightPx ?? $headerFeaturedPanelMaxHeightPx ?? \App\Models\ShopHeaderSetting::DEFAULT_FEATURED_PANEL_MAX_HEIGHT_PX));
    $headerBannerHeightPx = max(64, (int) ($headerBannerHeightPx ?? \App\Models\ShopHeaderSetting::DEFAULT_BANNER_HEIGHT_PX));
    $headerChipStylesCss = trim((string) ($headerChipStylesCss ?? ''));
    $hubUsesSubPalette = str_contains($headerChipStylesCss, '--chip-side-sub-use-palette');
    $hubUsesNestedPalette = str_contains($headerChipStylesCss, '--chip-side-nested-use-palette');
    $hubLayoutStyle = '--hub-featured-left-panel-max-height: '.$headerFeaturedLeftPanelMaxHeightPx.'px; --hub-featured-right-panel-max-height: '.$headerFeaturedRightPanelMaxHeightPx.'px; --hub-banner-height: '.$headerBannerHeightPx.'px;'
        .($headerChipStylesCss !== '' ? ' '.$headerChipStylesCss : '');
@endphp
<div
    class="catalog-filters @if($inHeader) catalog-filters--in-header @endif @if($hubUsesSubPalette) catalog-filters--chip-sub-palette @endif @if($hubUsesNestedPalette) catalog-filters--chip-nested-palette @endif"
    @if($inHeader) style="{{ $hubLayoutStyle }}" @endif
>
    @php
        $sortVal = $filters['sort'] ?? 'random';
        $perPageVal = (int) ($filters['per_page'] ?? 24);
        $subcategoryFilterIds = array_values(array_filter(
            array_map('intval', $filters['subcategories'] ?? []),
            fn (int $id): bool => $id > 0
        ));
        $catQuery = array_filter([
            // Пошук, «Акції» і «Комплекти» навмисно не передаємо: вибір категорії скидає їх
            'sort' => $sortVal !== 'random' ? $sortVal : null,
            'per_page' => $perPageVal !== 24 ? $perPageVal : null,
        ]);
        $promoContextQuery = array_filter([
            // Так само для «Акції» / «Комплекти» — не тримати активний q
            'category' => ($filters['category'] ?? 0) > 0 ? (int) $filters['category'] : null,
            'subcategories' => $subcategoryFilterIds !== [] ? $subcategoryFilterIds : null,
            'sort' => $sortVal !== 'random' ? $sortVal : null,
            'per_page' => $perPageVal !== 24 ? $perPageVal : null,
        ]);
        $promoOn = ! empty($filters['on_sale']);
        $bundlesOn = ! empty($filters['bundles']);
        // «Акції» — окремий режим: показує всі акційні товари, без прив'язки до поточної категорії
        $promoHref = route('catalog.index', array_merge($catQuery, ['on_sale' => 1]));
        $promoOffHref = route('catalog.index', $catQuery);
        $bundlesTurnOnQuery = $promoContextQuery;
        $bundlesHref = route('catalog.index', array_filter(array_merge($bundlesTurnOnQuery, $bundlesOn ? [] : ['bundles' => 1])));
        $bundlesOffQuery = $promoContextQuery;
        unset($bundlesOffQuery['bundles']);
        $bundlesOffHref = route('catalog.index', array_filter($bundlesOffQuery));
        $hubChipColorSlot = 0;
        $assignHubChipColor = static function () use (&$hubChipColorSlot): int {
            $hubChipColorSlot++;

            return (($hubChipColorSlot - 1) % 6) + 1;
        };
    @endphp

    @if ($inHeader)
        <div class="catalog-category-hub catalog-category-hub--desktop" aria-label="{{ __('shop.catalog_header_hub_aria') }}">
            @if ($headerFeaturedLeft !== null)
                @include('catalog.partials.category-header-slot', [
                    'node' => $headerFeaturedLeft,
                    'catQuery' => $catQuery,
                    'filters' => $filters,
                    'side' => 'left',
                    'hubChipColor' => $assignHubChipColor(),
                ])
            @else
                <div class="catalog-category-hub__col catalog-category-hub__col--side catalog-category-hub__col--left catalog-category-hub__col--empty" aria-hidden="true"></div>
            @endif

            <div class="catalog-category-hub__col catalog-category-hub__col--center">
                <div class="catalog-category-hub__center-rest">
                    <div class="catalog-category-hub__rest-scroll">
                        <ul class="catalog-category-list catalog-category-list--hub-rest" role="list">
                            @foreach ($headerRestCategories as $cat)
                                @include('catalog.partials.category-node', [
                                    'node' => $cat,
                                    'catQuery' => $catQuery,
                                    'filters' => $filters,
                                    'depth' => 0,
                                    'hubChipColor' => $assignHubChipColor(),
                                ])
                            @endforeach
                            <li class="catalog-category-list__item--promo" data-hub-chip-color="{{ $assignHubChipColor() }}">
                                <a
                                    href="{{ $promoOn ? $promoOffHref : $promoHref }}"
                                    class="catalog-category-list__link catalog-category-list__link--promo @if ($promoOn) is-active @endif"
                                    data-on-sale-link="1"
                                ><span class="catalog-category-list__label">{{ __('shop.catalog_promo_sales') }}</span></a>
                            </li>
                        </ul>
                    </div>
                </div>

                <div class="catalog-category-hub__banner-wrap">
                    <div class="catalog-category-hub__banner-slot">
                        @include('catalog.partials.category-header-banner', [
                            'headerBanners' => count($headerBannerSlotTop) >= 2
                                ? $headerBannerSlotTop
                                : (count($headerBannersMobile) >= 2 ? $headerBannersMobile : $headerBannerSlotTop),
                            'promoOn' => $promoOn,
                            'promoHref' => $promoHref,
                            'promoOffHref' => $promoOffHref,
                            'bannerContext' => 'desktop-top',
                        ])
                    </div>
                    <div class="catalog-category-hub__banner-slot">
                        @include('catalog.partials.category-header-banner', [
                            'headerBanners' => $headerBannerSlotBottom,
                            'promoOn' => $promoOn,
                            'promoHref' => $promoHref,
                            'promoOffHref' => $promoOffHref,
                            'bannerContext' => 'desktop-bottom',
                        ])
                    </div>
                </div>
            </div>

            @if ($headerFeaturedRight !== null)
                @include('catalog.partials.category-header-slot', [
                    'node' => $headerFeaturedRight,
                    'catQuery' => $catQuery,
                    'filters' => $filters,
                    'side' => 'right',
                    'hubChipColor' => $assignHubChipColor(),
                ])
            @else
                <div class="catalog-category-hub__col catalog-category-hub__col--side catalog-category-hub__col--right catalog-category-hub__col--empty" aria-hidden="true"></div>
            @endif
        </div>
    @endif

    <div class="catalog-filters__scroll @if($inHeader) catalog-category-hub catalog-category-hub__mobile catalog-category-hub--compact @endif">
        <ul class="catalog-category-list @if($inHeader) catalog-category-list--hub-rest @endif" role="list">
            @if (! $inHeader)
                <li class="catalog-category-list__item--promo">
                    <a
                        href="{{ $promoOn ? $promoOffHref : $promoHref }}"
                        class="catalog-category-list__link catalog-category-list__link--promo @if ($promoOn) is-active @endif"
                        data-on-sale-link="1"
                    ><span class="catalog-category-list__label">{{ __('shop.catalog_promo_sales') }}</span></a>
                </li>
            @endif
            @php
                if ($inHeader) {
                    $hubChipColorSlot = 0;
                }
            @endphp
            @foreach ($categoryNavigationTree as $cat)
                @include('catalog.partials.category-node', [
                    'node' => $cat,
                    'catQuery' => $catQuery,
                    'filters' => $filters,
                    'depth' => 0,
                    'hubChipColor' => $inHeader ? $assignHubChipColor() : null,
                ])
            @endforeach
            @if ($inHeader)
                <li class="catalog-category-list__item--promo" data-hub-chip-color="{{ $assignHubChipColor() }}">
                    <a
                        href="{{ $promoOn ? $promoOffHref : $promoHref }}"
                        class="catalog-category-list__link catalog-category-list__link--promo @if ($promoOn) is-active @endif"
                        data-on-sale-link="1"
                    ><span class="catalog-category-list__label">{{ __('shop.catalog_promo_sales') }}</span></a>
                </li>
            @endif
        </ul>
    </div>

    @if ($inHeader)
        <div class="catalog-category-hub__banner-mobile">
            <div class="catalog-category-hub__banner-slot catalog-category-hub__banner-slot--mobile">
                @include('catalog.partials.category-header-banner', [
                    'headerBanners' => $headerBannersMobile,
                    'promoOn' => $promoOn,
                    'promoHref' => $promoHref,
                    'promoOffHref' => $promoOffHref,
                    'bannerContext' => 'mobile',
                ])
            </div>
        </div>
    @endif
</div>

