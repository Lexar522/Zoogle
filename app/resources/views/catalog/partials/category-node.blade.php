@php
    $depth = $depth ?? 0;
@endphp
<li class="catalog-category-list__item catalog-category-list__item--depth-{{ $depth }} @if (! empty($node['children'])) has-children @endif">
    <a
        href="{{ route('catalog.index', array_merge($catQuery, ['category' => $node['id']])) }}"
        class="catalog-category-list__link @if ($depth === 0) catalog-category-list__link--parent @else catalog-category-list__link--sub @endif @if ((int) $filters['category'] === (int) $node['id']) is-active @endif"
        data-category-id="{{ $node['id'] }}"
    >
        <span class="catalog-category-list__label">{{ mt($node['name']) }}</span>
        @if (! empty($node['children']))
            <span class="catalog-category-list__chevron" aria-hidden="true">›</span>
        @endif
    </a>

    @if (! empty($node['children']))
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
