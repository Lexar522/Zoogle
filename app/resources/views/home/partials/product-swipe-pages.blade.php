{{-- Товари блоку сторінками ($perPage, типово 4): на телефоні сторінка — сітка у 2 колонки, гортається свайпом.
     На комп'ютері сторінки «розчиняються» в звичайній сітці, а з класом --paged (хіти й рекомендовані на головній) гортаються
     по $desktopGroup телефонних сторінок разом (2 × 4 = 8 товарів); зайві для комп'ютера точки позначені data-desktop-extra. --}}
@php
    $swipePages = $products->values()->chunk(max(1, (int) ($perPage ?? 4)));
    $desktopGroup = max(1, (int) ($desktopGroup ?? 1));
    $desktopPageCount = (int) ceil($swipePages->count() / $desktopGroup);
@endphp
<div class="home-product-carousel__viewport" tabindex="0" @if ($desktopGroup > 1) data-desktop-group="{{ $desktopGroup }}" @endif>
    <div class="home-product-carousel__track">
        @foreach ($swipePages as $pageIndex => $pageProducts)
            <div class="home-product-carousel__page" data-carousel-page>
                @foreach ($pageProducts as $cardIndex => $product)
                    <div class="home-product-carousel__cell">
                        @include('catalog.partials.product-card', [
                            'listing' => $product,
                            'listingQuotes' => $listingQuotes ?? [],
                            'bundleQuotes' => [],
                            'cardImagePriority' => $cardIndex,
                            'cardImageHighPriority' => false,
                            'homeCardBadge' => $badge ?? null,
                            'homeCardBadgeClass' => $badgeClass ?? null,
                        ])
                    </div>
                @endforeach
            </div>
        @endforeach
    </div>
</div>
@if ($swipePages->count() > 1)
    <div
        class="home-product-carousel__dots @if ($desktopPageCount < 2) home-product-carousel__dots--phone-only @endif"
        data-carousel-dots
        data-page-label="{{ __('shop.home_carousel_page', ['page' => ':page', 'total' => ':total']) }}"
    >
        <button type="button" class="home-product-carousel__page-arrow" data-carousel-page-prev aria-label="{{ __('shop.home_carousel_prev') }}" disabled>
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 18l-6-6 6-6"/></svg>
        </button>
        <span class="home-product-carousel__dot-list">
            @foreach ($swipePages as $pageIndex => $pageProducts)
                <button type="button" class="home-product-carousel__dot" data-carousel-page-dot="{{ $loop->index }}" aria-label="{{ __('shop.home_carousel_page', ['page' => $loop->iteration, 'total' => $swipePages->count()]) }}" @if ($loop->index >= $desktopPageCount) data-desktop-extra @endif @if ($loop->first) aria-current="true" @endif></button>
            @endforeach
        </span>
        <button type="button" class="home-product-carousel__page-arrow" data-carousel-page-next aria-label="{{ __('shop.home_carousel_next') }}">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 18l6-6-6-6"/></svg>
        </button>
    </div>
@endif
