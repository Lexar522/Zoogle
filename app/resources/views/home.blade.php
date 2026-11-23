@extends('layouts.shop')

@php
    $homeSeo = \App\Models\ShopSeoSetting::home();
@endphp
@section('title', $homeSeo['title'])
@section('meta_description', $homeSeo['description'])
@section('og_title', $homeSeo['og_title'])
@section('og_description', $homeSeo['og_description'])
@section('canonical_url', route('home'))

@push('meta')
    @php
        $homeSameAs = [];
        $homeContacts = [];
        if (\App\Support\SchemaCache::hasTable('shop_integration_settings')) {
            $homeContacts = \App\Support\ShopHeaderContacts::storefrontItems();
        }
        foreach ($homeContacts as $homeContact) {
            $homeHref = (string) ($homeContact['href'] ?? '');
            if (in_array($homeContact['kind'] ?? '', ['instagram', 'tiktok', 'telegram', 'facebook'], true)
                && str_starts_with($homeHref, 'http')) {
                $homeSameAs[] = $homeHref;
            }
        }
        $homeSchema = [
            '@context' => 'https://schema.org',
            '@graph' => [
                [
                    '@type' => 'Organization',
                    'name' => 'ZOOGLE',
                    'url' => route('home'),
                    'logo' => asset('images/og-share.png'),
                ],
                [
                    '@type' => 'WebSite',
                    'name' => 'ZOOGLE',
                    'url' => route('home'),
                    'inLanguage' => 'uk',
                    'potentialAction' => [
                        '@type' => 'SearchAction',
                        'target' => route('catalog.index').'?q={search_term_string}',
                        'query-input' => 'required name=search_term_string',
                    ],
                ],
            ],
        ];
        if ($homeSameAs !== []) {
            $homeSchema['@graph'][0]['sameAs'] = array_values(array_unique($homeSameAs));
        }
    @endphp
    <script type="application/ld+json">
{!! json_encode($homeSchema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) !!}
    </script>
@endpush

@section('header_bottom')
    @include('catalog.partials.category-filters', ['inHeader' => true])
@endsection

@push('styles')
@vite('resources/css/storefront/home.css')
@endpush

