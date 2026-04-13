@if ($paginator->hasPages())
    <nav class="shop-pagination" role="navigation" aria-label="Сторінки результатів">
        <p class="shop-pagination__info">
            Показано
            <strong>{{ $paginator->firstItem() }}</strong>–<strong>{{ $paginator->lastItem() }}</strong>
            з <strong>{{ $paginator->total() }}</strong>
        </p>
        <ul class="shop-pagination__list">
            @if ($paginator->onFirstPage())
                <li><span class="shop-pagination__disabled" aria-disabled="true">‹</span></li>
            @else
                <li><a class="shop-pagination__link" href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="Попередня сторінка">‹</a></li>
            @endif

            @foreach ($elements as $element)
                @if (is_string($element))
                    <li><span class="shop-pagination__dots">{{ $element }}</span></li>
                @endif
                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <li><span class="shop-pagination__current" aria-current="page">{{ $page }}</span></li>
                        @else
                            <li><a class="shop-pagination__link" href="{{ $url }}">{{ $page }}</a></li>
                        @endif
                    @endforeach
                @endif
            @endforeach

            @if ($paginator->hasMorePages())
                <li><a class="shop-pagination__link" href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="Наступна сторінка">›</a></li>
            @else
                <li><span class="shop-pagination__disabled" aria-disabled="true">›</span></li>
            @endif
        </ul>
    </nav>
@endif
