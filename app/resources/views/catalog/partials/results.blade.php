@php
    use App\Support\CatalogCategoryTree;

    $filters = $filters ?? ['q' => '', 'category' => 0, 'on_sale' => false, 'bundles' => false, 'sort' => 'random', 'per_page' => 24, 'subcategories' => []];
    $sortVal = $filters['sort'] ?? 'random';
    $perPageVal = (int) ($filters['per_page'] ?? 24);
    $subcategoryFilterIds = array_values(array_filter(
        array_map('intval', $filters['subcategories'] ?? []),
        fn (int $id): bool => $id > 0
    ));
    $selectedSubcategoryLabels = $subcategoryFilterIds !== []
        ? CatalogCategoryTree::subcategoryFilterSelectedLabels($categoryTree ?? [], $subcategoryFilterIds)
        : [];
    $subcategoryFilterChipLabel = $selectedSubcategoryLabels !== []
        ? implode(', ', array_map(fn (string $name): string => mt($name), $selectedSubcategoryLabels)).' ×'
        : __('shop.catalog_filter_subcategories');
    $catalogQueryPreserve = array_filter([
        'q' => ($filters['q'] ?? '') !== '' ? $filters['q'] : null,
        'category' => ($filters['category'] ?? 0) > 0 ? (int) ($filters['category'] ?? 0) : null,
        'subcategories' => $subcategoryFilterIds !== [] ? $subcategoryFilterIds : null,
        'on_sale' => ! empty($filters['on_sale']) ? 1 : null,
        'bundles' => ! empty($filters['bundles']) ? 1 : null,
        'sort' => $sortVal !== 'random' ? $sortVal : null,
        'per_page' => $perPageVal !== 24 ? $perPageVal : null,
        'all' => request()->boolean('all') ? 1 : null,
    ], fn ($v) => $v !== null && $v !== '');
    $catalogReturnQuery = array_filter([
        'category' => ($filters['category'] ?? 0) > 0 ? (int) ($filters['category'] ?? 0) : null,
        'subcategories' => $subcategoryFilterIds !== [] ? $subcategoryFilterIds : null,
        'on_sale' => ! empty($filters['on_sale']) ? 1 : null,
        'all' => request()->boolean('all') ? 1 : null,
    ], fn ($v) => $v !== null && $v !== '');
    $showCatalogGrid = $showCatalogGrid ?? true;
@endphp

@if ($showCatalogGrid)
@include('catalog.partials.category-subcategory-filter-styles')
@php
    $selectedCategoryId = (int) ($filters['category'] ?? 0);
    $activeCategoryPath = [];

    $findCategoryPath = function (array $nodes, int $targetId, array $trail = []) use (&$findCategoryPath): array {
        foreach ($nodes as $node) {
            $nodeId = is_array($node) ? (int) ($node['id'] ?? 0) : (int) ($node->id ?? 0);
            if ($nodeId <= 0) {
                continue;
            }

            $normalizedNode = [
                'id' => $nodeId,
                'name' => mt((string) (is_array($node) ? ($node['name'] ?? '') : ($node->name ?? ''))),
            ];

            $currentTrail = $trail;
            $currentTrail[] = $normalizedNode;

            if ($nodeId === $targetId) {
                return $currentTrail;
            }

            $children = is_array($node) ? ($node['children'] ?? []) : ($node->children ?? []);
            if ($children instanceof \Illuminate\Support\Collection) {
                $children = $children->all();
            }

            $found = $findCategoryPath(is_array($children) ? $children : [], $targetId, $currentTrail);
            if ($found !== []) {
                return $found;
            }
        }

        return [];
    };

    if ($selectedCategoryId > 0) {
        $activeCategoryPath = $findCategoryPath($categoryTree ?? [], $selectedCategoryId);
    }

    $showActiveFilterChips = ($filters['q'] ?? '') !== '' || ($filters['category'] ?? 0) > 0 || $subcategoryFilterIds !== [] || ! empty($filters['on_sale'] ?? false) || ! empty($filters['bundles'] ?? false);
@endphp
@if (($filters['category'] ?? 0) > 0 && ! empty($categorySubcategoryFilterGroups ?? []))
    @include('catalog.partials.category-subcategory-filter', [
        'filters' => $filters,
        'categoryTree' => $categoryTree ?? [],
        'categorySubcategoryFilterGroups' => $categorySubcategoryFilterGroups,
    ])
