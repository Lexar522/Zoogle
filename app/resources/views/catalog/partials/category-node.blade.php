@php
    $depth = $depth ?? 0;
    $hasChildren = ! empty($node['children']);
    $isActive = (int) $filters['category'] === (int) $node['id'];
    $linkClass = 'catalog-category-list__link '
        . ($depth === 0 ? 'catalog-category-list__link--parent' : 'catalog-category-list__link--sub')
        . ($isActive ? ' is-active' : '');
@endphp
<li class="catalog-category-list__item catalog-category-list__item--depth-{{ $depth }} @if ($hasChildren) has-children @endif">
    @if ($hasChildren)
        <div class="catalog-category-list__row @if ($depth === 0) catalog-category-list__row--top @endif">
            <a
                href="{{ route('catalog.index', array_merge($catQuery, ['category' => $node['id']])) }}"
                class="{{ $linkClass }}"
                data-category-id="{{ $node['id'] }}"
            >
                <span class="catalog-category-list__label">{{ mt($node['name']) }}</span>
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
                <span class="catalog-category-list__chevron" aria-hidden="true">›</span>
            </button>
        </div>
    @else
        <a
            href="{{ route('catalog.index', array_merge($catQuery, ['category' => $node['id']])) }}"
            class="{{ $linkClass }}"
            data-category-id="{{ $node['id'] }}"
        >
            <span class="catalog-category-list__label">{{ mt($node['name']) }}</span>
        </a>
    @endif

    @if ($hasChildren)
        <ul class="catalog-subcategory-list catalog-subcategory-list--depth-{{ $depth + 1 }}" role="list">
            @foreach ($node['children'] as $child)
                @include('catalog.partials.category-node', [
                    'node' => $child,
                    'catQuery' => $catQuery,
                    'filters' => $filters,
                    'depth' => $depth + 1,
                ])
            @endforeach
        </ul>
    @endif
</li>
