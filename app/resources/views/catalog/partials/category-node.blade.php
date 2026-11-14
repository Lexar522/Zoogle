@php
    $depth = $depth ?? 0;
    $hasChildren = ! empty($node['children']);
    $isActive = (int) $filters['category'] === (int) $node['id'];
    $linkClass = 'catalog-category-list__link '
        . ($depth === 0 ? 'catalog-category-list__link--parent' : 'catalog-category-list__link--sub')
        . ($isActive ? ' is-active' : '');
    $hubChipColor = isset($hubChipColor) ? (int) $hubChipColor : null;
    $categoryName = mt($node['name']);
    $allowOverflowTooltip = $allowOverflowTooltip ?? true;
    $showOverflowTooltip = $allowOverflowTooltip && $depth >= 1;
@endphp
<li
    class="catalog-category-list__item catalog-category-list__item--depth-{{ $depth }} @if ($hasChildren) has-children @endif"
    @if ($hubChipColor !== null && $depth === 0) data-hub-chip-color="{{ $hubChipColor }}" @endif
>
    @if ($hasChildren)
        <div class="catalog-category-list__row @if ($depth === 0) catalog-category-list__row--top @endif">
            <a
                href="{{ route('catalog.index', array_merge($catQuery, ['category' => $node['id']])) }}"
                class="{{ $linkClass }}"
                data-category-id="{{ $node['id'] }}"
                @if ($showOverflowTooltip) data-category-tooltip="{{ $categoryName }}" @endif
            >
                <span class="catalog-category-list__label">{{ $categoryName }}</span>
            </a>
            <button
                type="button"
                class="catalog-category-list__expand"
                aria-expanded="false"
                aria-label="{{ __('shop.catalog_category_expand', ['name' => mt($node['name'])]) }}"
                data-category-expand
                data-expand-label="{{ __('shop.catalog_category_expand', ['name' => mt($node['name'])]) }}"
                data-collapse-label="{{ __('shop.catalog_category_collapse', ['name' => mt($node['name'])]) }}"
            >
                <svg class="catalog-category-list__chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.25" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                    <path d="M6 9l6 6 6-6"/>
                </svg>
            </button>
        </div>
    @else
        <a
            href="{{ route('catalog.index', array_merge($catQuery, ['category' => $node['id']])) }}"
            class="{{ $linkClass }}"
            data-category-id="{{ $node['id'] }}"
            @if ($showOverflowTooltip) data-category-tooltip="{{ $categoryName }}" @endif
        >
            <span class="catalog-category-list__label">{{ $categoryName }}</span>
        </a>
    @endif

    @if ($hasChildren && $depth < \App\Support\CatalogCategoryTree::NAV_MAX_DEPTH - 1)
        <ul class="catalog-subcategory-list catalog-subcategory-list--depth-{{ $depth + 1 }}" role="list">
            @foreach ($node['children'] as $child)
                @include('catalog.partials.category-node', [
                    'node' => $child,
                    'catQuery' => $catQuery,
                    'filters' => $filters,
                    'depth' => $depth + 1,
                    'allowOverflowTooltip' => $allowOverflowTooltip,
                ])
            @endforeach
        </ul>
    @endif
</li>