@endif
<div id="catalog-listing-panel" class="home-shop-panel catalog-listing-panel">
<div class="catalog-toolbar">
    <div class="catalog-toolbar__head">
        <div class="catalog-toolbar__head-top">
            <div class="catalog-toolbar__head-row">
                <div>
                    @php
                        $isStorewideSearch = trim((string) ($filters['q'] ?? '')) !== '';
                        $toolbarTitle = __('shop.catalog_toolbar_catalog');
                        $selectedCategoryName = null;
                        $showRecommendedLabel = false;
                        if ($isStorewideSearch) {
                            $toolbarTitle = __('shop.catalog_toolbar_search_results');
                        } elseif (($filters['category'] ?? 0) > 0) {
                            $selectedCategoryName = $categoryValues->firstWhere('id', (int) ($filters['category'] ?? 0))?->name;
                            if (filled($selectedCategoryName)) {
                                $toolbarTitle = mt((string) $selectedCategoryName);
                                // Порядок за замовчуванням — окремий лейбл «Рекомендовані» поза H1, щоб не змінювати SEO-заголовок
                                $showRecommendedLabel = $sortVal === 'random';
                            }
                        }
                        if (! $isStorewideSearch && ! empty($filters['on_sale'] ?? false)) {
                            $toolbarTitle = (($filters['category'] ?? 0) > 0 ? $toolbarTitle.' · ' : '').__('shop.catalog_toolbar_sales_suffix');
                        }
                        if (! $isStorewideSearch && ! empty($filters['bundles'] ?? false)) {
                            $toolbarTitle = (($filters['category'] ?? 0) > 0 || ! empty($filters['on_sale'] ?? false) ? $toolbarTitle.' · ' : '').__('shop.catalog_ready_bundles');
                        }
                    @endphp
                    <div class="catalog-toolbar__title-line">
                        @if ($showRecommendedLabel)
                            <span class="catalog-toolbar__title catalog-toolbar__title-label">{{ __('shop.catalog_toolbar_recommended_prefix') }}</span>
                        @endif
                        <h1 class="catalog-toolbar__title">{{ $toolbarTitle }}</h1>
                    </div>
                </div>
                <div class="catalog-toolbar__meta">
                    {{ __('shop.catalog_found_prefix') }} <strong>{{ $listings->total() }}</strong>
                </div>
            </div>
            <div class="catalog-toolbar__chips-row">
                @if ($showActiveFilterChips)
                    <div class="catalog-toolbar__chips catalog-toolbar__chips--active" role="group" aria-label="{{ __('shop.catalog_active_filters_group_aria') }}">
                        @if (($filters['q'] ?? '') !== '')
                            <a
                                class="catalog-toolbar__chip catalog-toolbar__chip--filter"
                                href="{{ route('catalog.index', array_filter(array_merge($catalogQueryPreserve, ['q' => null]), fn ($v) => $v !== null && $v !== '')) }}"
                            >
                                {{ __('shop.catalog_filter_search_storewide') }} {{ $filters['q'] }} ×
                            </a>
                        @endif

                        @if (! $isStorewideSearch && $selectedCategoryId > 0 && $activeCategoryPath !== [])
                            @foreach ($activeCategoryPath as $index => $activeCategory)
                                @if ($index >= count($activeCategoryPath) - 1)
                                    @continue
                                @endif
                                @php
                                    $fallbackCategoryId = $index > 0 ? ($activeCategoryPath[$index - 1]['id'] ?? null) : null;
                                    $categoryChipQuery = array_filter(array_merge($catalogQueryPreserve, [
                                        'q' => null,
                                        'category' => $fallbackCategoryId,
                                    ]), fn ($v) => $v !== null && $v !== '');
                                @endphp
                                <a
                                    class="catalog-toolbar__chip catalog-toolbar__chip--filter"
                                    href="{{ route('catalog.index', $categoryChipQuery) }}"
                                >
                                    {{ $activeCategory['name'] }} ×
                                </a>
                            @endforeach
                        @endif

                        @if ($subcategoryFilterIds !== [])
                            <a
                                class="catalog-toolbar__chip catalog-toolbar__chip--filter"
                                href="{{ route('catalog.index', array_filter(array_merge($catalogQueryPreserve, ['subcategories' => null]), fn ($v) => $v !== null && $v !== '')) }}"
                            >
                                {{ $subcategoryFilterChipLabel }}
                            </a>
                        @endif

                        @if (! empty($filters['on_sale'] ?? false))
                            <a
                                class="catalog-toolbar__chip catalog-toolbar__chip--filter"
                                href="{{ route('catalog.index', array_filter(array_merge($catalogQueryPreserve, ['on_sale' => null]), fn ($v) => $v !== null && $v !== '')) }}"
                            >
                                {{ __('shop.catalog_filter_sales') }}
                            </a>
                        @endif

                        @if (! empty($filters['bundles'] ?? false))
                            <a
                                class="catalog-toolbar__chip catalog-toolbar__chip--filter"
                                href="{{ route('catalog.index', array_filter(array_merge($catalogQueryPreserve, ['bundles' => null]), fn ($v) => $v !== null && $v !== '')) }}"
                            >
                                {{ __('shop.catalog_filter_bundles') }}
                            </a>
                        @endif
                    </div>
                @endif
                <div class="catalog-toolbar__chips catalog-toolbar__chips--sort" role="group" aria-label="{{ __('shop.catalog_sort_group_aria') }}">
                    <button type="button" class="catalog-toolbar__chip @if ($sortVal === 'newest') is-active @endif" data-catalog-sort-preset="newest">{{ __('shop.sort_newest') }}</button>
                    @php
                        $catalogPagination = \App\Models\ShopCatalogPaginationSetting::resolved();
                        $catalogShowingAll = $catalogPagination['show_all'] && request()->boolean('all');
                        $catalogPagedParams = request()->except(['page', 'through', 'all']);
                    @endphp
                    @if ($catalogPagination['show_all'] && ($catalogShowingAll || $listings->lastPage() > 1))
                        <a
                            class="catalog-toolbar__chip catalog-toolbar__chip--show-all @if ($catalogShowingAll) is-active @endif"
                            href="{{ $catalogShowingAll ? route('catalog.index', $catalogPagedParams) : route('catalog.index', array_merge($catalogPagedParams, ['all' => 1])) }}"
                        >{{ $catalogShowingAll ? __('shop.pagination_show_pages') : __('shop.pagination_show_all') }}</a>
                    @endif
                    <button type="button" class="catalog-toolbar__chip @if ($sortVal === 'price_asc') is-active @endif" data-catalog-sort-preset="price_asc">{{ __('shop.sort_price_asc') }}</button>
                    <button type="button" class="catalog-toolbar__chip @if ($sortVal === 'price_desc') is-active @endif" data-catalog-sort-preset="price_desc">{{ __('shop.sort_price_desc') }}</button>
                    <button type="button" class="catalog-toolbar__chip @if ($sortVal === 'popular') is-active @endif" data-catalog-sort-preset="popular">{{ __('shop.sort_popular') }}</button>
                </div>
            </div>
        </div>
    </div>