@section('content')
    <div class="home-page">
        <div
            id="catalog-results"
            class="catalog-results catalog-results--home-panels"
            data-catalog-base="{{ route('catalog.index') }}"
            data-favorite-ids="{{ e(json_encode($favoriteProductIds ?? [])) }}"
        >
        @php
            $homeProductSections = is_array($homeProductSections ?? null)
                ? $homeProductSections
                : \App\Models\ShopHomeProductSection::forStorefront();
            $homeHitsCopy = is_array($homeProductSections['hits'] ?? null) ? $homeProductSections['hits'] : [];
            $homeRecommendedCopy = is_array($homeProductSections['recommended'] ?? null) ? $homeProductSections['recommended'] : [];
        @endphp
        @if ($homeHitsCopy['enabled'] ?? true)
        <section class="home-shop-panel home-shop-panel--premium home-shop-panel--hits" aria-labelledby="home-hits-heading">
            <div class="home-shop-panel__head">
                <div>
                    <h2 id="home-hits-heading" class="home-shop-panel__title">{{ $homeHitsCopy['title'] ?? __('shop.home_hits_title') }}</h2>
                    @if (trim((string) ($homeHitsCopy['lead'] ?? '')) !== '')
                        <p class="home-shop-panel__lead">{{ $homeHitsCopy['lead'] }}</p>
                    @endif
                </div>
                <span class="home-section-badge home-section-badge--popular">{{ __('shop.home_badge_popular') }}</span>
            </div>
            @if ($hitsProducts->isEmpty())
                <p class="home-shop-panel__empty">{{ __('shop.home_hits_empty') }}</p>
            @else
                <div
                    class="home-product-carousel home-product-carousel--mobile-feed home-product-carousel--show-excerpt"
                    data-home-carousel
                    role="region"
                    data-carousel-roledescription="{{ __('shop.aria_carousel') }}"
                    data-list-roledescription="{{ __('shop.aria_product_list') }}"
                    aria-roledescription="{{ __('shop.aria_carousel') }}"
                    aria-labelledby="home-hits-heading"
                >
                    <button
                        type="button"
                        class="home-product-carousel__btn home-product-carousel__btn--prev"
                        aria-label="{{ __('shop.home_carousel_prev') }}"
                    >
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path d="M15 18l-6-6 6-6" />
                        </svg>
                    </button>
                    @include('home.partials.product-swipe-pages', [
                        'products' => $hitsProducts,
                        'badge' => __('shop.home_badge_hit'),
                        'badgeClass' => 'hit',
                    ])
                    <button
                        type="button"
                        class="home-product-carousel__btn home-product-carousel__btn--next"
                        aria-label="{{ __('shop.home_carousel_next') }}"
                    >
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path d="M9 18l6-6-6-6" />
                        </svg>
                    </button>
                </div>
            @endif
        </section>
        @endif

        @if ($homeRecommendedCopy['enabled'] ?? true)
        <section class="home-shop-panel home-shop-panel--premium home-shop-panel--recommended" aria-labelledby="home-recommended-heading">
            <div class="home-shop-panel__head">
                <div>
                    <h2 id="home-recommended-heading" class="home-shop-panel__title">{{ $homeRecommendedCopy['title'] ?? __('shop.home_rec_title') }}</h2>
                    @if (trim((string) ($homeRecommendedCopy['lead'] ?? '')) !== '')
                        <p class="home-shop-panel__lead">{{ $homeRecommendedCopy['lead'] }}</p>
                    @endif
                </div>
                <span class="home-section-badge home-section-badge--recommended">{{ __('shop.home_badge_suggested') }}</span>
            </div>
            @if ($recommendedProducts->isEmpty())
                <p class="home-shop-panel__empty">{{ __('shop.home_rec_empty') }}</p>
            @else
                <div
                    class="home-product-carousel home-product-carousel--mobile-feed home-product-carousel--show-excerpt"
                    data-home-carousel
                    role="region"
                    data-carousel-roledescription="{{ __('shop.aria_carousel') }}"
                    data-list-roledescription="{{ __('shop.aria_product_list') }}"
                    aria-roledescription="{{ __('shop.aria_carousel') }}"
                    aria-labelledby="home-recommended-heading"
                >
                    <button
                        type="button"
                        class="home-product-carousel__btn home-product-carousel__btn--prev"
                        aria-label="{{ __('shop.home_carousel_prev') }}"
                    >
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path d="M15 18l-6-6 6-6" />
                        </svg>
                    </button>
                    @include('home.partials.product-swipe-pages', [
                        'products' => $recommendedProducts,
                        'badge' => __('shop.home_badge_recommended'),
                        'badgeClass' => 'recommended',
                    ])
                    <button
                        type="button"
                        class="home-product-carousel__btn home-product-carousel__btn--next"
                        aria-label="{{ __('shop.home_carousel_next') }}"
                    >
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path d="M9 18l6-6-6-6" />
                        </svg>
                    </button>
                </div>
            @endif
        </section>
        @endif

        @php
            $homeBenefits = is_array($homeBenefits ?? null) ? $homeBenefits : \App\Models\ShopHomeBenefit::forStorefront();
            $homeBenefitItems = is_array($homeBenefits['items'] ?? null) ? $homeBenefits['items'] : [];
            $homeBenefitsVisible = ! empty($homeBenefits['enabled'])
                && (
                    trim((string) ($homeBenefits['title'] ?? '')) !== ''
                    || trim((string) ($homeBenefits['lead'] ?? '')) !== ''
                    || $homeBenefitItems !== []
                );
        @endphp
        @if ($homeBenefitsVisible)
        <section class="home-benefits" @if (trim((string) ($homeBenefits['title'] ?? '')) !== '') aria-labelledby="home-benefits-heading" @endif>
            @if (trim((string) ($homeBenefits['title'] ?? '')) !== '' || trim((string) ($homeBenefits['lead'] ?? '')) !== '')
            <header class="home-benefits__head">
                @if (trim((string) ($homeBenefits['title'] ?? '')) !== '')
                    <h2 id="home-benefits-heading" class="home-benefits__title">{{ $homeBenefits['title'] }}</h2>
                @endif
                @if (trim((string) ($homeBenefits['lead'] ?? '')) !== '')
                    <p class="home-benefits__lead">{{ $homeBenefits['lead'] }}</p>
                @endif
            </header>
            @endif
            @if ($homeBenefitItems !== [])
            <div class="home-benefits__grid">
                @foreach ($homeBenefitItems as $benefit)
                    <article class="home-benefit-card" style="{{ $benefit['style'] ?? '' }}">
                        <span class="home-benefit-card__icon">{{ $loop->iteration }}</span>
                        @if (trim((string) ($benefit['title'] ?? '')) !== '')
                            <h3>{{ $benefit['title'] }}</h3>
                        @endif
                        @if (trim((string) ($benefit['text'] ?? '')) !== '')
                            <p>{{ $benefit['text'] }}</p>
                        @endif
                    </article>
                @endforeach
            </div>
            @endif
        </section>
        @endif
        </div>
    </div>
@endsection

@push('scripts')
    @include('catalog.partials.shop-catalog-scripts')
@endpush
@include('home.partials.home-product-carousel')

@push('styles')
<style>
    /* Карусель хітів/рекомендованих: 4 картки у вікні (після базових стилів partial). */
    .home-page .home-shop-panel .home-product-carousel:not(.home-product-carousel--mobile-feed) {
        --home-carousel-cols: 4;
    }
    @media (max-width: 1380px) {
        .home-page .home-shop-panel .home-product-carousel:not(.home-product-carousel--mobile-feed) {
            --home-carousel-cols: 3;
        }
    }
    @media (max-width: 980px) {
        .home-page .home-shop-panel .home-product-carousel:not(.home-product-carousel--mobile-feed) {
            --home-carousel-cols: 2;
        }
    }
    @media (max-width: 768px) {
        .home-page .home-shop-panel .home-product-carousel:not(.home-product-carousel--mobile-feed) {
            --home-carousel-cols: 1.12;
            --home-carousel-gap: 12px;
        }
    }
</style>
@endpush
