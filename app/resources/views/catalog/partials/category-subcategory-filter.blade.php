@php
    use App\Support\CatalogCategoryTree;

    $filters = $filters ?? ['q' => '', 'category' => 0, 'on_sale' => false, 'bundles' => false];
    $categorySubcategoryFilterGroups = $categorySubcategoryFilterGroups ?? [];
    $categoryTree = $categoryTree ?? [];
    $selectedSubcategoryIds = array_values(array_filter(
        array_map('intval', $filters['subcategories'] ?? []),
        fn (int $id): bool => $id > 0
    ));
    $selectedSubcategorySet = array_flip($selectedSubcategoryIds);
    $appliedSubcategoryLabels = $selectedSubcategoryIds !== []
        ? CatalogCategoryTree::subcategoryFilterSelectedLabels($categoryTree, $selectedSubcategoryIds)
        : [];
    $selectedCategoryId = (int) ($filters['category'] ?? 0);
    $selectedCategoryLabel = '';
    if ($selectedCategoryId > 0) {
        $selectedCategoryNode = CatalogCategoryTree::findNodeInTree($categoryTree, $selectedCategoryId);
        $selectedCategoryLabel = trim((string) ($selectedCategoryNode['name'] ?? ''));
    }
    $sortVal = $filters['sort'] ?? 'random';
    $perPageVal = (int) ($filters['per_page'] ?? 24);
@endphp

@if ($categorySubcategoryFilterGroups !== [] && ($filters['category'] ?? 0) > 0)
<form
    class="catalog-subcategory-filter"
    method="get"
    action="{{ route('catalog.index') }}"
    data-catalog-subcategory-filter
    aria-label="{{ __('shop.catalog_subcategory_filter_aria') }}"
>
    @if ($appliedSubcategoryLabels !== [] || $selectedCategoryLabel !== '')
    <div class="catalog-subcategory-filter__head">
        @if ($appliedSubcategoryLabels !== [])
            <div class="catalog-subcategory-filter__applied" aria-live="polite">
                <span class="catalog-subcategory-filter__applied-label">{{ __('shop.catalog_subcategory_filter_applied') }}</span>
                <ul class="catalog-subcategory-filter__applied-list" role="list">
                    @foreach ($appliedSubcategoryLabels as $appliedLabel)
                        <li class="catalog-subcategory-filter__applied-item" role="listitem">{{ mt($appliedLabel) }}</li>
                    @endforeach
                </ul>
            </div>
        @elseif ($selectedCategoryLabel !== '')
            <p class="catalog-subcategory-filter__context">{{ __('shop.catalog_subcategory_filter_category', ['name' => mt($selectedCategoryLabel)]) }}</p>
        @endif
    </div>
    @endif
    <div class="catalog-subcategory-filter__body">
        @if (($filters['category'] ?? 0) > 0)
            <input type="hidden" name="category" value="{{ (int) $filters['category'] }}">
        @endif
        @if (! empty($filters['on_sale']))
            <input type="hidden" name="on_sale" value="1">
        @endif
        @if (! empty($filters['bundles']))
            <input type="hidden" name="bundles" value="1">
        @endif
        @if ($sortVal !== 'random')
            <input type="hidden" name="sort" value="{{ $sortVal }}">
        @endif
        @if ($perPageVal !== 24)
            <input type="hidden" name="per_page" value="{{ $perPageVal }}">
        @endif
        @if (request()->boolean('all') && \App\Models\ShopCatalogPaginationSetting::showAllEnabled())
            <input type="hidden" name="all" value="1">
        @endif

        <div class="catalog-subcategory-filter__groups">
            @foreach ($categorySubcategoryFilterGroups as $group)
                @php
                    $groupParentId = (int) ($group['parentId'] ?? 0);
                    $selectedCategoryId = (int) ($filters['category'] ?? 0);
                    $showGroupTitle = $groupParentId > 0 && $groupParentId !== $selectedCategoryId;
                    $groupDepth = max(0, (int) ($group['depth'] ?? 0));
                @endphp
                <section
                    class="catalog-subcategory-filter__group"
                    style="--subfilter-depth: {{ $groupDepth }}"
                    @if ($showGroupTitle) aria-labelledby="catalog-subcategory-filter-group-{{ $groupParentId }}" @endif
                >
                    @if ($showGroupTitle)
                        <h3 class="catalog-subcategory-filter__group-title" id="catalog-subcategory-filter-group-{{ $groupParentId }}">{{ mt($group['parentName'] ?? '') }}</h3>
                    @endif
                    <ul class="catalog-subcategory-filter__items" role="list">
                        @foreach ($group['items'] ?? [] as $item)
                            @php
                                $itemId = (int) ($item['id'] ?? 0);
                            @endphp
                            @if ($itemId <= 0)
                                @continue
                            @endif
                            <li class="catalog-subcategory-filter__item" role="listitem">
                                <label class="catalog-subcategory-filter__label">
                                    <input
                                        type="checkbox"
                                        class="catalog-subcategory-filter__checkbox"
                                        name="subcategories[]"
                                        value="{{ $itemId }}"
                                        @checked(isset($selectedSubcategorySet[$itemId]))
                                    >
                                    <span class="catalog-subcategory-filter__text">{{ mt($item['name'] ?? '') }}</span>
                                </label>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endforeach
        </div>

        <div class="catalog-subcategory-filter__actions">
            <button type="submit" class="btn catalog-subcategory-filter__submit">{{ __('shop.catalog_subcategory_filter_apply') }}</button>
        </div>
    </div>
</form>
@endif