</div>

<div id="catalog-products" class="grid catalog-results__grid" style="{{ \App\Models\ShopCatalogGridSetting::gridStyle() }}" data-catalog-product-grid>
    @forelse ($listings as $listing)
        @include('catalog.partials.product-card', [
            'listing' => $listing,
            'listingQuotes' => $listingQuotes ?? [],
            'bundleQuotes' => $bundleQuotes ?? [],
            'catalogReturnQuery' => $catalogReturnQuery,
        ])
    @empty
        <div class="card catalog-results__empty" style="grid-column: 1 / -1;">
            <p style="margin:0;">{{ __('shop.catalog_empty') }} <a href="{{ route('catalog.index') }}">{{ __('shop.catalog_empty_reset') }}</a>.</p>
        </div>
    @endforelse
</div>

@if ($listings->total() > 0)
    <footer
        class="catalog-listing-footer"
        data-catalog-total="{{ $listings->total() }}"
        data-catalog-per-page="{{ $listings->perPage() }}"
        data-catalog-current-page="{{ $listings->currentPage() }}"
        data-pagination-shown="{{ __('shop.pagination_shown') }}"
        data-pagination-of="{{ __('shop.pagination_of') }}"
    >
        <div class="pagination-wrap catalog-listing-footer__pages">
            {{ $listings->links('catalog.partials.pagination') }}
        </div>

        @if ($listings->hasMorePages())
            <div class="catalog-results-more catalog-listing-footer__more" role="region" aria-label="{{ __('shop.catalog_more_aria') }}">
                <p class="catalog-results-more__hint">{{ __('shop.catalog_more_hint') }}</p>
                <div class="catalog-results-more__actions">
                    <button
                        type="button"
                        class="btn secondary catalog-results-more__load"
                        data-catalog-load-more
                        data-catalog-next-url="{{ $listings->nextPageUrl() }}"
                    >{{ __('shop.catalog_load_more') }}</button>
                </div>
            </div>
        @endif
    </footer>
@endif
</div>
@else
<div id="catalog-listing-panel" class="home-shop-panel catalog-listing-panel catalog-listing-panel--prompt">
    <p class="catalog-results__empty-hint-text">{{ __('shop.catalog_prompt') }}</p>
</div>
@endif
