@extends('layouts.shop')

@section('title', __('shop.home_page_title'))
@section('meta_description', __('shop.home_meta_description'))
@section('canonical_url', route('home'))
@section('og_title', __('shop.home_og_title'))
@section('og_description', __('shop.home_og_description'))

@section('header_bottom')
    @include('catalog.partials.category-filters', ['inHeader' => true])
@endsection

@push('styles')
<style>
    .home-page {
        position: relative;
        isolation: isolate;
        width: min(80vw, 100%);
        margin: clamp(6px, 1vw, 14px) auto clamp(20px, 3vw, 36px);
    }
    .home-page .catalog-results--home-panels {
        width: 100%;
        margin: 0;
        padding: 0;
        background: transparent;
        box-shadow: none;
        border-radius: 0;
        gap: clamp(16px, 2.2vw, 26px);
    }
    .home-shop-panel__head {
        display: flex;
        align-items: flex-end;
        justify-content: space-between;
        gap: 1rem;
        margin-bottom: clamp(14px, 2vw, 20px);
        padding-bottom: 12px;
        border-bottom: 1px solid #eef2f7;
    }
    .home-shop-panel__title {
        margin: 0;
        color: #202124;
        font-size: clamp(1.25rem, 2.1vw, 1.65rem);
        font-weight: 700;
        letter-spacing: -0.035em;
        line-height: 1.15;
    }
    .home-shop-panel.home-shop-panel--hits .home-shop-panel__title {
        color: var(--color-cta);
    }
    .home-shop-panel.home-shop-panel--recommended .home-shop-panel__title {
        color: var(--color-buy);
    }
    .home-shop-panel__lead {
        margin: 0.35rem 0 0;
        color: #5f6368;
        font-size: 0.92rem;
        line-height: 1.45;
    }
    .home-section-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-height: 34px;
        padding: 0 14px;
        border-radius: 20px;
        color: #fff;
        font-size: 0.78rem;
        font-weight: 800;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        white-space: nowrap;
        box-shadow: 0 10px 24px rgba(15, 23, 42, 0.16);
    }
    .home-section-badge--popular {
        background: var(--color-cta);
        box-shadow: 0 10px 24px rgba(var(--color-cta-rgb), 0.28);
    }
    .home-section-badge--recommended {
        background: var(--color-buy);
        box-shadow: 0 10px 24px rgba(var(--color-buy-rgb), 0.28);
    }
    .home-shop-panel.home-shop-panel--premium {
        position: relative;
        overflow: hidden;
        padding: clamp(18px, 2.4vw, 30px);
        border-radius: 20px;
        border: 1px solid #e8eaed;
        background: #fff;
        box-shadow: 0 12px 30px rgba(15, 23, 42, 0.08);
        transition:
            transform .32s cubic-bezier(0.22, 1, 0.36, 1),
            box-shadow .32s cubic-bezier(0.22, 1, 0.36, 1),
            border-color .32s ease;
    }
    .home-shop-panel.home-shop-panel--premium::before {
        display: none;
    }
    @media (hover: hover) and (pointer: fine) {
        .home-benefits:hover,
        .home-shop-panel.home-shop-panel--premium:hover {
            transform: translateY(-3px);
            border-color: #dbe4ee;
            box-shadow: 0 18px 42px rgba(15, 23, 42, 0.13);
        }
    }
    .home-benefits {
        position: relative;
        overflow: hidden;
        display: flex;
        flex-direction: column;
        gap: 0;
        padding: clamp(14px, 2vw, 22px);
        border-radius: 20px;
        border: 1px solid #e8eaed;
        background:
            radial-gradient(circle at 8% 0%, rgba(54, 125, 241, 0.12), transparent 34%),
            linear-gradient(180deg, #fff 0%, #f8fbff 100%);
        box-shadow: 0 12px 30px rgba(15, 23, 42, 0.08);
        transition:
            transform .32s cubic-bezier(0.22, 1, 0.36, 1),
            box-shadow .32s cubic-bezier(0.22, 1, 0.36, 1),
            border-color .32s ease;
    }
    .home-benefits__head {
        margin-bottom: clamp(14px, 1.8vw, 20px);
        padding-bottom: 12px;
        border-bottom: 1px solid #eef2f7;
        display: flex;
        flex-direction: column;
        align-items: flex-start;
        gap: 0.15rem;
    }
    .home-benefits__title {
        margin: 0;
        color: #202124;
        font-size: clamp(1.22rem, 2.05vw, 1.58rem);
        font-weight: 700;
        letter-spacing: -0.035em;
        line-height: 1.15;
    }
    .home-benefits__lead {
        margin: 0.4rem 0 0;
        color: #5f6368;
        font-size: 0.92rem;
        line-height: 1.45;
        max-width: 52rem;
    }
    .home-benefits__grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 12px;
        min-width: 0;
    }
    .home-benefit-card {
        position: relative;
        min-height: 100%;
        padding: 16px;
        border-radius: 20px;
        border: 1px solid #eef2f7;
        background: rgba(255, 255, 255, 0.78);
        box-shadow: 0 8px 20px rgba(15, 23, 42, 0.06);
    }
    .home-benefit-card__icon {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 42px;
        height: 42px;
        margin-bottom: 10px;
        border-radius: 16px;
        background: #eaf2ff;
        color: #367df1;
        font-weight: 900;
        box-shadow: 0 8px 18px rgba(54, 125, 241, 0.16);
    }
    .home-benefit-card h3 {
        margin: 0 0 6px;
        color: #202124;
        font-size: 0.98rem;
        line-height: 1.25;
    }
    .home-benefit-card p {
        margin: 0;
        color: #5f6368;
        font-size: 0.86rem;
        line-height: 1.45;
    }
    /* 4 кольори з палітри сайту — виразніші заливки, градієнти та «світіння» */
    .home-benefits__grid .home-benefit-card:nth-child(1) {
        border: 2px solid rgba(54, 125, 241, 0.5);
        background: linear-gradient(
            165deg,
            rgba(54, 125, 241, 0.22) 0%,
            rgba(54, 125, 241, 0.08) 48%,
            rgba(255, 255, 255, 0.92) 100%
        );
        box-shadow:
            0 10px 28px rgba(54, 125, 241, 0.2),
            0 2px 8px rgba(15, 23, 42, 0.05);
    }
    .home-benefits__grid .home-benefit-card:nth-child(1) .home-benefit-card__icon {
        background: linear-gradient(145deg, #5b9dff 0%, #2f6fdf 50%, #2563eb 100%);
        color: #fff;
        box-shadow: 0 10px 24px rgba(54, 125, 241, 0.42);
    }
    .home-benefits__grid .home-benefit-card:nth-child(1) h3 {
        color: #1d4ed8;
    }
    .home-benefits__grid .home-benefit-card:nth-child(1) p {
        color: #3d4e75;
    }
    .home-benefits__grid .home-benefit-card:nth-child(2) {
        border: 2px solid rgba(var(--color-accent-rgb), 0.55);
        background: linear-gradient(
            165deg,
            rgba(var(--color-accent-rgb), 0.35) 0%,
            rgba(var(--color-accent-rgb), 0.14) 45%,
            rgba(255, 253, 248, 0.98) 100%
        );
        box-shadow:
            0 10px 28px rgba(var(--color-accent-rgb), 0.28),
            0 2px 8px rgba(15, 23, 42, 0.05);
    }
    .home-benefits__grid .home-benefit-card:nth-child(2) .home-benefit-card__icon {
        background: linear-gradient(145deg, #ffd54a 0%, var(--color-accent) 55%, #e29f00 100%);
        color: #fff;
        text-shadow: 0 1px 2px rgba(0, 0, 0, 0.25);
        box-shadow: 0 10px 24px rgba(var(--color-accent-rgb), 0.45);
    }
    .home-benefits__grid .home-benefit-card:nth-child(2) h3 {
        color: #92400e;
    }
    .home-benefits__grid .home-benefit-card:nth-child(2) p {
        color: #6b4f2a;
    }
    .home-benefits__grid .home-benefit-card:nth-child(3) {
        border: 2px solid rgba(var(--color-buy-rgb), 0.5);
        background: linear-gradient(
            165deg,
            rgba(var(--color-buy-rgb), 0.26) 0%,
            rgba(var(--color-buy-rgb), 0.1) 48%,
            rgba(248, 255, 250, 0.95) 100%
        );
        box-shadow:
            0 10px 28px rgba(var(--color-buy-rgb), 0.22),
            0 2px 8px rgba(15, 23, 42, 0.05);
    }
    .home-benefits__grid .home-benefit-card:nth-child(3) .home-benefit-card__icon {
        background: linear-gradient(145deg, #4caf50 0%, var(--color-buy) 50%, #2d8630 100%);
        color: #fff;
        box-shadow: 0 10px 24px rgba(var(--color-buy-rgb), 0.4);
    }
    .home-benefits__grid .home-benefit-card:nth-child(3) h3 {
        color: #166534;
    }
    .home-benefits__grid .home-benefit-card:nth-child(3) p {
        color: #3d5c42;
    }
    .home-benefits__grid .home-benefit-card:nth-child(4) {
        border: 2px solid rgba(var(--color-cta-rgb), 0.48);
        background: linear-gradient(
            165deg,
            rgba(var(--color-cta-rgb), 0.22) 0%,
            rgba(var(--color-cta-rgb), 0.09) 48%,
            rgba(255, 250, 250, 0.96) 100%
        );
        box-shadow:
            0 10px 28px rgba(var(--color-cta-rgb), 0.26),
            0 2px 8px rgba(15, 23, 42, 0.05);
    }
    .home-benefits__grid .home-benefit-card:nth-child(4) .home-benefit-card__icon {
        background: linear-gradient(145deg, #f87171 0%, var(--color-cta) 48%, #dc2626 100%);
        color: #fff;
        box-shadow: 0 10px 24px rgba(var(--color-cta-rgb), 0.38);
    }
    .home-benefits__grid .home-benefit-card:nth-child(4) h3 {
        color: #b91c1c;
    }
    .home-benefits__grid .home-benefit-card:nth-child(4) p {
        color: #5c3d3d;
    }
    @media (hover: hover) and (pointer: fine) {
        .home-benefits__grid .home-benefit-card {
            transition:
                transform 0.38s cubic-bezier(0.22, 1, 0.36, 1),
                box-shadow 0.38s cubic-bezier(0.22, 1, 0.36, 1),
                border-color 0.3s ease;
        }
        .home-benefits__grid .home-benefit-card .home-benefit-card__icon {
            transition:
                transform 0.4s cubic-bezier(0.34, 1.15, 0.64, 1),
                box-shadow 0.35s ease;
        }
        .home-benefits__grid .home-benefit-card:hover {
            transform: translateY(-8px);
        }
        .home-benefits__grid .home-benefit-card:hover .home-benefit-card__icon {
            transform: scale(1.1);
        }
        .home-benefits__grid .home-benefit-card:nth-child(1):hover {
            box-shadow:
                0 22px 48px rgba(54, 125, 241, 0.38),
                0 10px 24px rgba(54, 125, 241, 0.22),
                0 4px 12px rgba(15, 23, 42, 0.08);
            border-color: rgba(54, 125, 241, 0.78);
        }
        .home-benefits__grid .home-benefit-card:nth-child(1):hover .home-benefit-card__icon {
            box-shadow: 0 14px 32px rgba(54, 125, 241, 0.55);
        }
        .home-benefits__grid .home-benefit-card:nth-child(2):hover {
            box-shadow:
                0 22px 48px rgba(var(--color-accent-rgb), 0.4),
                0 10px 28px rgba(var(--color-accent-rgb), 0.22),
                0 4px 12px rgba(15, 23, 42, 0.08);
            border-color: rgba(var(--color-accent-rgb), 0.88);
        }
        .home-benefits__grid .home-benefit-card:nth-child(2):hover .home-benefit-card__icon {
            box-shadow: 0 14px 34px rgba(var(--color-accent-rgb), 0.58);
        }
        .home-benefits__grid .home-benefit-card:nth-child(3):hover {
            box-shadow:
                0 22px 48px rgba(var(--color-buy-rgb), 0.34),
                0 10px 26px rgba(var(--color-buy-rgb), 0.22),
                0 4px 12px rgba(15, 23, 42, 0.08);
            border-color: rgba(var(--color-buy-rgb), 0.82);
        }
        .home-benefits__grid .home-benefit-card:nth-child(3):hover .home-benefit-card__icon {
            box-shadow: 0 14px 32px rgba(var(--color-buy-rgb), 0.52);
        }
        .home-benefits__grid .home-benefit-card:nth-child(4):hover {
            box-shadow:
                0 22px 48px rgba(var(--color-cta-rgb), 0.38),
                0 10px 26px rgba(var(--color-cta-rgb), 0.24),
                0 4px 12px rgba(15, 23, 42, 0.08);
            border-color: rgba(var(--color-cta-rgb), 0.78);
        }
        .home-benefits__grid .home-benefit-card:nth-child(4):hover .home-benefit-card__icon {
            box-shadow: 0 14px 34px rgba(var(--color-cta-rgb), 0.52);
        }
    }
    .home-page .home-product-carousel__cell .product-card {
        border-radius: 20px;
        box-shadow: 0 12px 28px rgba(15, 23, 42, 0.09);
    }
    .home-page .home-product-carousel__cell .product-card:hover {
        transform: translateY(-8px);
        box-shadow:
            0 24px 44px rgba(15, 23, 42, 0.16),
            0 10px 22px rgba(15, 23, 42, 0.1);
    }
    .home-page .product-card__media {
        overflow: hidden;
    }
    .home-page .product-card__media img {
        transition: transform .45s cubic-bezier(0.22, 1, 0.36, 1), filter .3s ease;
    }
    .home-page .product-card:hover .product-card__media img {
        transform: scale(1.055);
        filter: saturate(1.08) brightness(1.02);
    }
    .home-page .product-card__badge--hit {
        background: var(--color-cta);
        box-shadow:
            0 10px 22px rgba(var(--color-cta-rgb), 0.3),
            0 0 0 1px rgba(255, 255, 255, 0.2) inset;
    }
    .home-page .product-card__badge--recommended {
        background: var(--color-buy);
        box-shadow:
            0 10px 22px rgba(var(--color-buy-rgb), 0.3),
            0 0 0 1px rgba(255, 255, 255, 0.2) inset;
    }
    .home-page .product-card__price:not(.product-card__price--sale) {
        color: #339a39;
        text-shadow: 0 8px 18px rgba(51, 154, 57, 0.16);
    }
    .home-page .product-card__like-react,
    .home-page .product-card__cart-react {
        border-radius: 20px;
        background: #f8fbff;
        box-shadow: 0 6px 16px rgba(15, 23, 42, 0.06);
    }
    .home-page .product-card__cart-react .product-card__cart-add:hover:not(:disabled) {
        transform: translateY(-1px) scale(1.08);
    }
    @media (max-width: 980px) {
        .home-benefits__grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }
    @media (max-width: 768px) {
        .home-page {
            width: 100%;
            margin-top: 0;
        }
        .home-benefits,
        .home-shop-panel.home-shop-panel--premium {
            border-radius: var(--shop-radius-catalog, 20px);
        }
        .home-shop-panel__head {
            align-items: flex-start;
            flex-direction: column;
        }
    }
    @media (max-width: 560px) {
        .home-benefits__grid {
            grid-template-columns: 1fr;
        }
    }
    @media (prefers-reduced-motion: reduce) {
        .home-benefits,
        .home-shop-panel.home-shop-panel--premium,
        .home-page .product-card__media img {
            transition: none;
        }
        .home-benefits:hover,
        .home-shop-panel.home-shop-panel--premium:hover,
        .home-page .product-card:hover,
        .home-page .product-card:hover .product-card__media img {
            transform: none;
        }
        .home-benefits__grid .home-benefit-card,
        .home-benefits__grid .home-benefit-card .home-benefit-card__icon {
            transition: none !important;
        }
        .home-benefits__grid .home-benefit-card:hover,
        .home-benefits__grid .home-benefit-card:hover .home-benefit-card__icon {
            transform: none !important;
        }
    }
</style>
@endpush

@section('content')
    <div class="home-page">
        <div
            id="catalog-results"
            class="catalog-results catalog-results--home-panels"
            data-catalog-base="{{ route('catalog.index') }}"
            data-favorite-ids="{{ e(json_encode($favoriteProductIds ?? [])) }}"
        >
        <section class="home-shop-panel home-shop-panel--premium home-shop-panel--hits" aria-labelledby="home-hits-heading">
            <div class="home-shop-panel__head">
                <div>
                    <h2 id="home-hits-heading" class="home-shop-panel__title">{{ __('shop.home_hits_title') }}</h2>
                    <p class="home-shop-panel__lead">{{ __('shop.home_hits_lead') }}</p>
                </div>
                <span class="home-section-badge home-section-badge--popular">{{ __('shop.home_badge_popular') }}</span>
            </div>
            @if ($hitsProducts->isEmpty())
                <p class="home-shop-panel__empty">{{ __('shop.home_hits_empty') }}</p>
            @else
                <div
                    class="home-product-carousel"
                    data-home-carousel
                    role="region"
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
                    <div class="home-product-carousel__viewport" tabindex="0">
                        <div class="home-product-carousel__track">
                            @foreach ($hitsProducts as $product)
                                <div class="home-product-carousel__cell">
                                    @include('catalog.partials.product-card', [
                                        'listing' => $product,
                                        'listingQuotes' => $listingQuotes ?? [],
                                        'bundleQuotes' => [],
                                        'cardImagePriority' => $loop->index,
                                        'homeCardBadge' => __('shop.home_badge_hit'),
                                        'homeCardBadgeClass' => 'hit',
                                    ])
                                </div>
                            @endforeach
                        </div>
                    </div>
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

        <section class="home-benefits" aria-labelledby="home-benefits-heading">
            <header class="home-benefits__head">
                <h2 id="home-benefits-heading" class="home-benefits__title">{{ __('shop.home_benefits_title') }}</h2>
                <p class="home-benefits__lead">
                    {{ __('shop.home_benefits_lead') }}
                </p>
            </header>
            <div class="home-benefits__grid">
            <article class="home-benefit-card">
                <span class="home-benefit-card__icon">1</span>
                <h3>{{ __('shop.home_benefit_1_title') }}</h3>
                <p>{{ __('shop.home_benefit_1_text') }}</p>
            </article>
            <article class="home-benefit-card">
                <span class="home-benefit-card__icon">2</span>
                <h3>{{ __('shop.home_benefit_2_title') }}</h3>
                <p>{{ __('shop.home_benefit_2_text') }}</p>
            </article>
            <article class="home-benefit-card">
                <span class="home-benefit-card__icon">3</span>
                <h3>{{ __('shop.home_benefit_3_title') }}</h3>
                <p>{{ __('shop.home_benefit_3_text') }}</p>
            </article>
            <article class="home-benefit-card">
                <span class="home-benefit-card__icon">4</span>
                <h3>{{ __('shop.home_benefit_4_title') }}</h3>
                <p>{{ __('shop.home_benefit_4_text') }}</p>
            </article>
            </div>
        </section>

        <section class="home-shop-panel home-shop-panel--premium home-shop-panel--recommended" aria-labelledby="home-recommended-heading">
            <div class="home-shop-panel__head">
                <div>
                    <h2 id="home-recommended-heading" class="home-shop-panel__title">{{ __('shop.home_rec_title') }}</h2>
                    <p class="home-shop-panel__lead">{{ __('shop.home_rec_lead') }}</p>
                </div>
                <span class="home-section-badge home-section-badge--recommended">{{ __('shop.home_badge_suggested') }}</span>
            </div>
            @if ($recommendedProducts->isEmpty())
                <p class="home-shop-panel__empty">{{ __('shop.home_rec_empty') }}</p>
            @else
                <div
                    class="home-product-carousel"
                    data-home-carousel
                    role="region"
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
                    <div class="home-product-carousel__viewport" tabindex="0">
                        <div class="home-product-carousel__track">
                            @foreach ($recommendedProducts as $product)
                                <div class="home-product-carousel__cell">
                                    @include('catalog.partials.product-card', [
                                        'listing' => $product,
                                        'listingQuotes' => $listingQuotes ?? [],
                                        'bundleQuotes' => [],
                                        'cardImagePriority' => $loop->index,
                                        'homeCardBadge' => __('shop.home_badge_recommended'),
                                        'homeCardBadgeClass' => 'recommended',
                                    ])
                                </div>
                            @endforeach
                        </div>
                    </div>
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
    .home-page .home-shop-panel .home-product-carousel {
        --home-carousel-cols: 4;
    }
    @media (max-width: 1380px) {
        .home-page .home-shop-panel .home-product-carousel {
            --home-carousel-cols: 3;
        }
    }
    @media (max-width: 980px) {
        .home-page .home-shop-panel .home-product-carousel {
            --home-carousel-cols: 2;
        }
    }
    @media (max-width: 560px) {
        .home-page .home-shop-panel .home-product-carousel {
            --home-carousel-cols: 1.08;
            --home-carousel-gap: 12px;
        }
    }
</style>
@endpush
