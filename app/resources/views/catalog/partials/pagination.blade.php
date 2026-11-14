@if ($paginator->total() > 0)
    @php
        $pagination = \App\Models\ShopCatalogPaginationSetting::resolved();
        $currentPage = $paginator->currentPage();
        $lastPage = $paginator->lastPage();
        $showingAll = $pagination['show_all'] && request()->boolean('all');
        $window = \App\Support\CatalogPageWindow::range($currentPage, $lastPage, $pagination['visible_pages']);
        $showNumberList = ! $showingAll || $lastPage > 1;
        $showFirstPage = $window['start'] > 1;
        $showLastPage = $window['end'] < $lastPage;
    @endphp
    <nav class="shop-pagination" role="navigation" aria-label="{{ __('shop.pagination_aria') }}">
        <p class="shop-pagination__info">
            {{ __('shop.pagination_shown') }}
            <strong>{{ $paginator->firstItem() }}</strong>–<strong>{{ $paginator->lastItem() }}</strong>
            {{ __('shop.pagination_of') }} <strong>{{ $paginator->total() }}</strong>
        </p>
        @if ($showNumberList)
            <ul class="shop-pagination__list">
                @if ($paginator->onFirstPage())
                    <li><span class="shop-pagination__btn shop-pagination__disabled shop-pagination__nav" aria-disabled="true">‹</span></li>
                @else
                    <li><a class="shop-pagination__btn shop-pagination__link shop-pagination__nav" href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="{{ __('shop.pagination_prev_aria') }}">‹</a></li>
                @endif

                @if ($showFirstPage)
                    <li><a class="shop-pagination__btn shop-pagination__link shop-pagination__page shop-pagination__page--1" href="{{ $paginator->url(1) }}" data-catalog-page="1">1</a></li>
                    @if ($window['start'] > 2)
                        <li><span class="shop-pagination__dots" aria-hidden="true">…</span></li>
                    @endif
                @endif

                @for ($page = $window['start']; $page <= $window['end']; $page++)
                    @php $palette = (($page - 1) % 6) + 1; @endphp
                    @if ($page === $currentPage)
                        <li><span class="shop-pagination__btn shop-pagination__current shop-pagination__page shop-pagination__page--{{ $palette }} is-active" aria-current="page">{{ $page }}</span></li>
                    @else
                        <li><a class="shop-pagination__btn shop-pagination__link shop-pagination__page shop-pagination__page--{{ $palette }}" href="{{ $paginator->url($page) }}" data-catalog-page="{{ $page }}">{{ $page }}</a></li>
                    @endif
                @endfor

                @if ($showLastPage)
                    @if ($lastPage - $window['end'] > 1)
                        <li><span class="shop-pagination__dots" aria-hidden="true">…</span></li>
                    @endif
                    @php $lastPalette = (($lastPage - 1) % 6) + 1; @endphp
                    <li><a class="shop-pagination__btn shop-pagination__link shop-pagination__page shop-pagination__page--{{ $lastPalette }} shop-pagination__last" href="{{ $paginator->url($lastPage) }}" data-catalog-page="{{ $lastPage }}" rel="last" aria-label="{{ __('shop.pagination_last_aria') }}">{{ $lastPage }}</a></li>
                @endif

                @if ($paginator->hasMorePages())
                    <li><a class="shop-pagination__btn shop-pagination__link shop-pagination__nav" href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="{{ __('shop.pagination_next_aria') }}">›</a></li>
                @else
                    <li><span class="shop-pagination__btn shop-pagination__disabled shop-pagination__nav" aria-disabled="true">›</span></li>
                @endif

                @if ($pagination['show_last_arrow'] && $lastPage > 1 && $currentPage < $lastPage)
                    <li><a class="shop-pagination__btn shop-pagination__link shop-pagination__nav shop-pagination__end" href="{{ $paginator->url($lastPage) }}" rel="last" aria-label="{{ __('shop.pagination_last_aria') }}">»</a></li>
                @endif
            </ul>
        @endif
    </nav>
@endif
