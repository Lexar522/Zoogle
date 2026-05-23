@if ($paginator->total() > 0)
    @php
        $currentPage = $paginator->currentPage();
        $lastPage = $paginator->lastPage();
        $visibleThrough = min($currentPage + 1, $lastPage);
    @endphp
    <nav class="shop-pagination" role="navigation" aria-label="{{ __('shop.pagination_aria') }}">
        <p class="shop-pagination__info">
            {{ __('shop.pagination_shown') }}
            <strong>{{ $paginator->firstItem() }}</strong>–<strong>{{ $paginator->lastItem() }}</strong>
            {{ __('shop.pagination_of') }} <strong>{{ $paginator->total() }}</strong>
        </p>
        <ul class="shop-pagination__list">
            @if ($paginator->onFirstPage())
                <li><span class="shop-pagination__btn shop-pagination__disabled shop-pagination__nav" aria-disabled="true">‹</span></li>
            @else
                <li><a class="shop-pagination__btn shop-pagination__link shop-pagination__nav" href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="{{ __('shop.pagination_prev_aria') }}">‹</a></li>
            @endif

            @for ($page = 1; $page <= $visibleThrough; $page++)
                @php $palette = (($page - 1) % 6) + 1; @endphp
                @if ($page === $currentPage)
                    <li><span class="shop-pagination__btn shop-pagination__current shop-pagination__page shop-pagination__page--{{ $palette }} is-active" aria-current="page">{{ $page }}</span></li>
                @else
                    <li><a class="shop-pagination__btn shop-pagination__link shop-pagination__page shop-pagination__page--{{ $palette }}" href="{{ $paginator->url($page) }}" data-catalog-page="{{ $page }}">{{ $page }}</a></li>
                @endif
            @endfor

            @if ($paginator->hasMorePages())
                <li><a class="shop-pagination__btn shop-pagination__link shop-pagination__nav" href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="{{ __('shop.pagination_next_aria') }}">›</a></li>
            @else
                <li><span class="shop-pagination__btn shop-pagination__disabled shop-pagination__nav" aria-disabled="true">›</span></li>
            @endif
        </ul>
    </nav>
@endif
