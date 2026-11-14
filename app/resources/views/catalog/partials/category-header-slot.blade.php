@php
    $node = $node ?? null;
    if ($node === null) {
        return;
    }
    $filters = $filters ?? ['q' => '', 'category' => 0, 'on_sale' => false, 'bundles' => false];
    $selectedCategoryId = (int) ($filters['category'] ?? 0);
    $nodeId = (int) ($node['id'] ?? 0);
    $children = $node['children'] ?? [];
    $hasChildren = $children !== [];
    $isActive = $selectedCategoryId === $nodeId;
    $nodeName = mt($node['name']);
    $side = in_array($side ?? 'left', ['left', 'right'], true) ? $side : 'left';
    $hubChipColor = isset($hubChipColor) ? (int) $hubChipColor : null;
@endphp
<div class="catalog-category-hub__col catalog-category-hub__col--side catalog-category-hub__col--{{ $side }}" @if ($hubChipColor !== null) data-hub-chip-color="{{ $hubChipColor }}" @endif>
    <div class="catalog-category-hub__panel">
        <div class="catalog-category-hub__panel-head">
            <div
                class="catalog-category-list__row catalog-category-list__row--top catalog-category-list__row--solo"
            >
                <a
                    href="{{ route('catalog.index', array_merge($catQuery, ['category' => $nodeId])) }}"
                    class="catalog-category-list__link catalog-category-list__link--parent @if ($isActive) is-active @endif"
                    data-category-id="{{ $nodeId }}"
                >
                    <span class="catalog-category-list__label">{{ $nodeName }}</span>
                </a>
            </div>
        </div>
        @if ($hasChildren)
            <div class="catalog-category-hub__panel-body">
                <ul class="catalog-subcategory-list catalog-subcategory-list--hub catalog-category-hub__subs-list" role="list">
                    @foreach ($children as $child)
                        @include('catalog.partials.category-node', [
                            'node' => $child,
                            'catQuery' => $catQuery,
                            'filters' => $filters,
                            'depth' => 1,
                            'allowOverflowTooltip' => true,
                        ])
                    @endforeach
                </ul>
            </div>
        @endif
    </div>
</div>
