@php
    $headerBanners = $headerBanners ?? [];
    $promoOn = (bool) ($promoOn ?? false);
    $promoHref = $promoHref ?? route('catalog.index', ['on_sale' => 1]);
    $promoOffHref = $promoOffHref ?? route('catalog.index');
    $bannerContext = $bannerContext ?? 'desktop';
    $carouselId = 'header-banner-'.$bannerContext.'-'.substr(md5((string) $bannerContext), 0, 6);
    $hasSlides = count($headerBanners) > 0;
@endphp

@once
@push('styles')
@vite('resources/css/storefront/header-banner.css')
@endpush
@endonce

@if (! $hasSlides)
    <a
        href="{{ $promoOn ? $promoOffHref : $promoHref }}"
        class="catalog-header-banner-carousel__slide catalog-header-banner-carousel__slide--static @if ($promoOn) is-active @endif"
        data-on-sale-link="1"
    >
        {{ __('shop.catalog_header_banner') }}
    </a>
@else
    <div class="catalog-header-banner-carousel-wrap">
        <div
            id="{{ $carouselId }}"
            class="catalog-header-banner-carousel"
            data-header-banner-carousel
            data-header-banner-autoplay="5000"
            role="region"
            aria-label="{{ __('shop.catalog_header_banner_carousel_aria') }}"
            aria-roledescription="carousel"
        >
            <div class="catalog-header-banner-carousel__viewport">
                <div class="catalog-header-banner-carousel__track" data-header-banner-track>
                    @foreach ($headerBanners as $index => $slide)
                        @php
                            $href = $slide['href'] ?? null;
                            $openNewTab = (bool) ($slide['openNewTab'] ?? false);
                            $text = trim((string) ($slide['text'] ?? ''));
                            $alt = $text !== '' ? $text : __('shop.catalog_header_banner');
                        @endphp
                        @if ($href)
                            <a
                                href="{{ $href }}"
                                class="catalog-header-banner-carousel__slide"
                                data-header-banner-slide
                                @if ($openNewTab) target="_blank" rel="noopener noreferrer" @endif
                                aria-label="{{ $alt }}"
                            >
                                <img
                                    class="catalog-header-banner-carousel__image"
                                    src="{{ $slide['imageUrl'] }}"
                                    alt="{{ $alt }}"
                                    loading="{{ $index === 0 ? 'eager' : 'lazy' }}"
                                    decoding="async"
                                    draggable="false"
                                >
                                @if ($text !== '')
                                    <span class="catalog-header-banner-carousel__caption" aria-hidden="true">
                                        <span class="catalog-header-banner-carousel__text">{{ $text }}</span>
                                    </span>
                                @endif
                            </a>
                        @else
                            <div class="catalog-header-banner-carousel__slide" data-header-banner-slide aria-label="{{ $alt }}">
                                <img
                                    class="catalog-header-banner-carousel__image"
                                    src="{{ $slide['imageUrl'] }}"
                                    alt="{{ $alt }}"
                                    loading="{{ $index === 0 ? 'eager' : 'lazy' }}"
                                    decoding="async"
                                    draggable="false"
                                >
                                @if ($text !== '')
                                    <span class="catalog-header-banner-carousel__caption" aria-hidden="true">
                                        <span class="catalog-header-banner-carousel__text">{{ $text }}</span>
                                    </span>
                                @endif
                            </div>
                        @endif
                    @endforeach
                </div>
            </div>
            @if (count($headerBanners) > 1)
                <button type="button" class="catalog-header-banner-carousel__btn catalog-header-banner-carousel__btn--prev" data-header-banner-prev aria-label="{{ __('shop.catalog_header_banner_prev') }}">‹</button>
                <button type="button" class="catalog-header-banner-carousel__btn catalog-header-banner-carousel__btn--next" data-header-banner-next aria-label="{{ __('shop.catalog_header_banner_next') }}">›</button>
            @endif
        </div>
        @if (count($headerBanners) > 1)
            <div class="catalog-header-banner-carousel__dots" role="tablist" aria-label="{{ __('shop.catalog_header_banner_dots_aria') }}">
                @foreach ($headerBanners as $index => $slide)
                    <button
                        type="button"
                        class="catalog-header-banner-carousel__dot @if ($index === 0) is-active @endif"
                        data-header-banner-dot="{{ $index }}"
                        role="tab"
                        aria-selected="{{ $index === 0 ? 'true' : 'false' }}"
                        aria-label="{{ __('shop.catalog_header_banner_dot', ['n' => $index + 1]) }}"
                    ></button>
                @endforeach
            </div>
        @endif
    </div>
@endif

@once
@push('scripts')
<script src="{{ \App\Support\StorefrontAsset::url('js/storefront/header-banner.js') }}" defer></script>
@endpush
@endonce
