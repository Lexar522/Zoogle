@php
    $filters = $filters ?? ['q' => '', 'category' => 0, 'on_sale' => false, 'sort' => 'newest', 'per_page' => 24];
    $sortVal = $filters['sort'] ?? 'newest';
    $perPageVal = (int) ($filters['per_page'] ?? 24);
    $catalogQueryPreserve = array_filter([
        'q' => ($filters['q'] ?? '') !== '' ? $filters['q'] : null,
        'category' => ($filters['category'] ?? 0) > 0 ? (int) ($filters['category'] ?? 0) : null,
        'on_sale' => ! empty($filters['on_sale']) ? 1 : null,
        'sort' => $sortVal !== 'newest' ? $sortVal : null,
        'per_page' => $perPageVal !== 24 ? $perPageVal : null,
    ], fn ($v) => $v !== null && $v !== '');
    $showCatalogGrid = $showCatalogGrid ?? true;
@endphp

@if ($showCatalogGrid)
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

    $showActiveFilterChips = ($filters['q'] ?? '') !== '' || ($filters['category'] ?? 0) > 0 || ! empty($filters['on_sale'] ?? false);
@endphp
<div class="home-shop-panel catalog-listing-panel">
<div class="catalog-toolbar">
    <div class="catalog-toolbar__head">
        <div class="catalog-toolbar__head-top">
            <div class="catalog-toolbar__head-row">
                <div>
                    @php
                        $toolbarTitle = __('shop.catalog_toolbar_catalog');
                        if (($filters['category'] ?? 0) > 0) {
                            $selectedCategoryName = $categoryValues->firstWhere('id', (int) ($filters['category'] ?? 0))?->name;
                            if (filled($selectedCategoryName)) {
                                $toolbarTitle = mt((string) $selectedCategoryName);
                            }
                        }
                        if (! empty($filters['on_sale'] ?? false)) {
                            $toolbarTitle = (($filters['category'] ?? 0) > 0 ? $toolbarTitle.' · ' : '').__('shop.catalog_toolbar_sales_suffix');
                        }
                    @endphp
                    <h1 class="catalog-toolbar__title">{{ $toolbarTitle }}</h1>
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
                                {{ __('shop.catalog_filter_search_prefix') }} {{ $filters['q'] }} ×
                            </a>
                        @endif

                        @if ($selectedCategoryId > 0 && $activeCategoryPath !== [])
                            @foreach ($activeCategoryPath as $index => $activeCategory)
                                @php
                                    $fallbackCategoryId = $index > 0 ? ($activeCategoryPath[$index - 1]['id'] ?? null) : null;
                                @endphp
                                <a
                                    class="catalog-toolbar__chip catalog-toolbar__chip--filter"
                                    href="{{ route('catalog.index', array_filter(array_merge($catalogQueryPreserve, ['category' => $fallbackCategoryId]), fn ($v) => $v !== null && $v !== '')) }}"
                                >
                                    {{ $activeCategory['name'] }} ×
                                </a>
                            @endforeach
                        @endif

                        @if (! empty($filters['on_sale'] ?? false))
                            <a
                                class="catalog-toolbar__chip catalog-toolbar__chip--filter"
                                href="{{ route('catalog.index', array_filter(array_merge($catalogQueryPreserve, ['on_sale' => null]), fn ($v) => $v !== null && $v !== '')) }}"
                            >
                                {{ __('shop.catalog_filter_sales') }}
                            </a>
                        @endif
                    </div>
                @endif
                <div class="catalog-toolbar__chips catalog-toolbar__chips--sort" role="group" aria-label="{{ __('shop.catalog_sort_group_aria') }}">
                    <button type="button" class="catalog-toolbar__chip @if ($sortVal === 'newest') is-active @endif" data-catalog-sort-preset="newest">{{ __('shop.sort_newest') }}</button>
                    <button type="button" class="catalog-toolbar__chip @if ($sortVal === 'title_asc') is-active @endif" data-catalog-sort-preset="title_asc">{{ __('shop.sort_az') }}</button>
                    <button type="button" class="catalog-toolbar__chip @if ($sortVal === 'title_desc') is-active @endif" data-catalog-sort-preset="title_desc">{{ __('shop.sort_za') }}</button>
                    <button type="button" class="catalog-toolbar__chip @if ($sortVal === 'price_asc') is-active @endif" data-catalog-sort-preset="price_asc">{{ __('shop.sort_price_asc') }}</button>
                    <button type="button" class="catalog-toolbar__chip @if ($sortVal === 'price_desc') is-active @endif" data-catalog-sort-preset="price_desc">{{ __('shop.sort_price_desc') }}</button>
                    <button type="button" class="catalog-toolbar__chip @if ($sortVal === 'popular') is-active @endif" data-catalog-sort-preset="popular">{{ __('shop.sort_popular') }}</button>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="grid catalog-results__grid">
    @forelse ($listings as $listing)
        @include('catalog.partials.product-card', ['listing' => $listing, 'listingQuotes' => $listingQuotes ?? [], 'bundleQuotes' => $bundleQuotes ?? []])
    @empty
        <div class="card" style="grid-column: 1 / -1;">
            <p style="margin:0;">{{ __('shop.catalog_empty') }} <a href="{{ route('catalog.index') }}">{{ __('shop.catalog_empty_reset') }}</a>.</p>
        </div>
    @endforelse
</div>

@if ($listings->hasMorePages())
    <div class="catalog-results-more" role="region" aria-label="{{ __('shop.catalog_more_aria') }}">
        <p class="catalog-results-more__hint">{{ __('shop.catalog_more_hint') }}</p>
        <div class="catalog-results-more__actions">
            <a
                href="{{ $listings->nextPageUrl() }}"
                class="btn secondary catalog-results-more__next"
                data-catalog-page-nav
            >{{ __('shop.catalog_next_page') }}</a>
            <button
                type="button"
                class="btn secondary catalog-results-more__load"
                data-catalog-load-more
                data-catalog-next-url="{{ $listings->nextPageUrl() }}"
            >{{ __('shop.catalog_load_more') }}</button>
        </div>
    </div>
@endif

<div class="pagination-wrap">
    {{ $listings->links('vendor.pagination.shop') }}
</div>
</div>
@else
<div class="home-shop-panel catalog-listing-panel catalog-listing-panel--prompt">
    <p class="catalog-results__empty-hint-text">{{ __('shop.catalog_prompt') }}</p>
</div>
@endif
