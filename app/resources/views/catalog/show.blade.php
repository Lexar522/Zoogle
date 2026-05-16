@extends('layouts.shop')

@section('title', mt($listing->title) . ' — ZOOGLE')
@section('meta_description', $pdpMetaDescription ?? mt($listing->title))
@section('canonical_url', route('catalog.show', $listing->slug))
@section('og_type', 'product')
@section('og_title', mt($listing->title))
@section('og_description', $pdpMetaDescription ?? mt($listing->title))
@if (! empty($pdpOgImageUrl))
    @section('og_image', $pdpOgImageUrl)
@endif

@push('meta')
    @php
        $schemaAvailability = match (($listingStockMode ?? 'none')) {
            'ok', 'low' => 'https://schema.org/InStock',
            'preorder' => 'https://schema.org/PreOrder',
            default => 'https://schema.org/OutOfStock',
        };

        $schemaProduct = [
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => mt($listing->title),
            'description' => $pdpMetaDescription ?? mt($listing->title),
            'sku' => filled(trim((string) ($listing->sku ?? ''))) ? (string) $listing->sku : (string) $listing->slug,
            'url' => route('catalog.show', $listing->slug),
            'offers' => [
                '@type' => 'AggregateOffer',
                'priceCurrency' => 'UAH',
                'lowPrice' => number_format((float) ($pdpOfferLowPrice ?? $listingBasePrice ?? 0), 2, '.', ''),
                'highPrice' => number_format((float) ($pdpOfferHighPrice ?? $listingBasePrice ?? 0), 2, '.', ''),
                'offerCount' => 1,
                'availability' => $schemaAvailability,
            ],
        ];

        if (! empty($pdpOgImageUrl)) {
            $schemaProduct['image'] = [$pdpOgImageUrl];
        }
    @endphp
    <script type="application/ld+json">
{!! json_encode($schemaProduct, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}
    </script>
@endpush

@php
    $galleryPhotos = is_array($galleryPhotos ?? null) ? $galleryPhotos : [];

    $categoryDisplayForPage = $categoryDisplayForPage ?? $categoryLabel ?? null;
    $listingBasePrice = (float) ($listingBasePrice ?? 0);
    $listingBaseCompareAt = isset($listingBaseCompareAt) && $listingBaseCompareAt !== null ? (float) $listingBaseCompareAt : null;
    $listingStockMode = $listingStockMode ?? 'none';

    $defaultOptionSelection = $defaultOptionSelection ?? [];
    $optionsPreselectedFromVariant = $optionsPreselectedFromVariant ?? false;
    $productOptionsDisplay = is_array($productOptionsDisplay ?? null) ? $productOptionsDisplay : [];
    $storefrontDefaultVariantId = null;
    $needsBareVariantPicker = false;
    $barePickerPreselect = null;
    $categoryBreadcrumb = is_array($categoryBreadcrumb ?? null) ? $categoryBreadcrumb : [];
    $breadcrumbQueryBase = array_filter([
        'q' => request('q'),
        'on_sale' => request()->boolean('on_sale') ? 1 : null,
    ], fn ($v) => $v !== null && $v !== '');
@endphp

@push('styles')
<style>
    .pdp-related-section.home-shop-panel {
        display: block;
        width: calc(100% - (var(--pdp-block-inset) * 2));
        max-width: 100%;
        min-width: 0;
        box-sizing: border-box;
        margin: 22px auto 0;
        border-radius: 20px;
        box-shadow: 0 12px 30px rgba(15, 23, 42, 0.08);
        transition:
            transform .32s cubic-bezier(0.22, 1, 0.36, 1),
            box-shadow .32s cubic-bezier(0.22, 1, 0.36, 1),
            border-color .32s ease;
        will-change: transform, box-shadow;
    }
    .pdp-related-section .home-product-carousel {
        --home-carousel-cols: 5;
        width: 100%;
        max-width: 100%;
        min-width: 0;
        box-sizing: border-box;
    }
    .pdp-related-section .home-product-carousel__viewport {
        width: 100%;
        max-width: 100%;
        min-width: 0;
        box-sizing: border-box;
    }
    .pdp-related-section .home-product-carousel__track {
        max-width: none;
    }
    @media (max-width: 1180px) {
        .pdp-related-section .home-product-carousel {
            --home-carousel-cols: 4;
        }
    }
    @media (max-width: 980px) {
        .pdp-related-section .home-product-carousel {
            --home-carousel-cols: 2;
        }
    }
    @media (max-width: 560px) {
        .pdp-related-section .home-product-carousel {
            --home-carousel-cols: 1.08;
        }
    }
    @media (hover: hover) and (pointer: fine) {
        .pdp-related-section.home-shop-panel:hover {
            transform: translateY(-3px);
            border-color: #dbe4ee;
            box-shadow: 0 18px 42px rgba(15, 23, 42, 0.13);
        }
    }
    @media (prefers-reduced-motion: reduce) {
        .pdp-related-section.home-shop-panel {
            transition: box-shadow .24s ease, border-color .24s ease;
            will-change: auto;
        }

        .pdp-related-section.home-shop-panel:hover {
            transform: none;
        }
    }
    .product-page {
        --pdp-block-inset: clamp(0px, 0.7vw, 10px);
        color: #202124;
        width: min(80vw, 100%);
        margin: clamp(6px, 1vw, 14px) auto clamp(12px, 2vw, 28px);
        box-sizing: border-box;
        padding: 0;
    }
    .product-page a { color: #1a73e8; text-decoration: none; }
    .product-page a:hover { color: #174ea6; }
    .product-breadcrumb { font-size: 13px; margin-bottom: 20px; }
    .product-breadcrumb {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 6px;
        color: #5f6368;
    }
    .product-breadcrumb__sep { opacity: .65; }
    .product-breadcrumb__selected {
        color: #3c4043;
        font-weight: 500;
    }
    .product-category-chain {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        align-items: center;
        min-width: 0;
        width: calc(100% - (var(--pdp-block-inset) * 2));
        margin: 0 0 1rem;
        margin-inline: auto;
    }
    .product-category-chain a {
        touch-action: manipulation;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        margin: 0;
        padding: 0 16px;
        min-height: 40px;
        border: 0;
        border-radius: 20px;
        font-size: 0.875rem;
        font-weight: 700;
        font-family: inherit;
        line-height: 1.2;
        text-decoration: none;
        cursor: pointer;
        box-shadow:
            0 12px 24px rgba(15, 23, 42, 0.08),
            0 4px 10px rgba(15, 23, 42, 0.04);
        transition:
            transform 0.5s cubic-bezier(0.16, 1, 0.3, 1),
            box-shadow 0.5s cubic-bezier(0.16, 1, 0.3, 1),
            background-color 0.2s ease,
            color 0.2s ease,
            filter 0.2s ease;
    }
    .product-category-chain a:hover {
        transform: translateY(-6px);
        box-shadow:
            0 28px 56px rgba(15, 23, 42, 0.22),
            0 14px 32px rgba(15, 23, 42, 0.16),
            0 6px 14px rgba(15, 23, 42, 0.1);
    }
    .product-category-chain a:active {
        transform: translateY(-2px) scale(0.97);
    }
    .product-category-chain a:nth-child(6n+1) {
        background-color: rgba(59, 130, 242, 0.12);
        color: #3b82f2;
    }
    .product-category-chain a:nth-child(6n+1):hover {
        background-color: #3b82f2;
        color: #fff;
    }
    .product-category-chain a:nth-child(6n+1):focus-visible {
        outline: 2px solid #3b82f2;
        outline-offset: 2px;
    }
    .product-category-chain a:nth-child(6n+2) {
        background-color: rgba(237, 54, 38, 0.12);
        color: #ed3626;
    }
    .product-category-chain a:nth-child(6n+2):hover {
        background-color: #ed3626;
        color: #fff;
    }
    .product-category-chain a:nth-child(6n+2):focus-visible {
        outline: 2px solid #ed3626;
        outline-offset: 2px;
    }
    .product-category-chain a:nth-child(6n+3) {
        background-color: rgba(252, 181, 1, 0.18);
        color: #fcb501;
    }
    .product-category-chain a:nth-child(6n+3):hover {
        background-color: #fcb501;
        color: #fff;
    }
    .product-category-chain a:nth-child(6n+3):focus-visible {
        outline: 2px solid #fcb501;
        outline-offset: 2px;
    }
    .product-category-chain a:nth-child(6n+4) {
        background-color: rgba(57, 125, 234, 0.12);
        color: #397dea;
    }
    .product-category-chain a:nth-child(6n+4):hover {
        background-color: #397dea;
        color: #fff;
    }
    .product-category-chain a:nth-child(6n+4):focus-visible {
        outline: 2px solid #397dea;
        outline-offset: 2px;
    }
    .product-category-chain a:nth-child(6n+5) {
        background-color: rgba(51, 152, 59, 0.12);
        color: #33983b;
    }
    .product-category-chain a:nth-child(6n+5):hover {
        background-color: #33983b;
        color: #fff;
    }
    .product-category-chain a:nth-child(6n+5):focus-visible {
        outline: 2px solid #33983b;
        outline-offset: 2px;
    }
    .product-category-chain a:nth-child(6n+6) {
        background-color: rgba(238, 60, 43, 0.12);
        color: #ee3c2b;
    }
    .product-category-chain a:nth-child(6n+6):hover {
        background-color: #ee3c2b;
        color: #fff;
    }
    .product-category-chain a:nth-child(6n+6):focus-visible {
        outline: 2px solid #ee3c2b;
        outline-offset: 2px;
    }
    @media (prefers-reduced-motion: reduce) {
        .product-category-chain a {
            transition:
                background-color 0.2s ease,
                color 0.2s ease,
                filter 0.2s ease;
        }
        .product-category-chain a:hover,
        .product-category-chain a:active {
            transform: none;
        }
    }
    .product-grid {
        display: grid;
        grid-template-columns: minmax(0, 1.08fr) minmax(360px, 0.92fr);
        gap: clamp(12px, 1.6vw, 22px);
        width: calc(100% - (var(--pdp-block-inset) * 2));
        max-width: min(1680px, 100%);
        margin: 0 auto;
        align-items: start;
    }
    @media (max-width: 900px) {
        .product-grid { grid-template-columns: 1fr; }
    }
    .product-main-column {
        min-width: 0;
        display: grid;
        gap: clamp(12px, 1.4vw, 18px);
    }
    .product-gallery-column {
        min-width: 0;
        width: 100%;
    }
    .product-gallery-card,
    .product-detail-card,
    .product-desc,
    .product-care-section {
        border-radius: 20px;
        border: 1px solid #e8eaed;
        background: #fff;
        box-shadow: 0 12px 30px rgba(15, 23, 42, 0.08);
        transition:
            transform .32s cubic-bezier(0.22, 1, 0.36, 1),
            box-shadow .32s cubic-bezier(0.22, 1, 0.36, 1),
            border-color .32s ease;
        will-change: transform, box-shadow;
    }
    @media (hover: hover) and (pointer: fine) {
        .product-gallery-card:hover,
        .product-detail-card:hover,
        .product-desc:hover,
        .product-care-section:hover {
            transform: translateY(-3px);
            border-color: #dbe4ee;
            box-shadow: 0 18px 42px rgba(15, 23, 42, 0.13);
        }
    }
    @media (prefers-reduced-motion: reduce) {
        .product-gallery-card,
        .product-detail-card,
        .product-desc,
        .product-care-section {
            transition: box-shadow .24s ease, border-color .24s ease;
            will-change: auto;
        }

        .product-gallery-card:hover,
        .product-detail-card:hover,
        .product-desc:hover,
        .product-care-section:hover {
            transform: none;
        }
    }
    .product-gallery-card {
        padding: clamp(12px, 1.6vw, 18px);
    }
    .product-detail-column {
        min-width: 0;
        width: 100%;
    }
    .product-detail-card {
        padding: clamp(20px, 2.6vw, 34px);
    }
    @media (min-width: 1024px) {
        .product-detail-column {
            position: sticky;
            top: clamp(16px, calc(var(--header-sticky-offset, 0px) + 12px), 96px);
            align-self: start;
        }
        .product-detail-card {
            max-height: calc(100vh - clamp(16px, calc(var(--header-sticky-offset, 0px) + 12px), 96px) - 16px);
            overflow-y: auto;
        }
    }
    .product-gallery-main {
        background: #f1f3f4;
        border-radius: 12px;
        border: 1px solid #e8eaed;
        aspect-ratio: 1;
        width: 100%;
        max-width: 100%;
        box-sizing: border-box;
        display: flex;
        align-items: center;
        justify-content: center;
        overflow: hidden;
        box-shadow: 0 1px 2px rgba(60, 64, 67, 0.12);
        transition: box-shadow .2s ease, border-color .2s ease;
    }
    .product-gallery-main:hover {
        border-color: #dadce0;
        box-shadow: 0 2px 8px rgba(60, 64, 67, 0.16);
    }
    .product-gallery-main img {
        width: 100%;
        height: 100%;
        object-position: center;
        transition: transform .2s ease;
    }
    .product-gallery-main--multi-img #product-main-img {
        transition:
            opacity 0.28s ease,
            transform 0.2s ease;
    }
    @media (prefers-reduced-motion: reduce) {
        .product-gallery-main--multi-img #product-main-img {
            transition: transform 0.2s ease;
        }
    }
    .product-gallery-main--fit-cover img { object-fit: cover; }
    .product-gallery-main:hover img {
        transform: scale(1.015);
    }
    .product-gallery-main .placeholder {
        color: #5f6368;
        font-size: 14px;
    }
    .product-thumbs {
        display: flex;
        gap: 8px;
        margin-top: 10px;
        flex-wrap: wrap;
    }
    .product-thumbs button {
        width: 64px;
        height: 64px;
        padding: 0;
        border-radius: 8px;
        border: 1px solid #dadce0;
        background: #fff;
        cursor: pointer;
        overflow: hidden;
        box-shadow: 0 1px 2px rgba(60, 64, 67, 0.1);
        transition: border-color .15s ease, box-shadow .15s ease, transform .15s ease;
    }
    .product-thumbs button:hover {
        border-color: #1a73e8;
        box-shadow: 0 1px 4px rgba(60, 64, 67, 0.2);
        transform: translateY(-1px);
    }
    .product-thumbs button.active {
        border-color: #1a73e8;
        box-shadow: 0 0 0 1px rgba(26, 115, 232, 0.35);
    }
    .product-thumbs img {
        width: 100%;
        height: 100%;
        object-position: center;
    }
    .product-thumbs--fit-cover img { object-fit: cover; }
    .product-title { font-size: clamp(1.5rem, 3vw, 2rem); font-weight: 700; margin: 0 0 12px; line-height: 1.2; color: #367df1; }
    .product-badges { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 20px; }
    .badge {
        font-size: 12px;
        font-weight: 600;
        padding: 6px 12px;
        border-radius: 8px;
    }
    .badge.ok { background: #dcfae6; color: #067647; }
    .badge.pre { background: #fffaeb; color: #b54708; }
    .badge.low { background: #fef3c7; color: #92400e; }
    .badge.no { background: #fee4e2; color: #b42318; }
    .product-price-row { margin-bottom: 8px; }
    .product-price {
        font-size: 2rem;
        font-weight: 700;
        color: #339a39;
        display: inline-flex;
        flex-wrap: wrap;
        align-items: baseline;
        gap: 10px 14px;
        will-change: transform, filter, opacity;
    }
    .product-price--up {
        animation: productPriceRise .32s cubic-bezier(0.22, 1, 0.36, 1);
    }
    .product-price--down {
        animation: productPriceDrop .32s cubic-bezier(0.22, 1, 0.36, 1);
    }
    .product-price__old { font-size: 1.35rem; font-weight: 500; color: #80868b; text-decoration: line-through; }
    .product-price__current { color: #4ade80; }
    @keyframes productPriceRise {
        0% { transform: translateY(0) scale(1); filter: brightness(1); }
        40% { transform: translateY(-2px) scale(1.035); filter: brightness(1.08); }
        100% { transform: translateY(0) scale(1); filter: brightness(1); }
    }
    @keyframes productPriceDrop {
        0% { transform: translateY(0) scale(1); opacity: 1; }
        40% { transform: translateY(1px) scale(0.985); opacity: 0.9; }
        100% { transform: translateY(0) scale(1); opacity: 1; }
    }
    @media (prefers-reduced-motion: reduce) {
        .product-price,
        .product-price--up,
        .product-price--down {
            animation: none !important;
        }
    }
    .product-sku { font-size: 13px; color: #5f6368; margin-bottom: 28px; }
    .product-options-panel { margin-top: 8px; padding-top: 20px; border-top: 1px solid #e8eaed; }
    .product-options-heading { font-size: 15px; font-weight: 600; color: #202124; margin: 0 0 16px; letter-spacing: 0.01em; }
    .opt-group { margin-bottom: 22px; }
    .opt-group:last-child { margin-bottom: 0; }
    .opt-label { font-size: 13px; font-weight: 600; color: #5f6368; margin-bottom: 10px; display: block; }
    .opt-chips { display: flex; flex-wrap: wrap; gap: 8px; }
    .opt-chip {
        border: 1px solid #dadce0;
        background: #fff;
        color: #202124;
        padding: 10px 16px;
        border-radius: 20px;
        font-size: 14px;
        cursor: pointer;
        box-shadow: 0 4px 10px rgba(15, 23, 42, 0.05);
        transition:
            transform .26s cubic-bezier(0.22, 1, 0.36, 1),
            border-color .18s ease,
            background .18s ease,
            color .18s ease,
            box-shadow .26s cubic-bezier(0.22, 1, 0.36, 1),
            filter .18s ease;
        will-change: transform, box-shadow;
    }
    .opt-chip:hover,
    .opt-chip:focus-visible {
        border-color: rgba(26, 115, 232, 0.55);
        transform: translateY(-3px) scale(1.035);
        box-shadow:
            0 12px 24px rgba(15, 23, 42, 0.14),
            0 0 0 4px rgba(26, 115, 232, 0.08);
    }
    .opt-chip:focus-visible {
        outline: 2px solid #1a73e8;
        outline-offset: 3px;
    }
    .opt-chip.active {
        background: #1a73e8;
        color: #fff;
        border-color: #1a73e8;
        transform: translateY(-2px) scale(1.04);
        box-shadow:
            0 14px 30px rgba(26, 115, 232, 0.26),
            0 0 0 4px rgba(26, 115, 232, 0.14);
        filter: saturate(1.08);
    }
    .opt-chip.active:hover,
    .opt-chip.active:focus-visible {
        transform: translateY(-4px) scale(1.06);
        box-shadow:
            0 18px 34px rgba(26, 115, 232, 0.32),
            0 0 0 5px rgba(26, 115, 232, 0.18);
    }
    .opt-chip:disabled {
        opacity: .4;
        cursor: not-allowed;
        transform: none;
        box-shadow: none;
        filter: none;
    }
    @media (prefers-reduced-motion: reduce) {
        .opt-chip {
            transition:
                border-color .18s ease,
                background .18s ease,
                color .18s ease,
                box-shadow .18s ease,
                filter .18s ease;
            will-change: auto;
        }

        .opt-chip:hover,
        .opt-chip:focus-visible,
        .opt-chip.active,
        .opt-chip.active:hover,
        .opt-chip.active:focus-visible {
            transform: none;
        }
    }
    .opt-colors { display: flex; flex-wrap: wrap; gap: 14px; align-items: center; }
    .opt-swatch {
        position: relative;
        width: 44px;
        height: 44px;
        border-radius: 50%;
        border: 2px solid rgba(255, 255, 255, 0.95);
        cursor: pointer;
        padding: 0;
        box-sizing: border-box;
        box-shadow:
            0 0 0 1px rgba(15, 23, 42, 0.18),
            0 6px 14px rgba(15, 23, 42, 0.12);
        transition:
            transform .26s cubic-bezier(0.22, 1, 0.36, 1),
            box-shadow .26s cubic-bezier(0.22, 1, 0.36, 1),
            border-color .18s ease,
            filter .18s ease;
        will-change: transform, box-shadow;
    }
    .opt-swatch::before {
        content: attr(aria-label);
        position: absolute;
        left: 50%;
        bottom: calc(100% + 8px);
        z-index: 4;
        max-width: 160px;
        padding: 5px 8px;
        border-radius: 999px;
        background: rgba(15, 23, 42, 0.92);
        color: #fff;
        font-size: 11px;
        font-weight: 700;
        line-height: 1.2;
        white-space: nowrap;
        opacity: 0;
        pointer-events: none;
        transform: translate(-50%, 4px) scale(0.96);
        transition:
            opacity .18s ease,
            transform .22s cubic-bezier(0.22, 1, 0.36, 1);
    }
    .opt-swatch::after {
        content: "";
        position: absolute;
        inset: 50% auto auto 50%;
        z-index: 3;
        width: 12px;
        height: 7px;
        border-left: 2px solid #fff;
        border-bottom: 2px solid #fff;
        opacity: 0;
        filter: drop-shadow(0 1px 2px rgba(15, 23, 42, 0.72));
        transform: translate(-50%, -58%) rotate(-45deg) scale(0.55);
        transition:
            opacity .18s ease,
            transform .24s cubic-bezier(0.22, 1, 0.36, 1);
    }
    .opt-swatch:hover,
    .opt-swatch:focus-visible {
        transform: translateY(-3px) scale(1.08);
        box-shadow:
            0 0 0 2px rgba(54, 125, 241, 0.24),
            0 13px 26px rgba(15, 23, 42, 0.22);
        filter: saturate(1.12) brightness(1.03);
    }
    .opt-swatch:hover::before,
    .opt-swatch:focus-visible::before {
        opacity: 1;
        transform: translate(-50%, 0) scale(1);
    }
    .opt-swatch:focus-visible {
        outline: 2px solid #1a73e8;
        outline-offset: 4px;
    }
    .opt-swatch.active {
        border-color: #fff;
        box-shadow:
            0 0 0 3px #1a73e8,
            0 0 0 7px rgba(26, 115, 232, 0.16),
            0 14px 30px rgba(26, 115, 232, 0.24);
        transform: translateY(-2px) scale(1.06);
    }
    .opt-swatch.active::after {
        opacity: 1;
        transform: translate(-50%, -58%) rotate(-45deg) scale(1);
    }
    .opt-swatch span { display: none; }
    .opt-swatch--has-img {
        position: relative;
        overflow: hidden;
        background-color: #e8eaed;
    }
    .opt-swatch--has-img img {
        position: absolute;
        inset: 0;
        width: 100%;
        height: 100%;
        object-fit: cover;
        pointer-events: none;
        border-radius: 50%;
    }
    @media (prefers-reduced-motion: reduce) {
        .opt-swatch,
        .opt-swatch::before,
        .opt-swatch::after {
            transition: none;
            will-change: auto;
        }

        .opt-swatch:hover,
        .opt-swatch:focus-visible,
        .opt-swatch.active {
            transform: none;
        }
    }
    .opt-group--readonly .opt-chip--readonly,
    .opt-group--readonly .opt-swatch--readonly {
        cursor: default;
        pointer-events: none;
    }
    .opt-group--readonly .opt-chip--readonly {
        background: #f8f9fb;
        border-color: #e8eaed;
        color: #3c4043;
        font-weight: 500;
    }
    .opt-group--readonly .opt-swatch--readonly {
        opacity: 0.95;
    }
    .opt-group--exclusive-hidden {
        display: none !important;
    }
    .product-actions {
        margin-top: 28px;
        display: flex;
        flex-direction: row;
        flex-wrap: nowrap;
        align-items: stretch;
        gap: 12px;
        width: 100%;
        max-width: 100%;
        box-sizing: border-box;
    }
    .product-actions form {
        flex: 1 1 0;
        min-width: 0;
    }
    .product-actions__favorite {
        flex: 0 0 auto;
        min-width: 0;
        align-self: stretch;
    }
    /* Лайк як друга CTA-пігулка (як «Поради по догляду»): одна кнопка з іконкою, текстом і лічильником */
    .pdp-actions-like {
        margin: 0;
        padding: 0;
        background: transparent !important;
        border-radius: 0 !important;
        display: flex;
        align-items: stretch;
    }
    .pdp-actions-like .product-actions__favorite-btn.product-card__favorite {
        position: relative;
        box-sizing: border-box;
        width: auto;
        min-width: 88px;
        min-height: 48px;
        height: auto;
        padding: 13px 18px;
        margin: 0;
        border-radius: 20px;
        border: 1px solid rgba(54, 125, 241, 0.22);
        background: #eaf2ff;
        color: #1d5fd6;
        font-family: inherit;
        font-size: 16px;
        font-weight: 700;
        line-height: 1.2;
        cursor: pointer;
        box-shadow: 0 4px 14px rgba(54, 125, 241, 0.14);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        flex-wrap: nowrap;
        transition: background 0.15s ease, box-shadow 0.15s ease, transform 0.15s ease, border-color 0.15s ease, color 0.15s ease;
    }
    .pdp-actions-like .product-actions__favorite-btn.product-card__favorite:hover {
        background: #dbeafe;
        box-shadow: 0 6px 18px rgba(54, 125, 241, 0.18);
        transform: translateY(-1px);
    }
    .pdp-actions-like .product-actions__favorite-btn.product-card__favorite:active {
        transform: translateY(0);
    }
    .pdp-actions-like .product-actions__favorite-btn.product-card__favorite:focus-visible {
        outline: 2px solid #367df1;
        outline-offset: 2px;
    }
    .pdp-actions-like .product-actions__favorite-btn.product-card__favorite.is-favorite {
        border-color: rgba(239, 56, 41, 0.35);
        background: #fff5f5;
        color: #c81e4d;
        box-shadow: 0 4px 14px rgba(239, 56, 41, 0.16);
    }
    .pdp-actions-like .product-actions__favorite-btn.product-card__favorite.is-favorite:hover {
        background: #ffe8ec;
        box-shadow: 0 6px 18px rgba(239, 56, 41, 0.22);
    }
    .pdp-actions-like .product-actions__favorite-btn.product-card__favorite::after {
        display: none !important;
        animation: none !important;
    }
    .pdp-actions-like .product-actions__favorite-icon {
        flex-shrink: 0;
        width: 22px;
        height: 22px;
    }
    .pdp-actions-like .product-actions__favorite-count {
        min-width: 1.5em;
        height: auto;
        margin: 0;
        padding: 0;
        font-size: 0.95rem;
        font-weight: 700;
        font-variant-numeric: tabular-nums;
        color: inherit;
        opacity: 0.88;
    }
    .pdp-actions-like .product-actions__favorite-btn.product-card__favorite .product-actions__favorite-icon path {
        fill: currentColor;
        stroke: currentColor;
    }
    .pdp-actions-like .product-actions__favorite-btn.product-card__favorite:hover .product-actions__favorite-icon path {
        fill: currentColor;
        stroke: currentColor;
    }
    .product-care-cta {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: auto;
        flex: 1 1 0;
        min-width: 0;
        max-width: none;
        min-height: 48px;
        padding: 13px 14px;
        border-radius: 20px;
        border: 1px solid rgba(54, 125, 241, 0.22);
        background: #eaf2ff;
        color: #1d5fd6 !important;
        font-size: clamp(0.78rem, 2.4vw, 16px);
        font-weight: 700;
        text-decoration: none;
        text-align: center;
        line-height: 1.25;
        box-shadow: 0 4px 14px rgba(54, 125, 241, 0.14);
        transition: background .15s ease, box-shadow .15s ease, transform .15s ease;
    }
    .product-care-cta:hover {
        background: #dbeafe;
        box-shadow: 0 6px 18px rgba(54, 125, 241, 0.18);
        transform: translateY(-1px);
    }
    .product-actions .btn-product {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 100%;
        max-width: none;
        min-width: 0;
        min-height: 48px;
        padding: 13px clamp(10px, 2.5vw, 20px);
        border-radius: 20px;
        border: none;
        font-size: clamp(0.82rem, 2.6vw, 16px);
        font-weight: 600;
        cursor: pointer;
        background: #ef3829;
        color: #fff;
        box-shadow: 0 4px 14px rgba(239, 56, 41, 0.35);
        transition: background .15s, filter .15s, box-shadow .15s;
        text-align: center;
        line-height: 1.25;
    }
    .product-actions .btn-product:hover:not(:disabled) {
        background: #d62f22;
        filter: brightness(1.03);
        box-shadow: 0 6px 18px rgba(239, 56, 41, 0.45);
    }
    .product-actions .btn-product:disabled { opacity: .45; cursor: not-allowed; }
    /* Верхній sheet у дусі Apple: системна типографіка, «grabber», спокійні кольори. */
    .pdp-defer-modal {
        position: fixed;
        left: 50%;
        top: max(12px, env(safe-area-inset-top, 0px));
        z-index: 270;
        width: min(calc(100vw - 32px), 540px);
        margin: 0;
        padding: 0;
        border: 0;
        background: transparent;
        box-sizing: border-box;
        transform: translateX(-50%);
        pointer-events: none;
        font-family:
            -apple-system,
            BlinkMacSystemFont,
            'SF Pro Text',
            'Segoe UI',
            Roboto,
            'Helvetica Neue',
            Arial,
            sans-serif;
        --apple-blue: #007aff;
        --apple-label: #1d1d1f;
        --apple-body: rgba(29, 29, 31, 0.92);
        --apple-secondary: rgba(60, 60, 67, 0.74);
        --apple-tertiary: rgba(60, 60, 67, 0.45);
        --apple-fill: rgba(120, 120, 128, 0.12);
    }
    .pdp-defer-modal[hidden] { display: none !important; }
    .pdp-defer-modal__panel {
        position: relative;
        pointer-events: auto;
        max-width: 100%;
        width: 100%;
        overflow: hidden;
        padding: 28px 26px 20px;
        border-radius: 22px;
        border: 0.5px solid rgba(0, 0, 0, 0.08);
        background: #fff;
        box-shadow:
            0 28px 80px rgba(0, 0, 0, 0.12),
            0 12px 32px rgba(0, 0, 0, 0.08);
        opacity: 0;
        transform: translateY(-14px) scale(0.97);
        transition:
            opacity 0.32s cubic-bezier(0.32, 0.72, 0, 1),
            transform 0.38s cubic-bezier(0.32, 0.72, 0, 1);
        -webkit-font-smoothing: antialiased;
        -moz-osx-font-smoothing: grayscale;
        text-rendering: optimizeLegibility;
    }
    /* Індикатор як у системного bottom/side sheet */
    .pdp-defer-modal__panel::before {
        content: '';
        position: absolute;
        top: 10px;
        left: 50%;
        width: 38px;
        height: 5px;
        margin-left: -19px;
        border-radius: 100px;
        background: rgba(0, 0, 0, 0.12);
        pointer-events: none;
    }
    .pdp-defer-modal.is-visible .pdp-defer-modal__panel {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
    @media (prefers-reduced-motion: reduce) {
        .pdp-defer-modal__panel {
            transition-duration: 0.01ms;
        }
        .pdp-defer-modal__contact:hover {
            transform: none;
        }
        .pdp-defer-modal__contact:hover .pdp-defer-modal__icon-wrap {
            transform: none;
        }
    }
    .pdp-defer-modal__eyebrow {
        margin: 4px 0 10px;
        font-size: 0.75rem;
        font-weight: 700;
        letter-spacing: 0.05em;
        text-transform: uppercase;
        color: var(--apple-secondary);
        line-height: 1.35;
    }
    .pdp-defer-modal__title {
        margin: 0 0 18px;
        font-size: clamp(1.2rem, 3.5vw, 1.45rem);
        font-weight: 700;
        letter-spacing: -0.024em;
        line-height: 1.28;
        color: var(--apple-label);
    }
    .pdp-defer-modal__lead {
        margin: 0 0 22px;
        padding: 16px 18px;
        font-weight: 600;
        letter-spacing: -0.008em;
        color: var(--apple-body);
        border-radius: 16px;
        border: none;
        background: rgba(0, 0, 0, 0.045);
        box-shadow: none;
    }
    .pdp-defer-modal__contacts-heading {
        display: flex;
        align-items: center;
        gap: 8px;
        margin: 0 0 12px;
        font-size: 0.875rem;
        font-weight: 700;
        letter-spacing: -0.008em;
        text-transform: none;
        color: var(--apple-secondary);
        line-height: 1.4;
    }
    .pdp-defer-modal__contacts-heading::before {
        content: '';
        flex-shrink: 0;
        width: 6px;
        height: 6px;
        border-radius: 50%;
        background: #34c759;
        opacity: 0.9;
    }
    .pdp-defer-modal__contacts {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(130px, 1fr));
        gap: 10px;
        margin-bottom: 2px;
    }
    .pdp-defer-modal__contact {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 10px;
        padding: 15px 11px 14px;
        border-radius: 16px;
        border: 0.5px solid rgba(0, 0, 0, 0.06);
        background: rgba(255, 255, 255, 0.65);
        text-decoration: none;
        color: var(--apple-label);
        font-size: 0.875rem;
        font-weight: 600;
        text-align: center;
        line-height: 1.38;
        letter-spacing: -0.012em;
        transition:
            background-color 0.2s ease,
            box-shadow 0.22s ease,
            transform 0.22s cubic-bezier(0.32, 0.72, 0, 1);
        word-break: break-word;
        hyphens: auto;
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.04);
    }
    .pdp-defer-modal__contact:hover {
        border-color: rgba(0, 0, 0, 0.08);
        background: rgba(255, 255, 255, 0.92);
        box-shadow: 0 4px 16px rgba(0, 0, 0, 0.06);
        transform: translateY(-1px);
    }
    .pdp-defer-modal__contact:focus-visible {
        outline: 2px solid var(--apple-blue);
        outline-offset: 2px;
    }
    .pdp-defer-modal__icon-wrap {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 48px;
        height: 48px;
        border-radius: 14px;
        flex-shrink: 0;
        box-shadow: none;
        transition: transform 0.2s ease, opacity 0.2s ease;
    }
    .pdp-defer-modal__contact:hover .pdp-defer-modal__icon-wrap {
        transform: scale(1.03);
    }
    .pdp-defer-modal__contact--phone .pdp-defer-modal__icon-wrap {
        background: rgba(52, 199, 89, 0.14);
        color: #248a3d;
    }
    .pdp-defer-modal__contact--viber .pdp-defer-modal__icon-wrap {
        background: rgba(142, 68, 173, 0.12);
        color: #6b3fa0;
    }
    .pdp-defer-modal__contact--whatsapp .pdp-defer-modal__icon-wrap {
        background: rgba(37, 211, 102, 0.14);
        color: #1d8a48;
    }
    .pdp-defer-modal__contact--telegram .pdp-defer-modal__icon-wrap {
        background: rgba(0, 122, 255, 0.12);
        color: #007aff;
    }
    .pdp-defer-modal__contact-label {
        max-width: 100%;
    }
    .pdp-defer-modal__actions {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        justify-content: stretch;
        align-items: stretch;
        margin-top: 22px;
        margin-left: -26px;
        margin-right: -26px;
        margin-bottom: -6px;
        padding: 18px 26px 12px;
        border-top: 0.5px solid rgba(0, 0, 0, 0.08);
        background: rgba(248, 248, 248, 0.55);
    }
    .pdp-defer-modal__btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex: 1 1 auto;
        min-width: 0;
        min-height: 50px;
        margin: 0;
        padding: 0 18px;
        border: none;
        border-radius: 14px;
        font-family: inherit;
        font-weight: 700;
        line-height: 1.25;
        cursor: pointer;
        transition: opacity 0.15s ease, transform 0.12s ease;
        -webkit-tap-highlight-color: transparent;
    }
    .pdp-defer-modal__btn:active:not(:disabled) {
        transform: scale(0.98);
        opacity: 0.88;
    }
    .pdp-defer-modal__btn--secondary {
        flex: 1 1 38%;
        background: var(--apple-fill);
        color: var(--apple-label);
    }
    .pdp-defer-modal__btn--primary {
        flex: 1 1 52%;
        background: var(--apple-blue);
        color: #fff;
        box-shadow: 0 1px 0 rgba(255, 255, 255, 0.22) inset;
    }
    .pdp-defer-modal__btn--primary:hover {
        filter: brightness(1.03);
    }
    .pdp-defer-modal__btn--secondary:hover {
        background: rgba(120, 120, 128, 0.16);
    }
    .pdp-defer-modal__btn:focus-visible {
        outline: 2px solid var(--apple-blue);
        outline-offset: 2px;
    }
    @media (max-width: 420px) {
        .pdp-defer-modal__actions {
            flex-direction: column;
            gap: 9px;
        }
        .pdp-defer-modal__btn,
        .pdp-defer-modal__btn--secondary,
        .pdp-defer-modal__btn--primary {
            flex: none;
            width: 100%;
        }
        .pdp-defer-modal__btn--primary {
            order: -1;
        }
    }
    .product-hint { font-size: 13px; color: #5f6368; min-height: 1.2em; }
    .product-variant-select {
        width: 100%;
        max-width: 420px;
        margin-top: 4px;
        padding: 12px 14px;
        font-size: 15px;
        border-radius: 10px;
        border: 1px solid #dadce0;
        background: #fff;
        color: #202124;
        cursor: pointer;
    }
    .product-variant-select:focus {
        outline: none;
        border-color: #1a73e8;
        box-shadow: 0 0 0 3px rgba(26, 115, 232, 0.2);
    }
    .product-desc {
        max-width: none;
        margin: 0;
        padding: clamp(18px, 2.4vw, 30px);
    }
    .product-desc h2 { font-size: 18px; margin-bottom: 12px; }
    .product-desc p { color: #3c4043; line-height: 1.6; }
    .product-desc__body {
        color: #3c4043;
        line-height: 1.65;
        font-size: 15px;
    }
    .product-desc__body > * + * { margin-top: 0.75em; }
    .product-desc__body p { margin: 0 0 0.75em; }
    .product-desc__body p:last-child { margin-bottom: 0; }
    .product-desc__body ul,
    .product-desc__body ol { margin: 0.35em 0 0.75em 1.25em; padding: 0; }
    .product-desc__body li { margin: 0.3em 0; }
    .product-desc__body h1,
    .product-desc__body h2,
    .product-desc__body h3,
    .product-desc__body h4 { margin: 0.85em 0 0.4em; line-height: 1.3; color: #202124; }
    .product-desc__body h1:first-child,
    .product-desc__body h2:first-child,
    .product-desc__body h3:first-child { margin-top: 0; }
    .product-desc__body a {
        color: #1a73e8;
        text-decoration: underline;
        text-underline-offset: 2px;
        overflow-wrap: anywhere;
        word-break: break-word;
    }
    .product-desc__body a:hover { color: #1557b0; }
    .product-desc__body blockquote {
        border-left: 3px solid #dadce0;
        margin: 0.75em 0;
        padding-left: 1em;
        color: #5f6368;
    }
    .product-desc__body code { font-size: 0.92em; background: #f1f3f4; padding: 0.1em 0.35em; border-radius: 4px; }
    .product-desc__body pre { margin: 0.75em 0; padding: 12px 14px; background: #f8f9fa; border-radius: 8px; overflow-x: auto; font-size: 13px; }
    .product-desc__body pre code { background: transparent; padding: 0; }
    .product-desc__body table { border-collapse: collapse; width: 100%; margin: 0.75em 0; font-size: 14px; }
    .product-desc__body th,
    .product-desc__body td { border: 1px solid #e8eaed; padding: 8px 10px; text-align: left; vertical-align: top; }
    .product-desc__body th { background: #f8f9fa; font-weight: 600; }
    .product-desc__body hr { border: none; border-top: 1px solid #e8eaed; margin: 1.25em 0; }
    .product-desc__body img { max-width: 100%; height: auto; border-radius: 6px; }
    .product-desc__body sub,
    .product-desc__body sup { font-size: 0.8em; line-height: 0; }
    .product-desc__body li > ul,
    .product-desc__body li > ol { margin-top: 0.35em; margin-bottom: 0.35em; }
    .product-care-section {
        max-width: none;
        width: 100%;
        box-sizing: border-box;
        margin: 0;
        padding: clamp(18px, 2.4vw, 30px);
        border-radius: 20px;
        border: 1px solid #e8eaed;
        background: linear-gradient(180deg, #fff 0%, #f8fbff 100%);
        box-shadow: 0 10px 26px rgba(15, 23, 42, 0.07);
    }
    .product-care-section__head {
        display: flex;
        flex-wrap: wrap;
        align-items: flex-end;
        justify-content: space-between;
        gap: 0.9rem 1rem;
        margin-bottom: 1rem;
    }
    .product-care-section__title {
        margin: 0;
        color: #367df1;
        font-size: clamp(1.25rem, 2.4vw, 1.6rem);
        letter-spacing: -0.03em;
    }
    .product-care-section__lead {
        margin: 0.35rem 0 0;
        color: #5f6368;
        line-height: 1.45;
    }
    .product-care-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 0.9rem;
    }
    .product-main-column .product-care-grid {
        grid-template-columns: 1fr;
    }
    .product-care-card {
        display: flex;
        flex-direction: column;
        min-height: 100%;
        padding: 1rem;
        border-radius: 16px;
        border: 1px solid #e6ebf2;
        background: #fff;
        color: inherit;
        text-decoration: none;
        box-shadow: 0 6px 16px rgba(15, 23, 42, 0.05);
        transition: transform .15s ease, box-shadow .15s ease;
    }
    .product-care-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 12px 26px rgba(15, 23, 42, 0.09);
    }
    .product-care-card__title {
        margin: 0;
        color: #0f172a;
        font-size: 1rem;
        line-height: 1.3;
    }
    .product-care-card__excerpt {
        margin: 0.55rem 0 0;
        color: #5f6368;
        font-size: 0.88rem;
        line-height: 1.45;
    }
    .product-care-card__more {
        margin-top: auto;
        padding-top: 0.85rem;
        color: var(--color-cta);
        font-weight: 900;
    }

    @media (max-width: 768px) {
        .product-page {
            width: 100%;
        }

        .product-page {
            --pdp-block-inset: 0px;
            margin: 0 auto 18px;
            padding: 0 12px max(20px, env(safe-area-inset-bottom, 0px));
        }

        .product-breadcrumb {
            margin-bottom: 14px;
            font-size: 12px;
        }

        .product-grid {
            gap: 12px;
        }

        .product-main-column {
            gap: 12px;
        }

        .product-gallery-card,
        .product-detail-card,
        .product-desc,
        .product-care-section {
            border-radius: 18px;
        }

        .product-gallery-card {
            padding: 10px;
        }

        .product-detail-card,
        .product-desc,
        .product-care-section {
            padding: 16px;
        }

        .product-title {
            font-size: clamp(1.35rem, 7vw, 1.75rem);
        }

        .product-gallery-main {
            border-radius: 16px;
        }

        .product-thumbs {
            gap: 7px;
            flex-wrap: nowrap;
            overflow-x: auto;
            padding-bottom: 4px;
            scrollbar-width: thin;
        }

        .product-thumbs button {
            width: 58px;
            height: 58px;
            flex: 0 0 auto;
        }

        .product-price {
            font-size: 1.72rem;
        }

        .product-options-panel {
            padding: 14px;
            border-radius: 16px;
        }

        .opt-chip,
        .opt-chip--readonly {
            min-height: 44px;
            padding-inline: 14px;
        }

        .opt-swatch,
        .opt-swatch--readonly {
            width: 44px;
            height: 44px;
        }

        .product-actions {
            margin-top: 20px;
            flex-direction: row;
            flex-wrap: nowrap;
            align-items: stretch;
            gap: 8px;
            max-width: none;
        }

        .pdp-actions-like {
            flex: 0 0 auto;
            width: auto;
        }

        .pdp-actions-like .product-actions__favorite-btn.product-card__favorite {
            min-width: 76px;
            padding: 12px 12px;
        }

        .product-actions .btn-product {
            max-width: none;
            min-height: 48px;
            border-radius: 20px;
        }
        .product-care-cta {
            max-width: none;
            border-radius: 20px;
        }

        .product-desc,
        .product-care-section {
            margin-top: 0;
        }

        .pdp-related-section.home-shop-panel {
            margin-top: 16px;
            border-radius: 18px;
        }

        .pdp-defer-modal {
            width: min(calc(100vw - 24px), 540px);
        }

        .pdp-defer-modal__panel {
            border-radius: 20px;
            padding: 26px 18px 18px;
        }

        .pdp-defer-modal__panel::before {
            top: 9px;
        }

        .pdp-defer-modal__actions {
            margin-left: -18px;
            margin-right: -18px;
            padding-left: 18px;
            padding-right: 18px;
        }
    }
</style>
@endpush

@section('content')
<div class="product-page" data-favorite-ids="{{ e(json_encode($favoriteProductIds ?? [])) }}">
    @if (count($categoryBreadcrumb))
        <div class="product-category-chain">
            <a
                href="{{ route('catalog.index', array_filter($breadcrumbQueryBase)) }}"
            >
                {{ __('shop.catalog_toolbar_catalog') }}
            </a>
            @foreach ($categoryBreadcrumb as $index => $catPart)
                @php
                    $fallbackCategoryId = $index > 0 ? ($categoryBreadcrumb[$index - 1]['id'] ?? null) : null;
                @endphp
                <a
                    href="{{ route('catalog.index', array_filter(array_merge($breadcrumbQueryBase, ['category' => $fallbackCategoryId]))) }}"
                >
                    {{ $catPart['name'] ?? '' }}
                </a>
            @endforeach
        </div>
    @else
        <div class="product-breadcrumb">
            <a href="{{ route('catalog.index') }}">{{ __('shop.catalog_toolbar_catalog') }}</a>
            <span class="product-breadcrumb__sep">/</span>
            <span class="product-breadcrumb__selected" id="product-breadcrumb-selected">{{ mt($listing->title) }}</span>
        </div>
    @endif

    <div class="product-grid">
        <div class="product-main-column">
            <div class="product-gallery-card">
                <div class="product-gallery-column" id="product-gallery-zone">
                    <div
                        class="product-gallery-main product-gallery-main--fit-cover @if (count($galleryPhotos) > 1) product-gallery-main--multi-img @endif"
                        id="product-main-visual"
                    >
                        @if (count($galleryPhotos))
                            <img src="{{ asset('storage/' . $galleryPhotos[0]) }}" alt="{{ mt($listing->title) }}" id="product-main-img">
                        @else
                            <span class="placeholder">{{ __('shop.pdp_no_photo') }}</span>
                        @endif
                    </div>
                    <div
                        class="product-thumbs product-thumbs--fit-cover"
                        id="product-thumbs"
                        @if (count($galleryPhotos) <= 1) style="display:none" @endif
                        data-has-thumbs="{{ count($galleryPhotos) > 1 ? '1' : '0' }}"
                    >
                        @foreach ($galleryPhotos as $i => $photo)
                            <button type="button" class="{{ $i === 0 ? 'active' : '' }}" data-src="{{ asset('storage/' . $photo) }}" aria-label="{{ __('shop.pdp_photo_aria', ['n' => $i + 1]) }}">
                                <img src="{{ asset('storage/' . $photo) }}" alt="">
                            </button>
                        @endforeach
                    </div>
                </div>
            </div>

            @if ($listing->hasRichDescriptionSection())
                <div class="product-desc">
                    @if ($listing->hasDisplayableShortDescription())
                        <h2>{{ __('shop.pdp_short_heading') }}</h2>
                        <div class="product-desc__body">{!! $listing->safeShortDescriptionHtml() !!}</div>
                    @endif
                    @if ($listing->hasDisplayableDescription())
                        <h2 style="margin-top:24px;">{{ __('shop.pdp_long_heading') }}</h2>
                        <div class="product-desc__body">{!! $listing->safeDescriptionHtml() !!}</div>
                    @endif
                </div>
            @endif

            @if (($careArticles ?? collect())->isNotEmpty())
                <section class="product-care-section" aria-labelledby="product-care-heading">
                    <div class="product-care-section__head">
                        <div>
                            <h2 id="product-care-heading" class="product-care-section__title">{{ __('shop.pdp_care_section_title') }}</h2>
                            <p class="product-care-section__lead">{{ __('shop.pdp_care_section_lead') }}</p>
                        </div>
                        <a class="btn secondary" href="{{ route('catalog.care.index', $listing->slug) }}">{{ __('shop.pdp_care_all') }}</a>
                    </div>
                    <div class="product-care-grid">
                        @foreach ($careArticles->take(3) as $article)
                            <a class="product-care-card" href="{{ route('catalog.care.show', [$listing->slug, $article->slug]) }}">
                                <h3 class="product-care-card__title">{{ mt($article->title) }}</h3>
                                @if (filled($article->excerpt))
                                    <p class="product-care-card__excerpt">{{ mt($article->excerpt) }}</p>
                                @endif
                                <span class="product-care-card__more">{{ __('shop.pdp_care_read') }}</span>
                            </a>
                        @endforeach
                    </div>
                </section>
            @endif
        </div>

        <div class="product-detail-column">
            <div class="product-detail-card">
                <h1 class="product-title">{{ mt($listing->title) }}</h1>

            <div class="product-badges" id="product-stock-badges">
                @if ($listingStockMode === 'preorder')
                    <span class="badge pre">{{ __('shop.pdp_badge_preorder') }}</span>
                @elseif ($listingStockMode === 'low')
                    <span class="badge low">{{ __('shop.pdp_badge_low') }}</span>
                @elseif ($listingStockMode === 'ok')
                    <span class="badge ok">{{ __('shop.pdp_badge_in_stock') }}</span>
                @else
                    <span class="badge no">{{ __('shop.pdp_badge_out_of_stock') }}</span>
                @endif
            </div>

            <div class="product-price-row">
                <span class="product-price" id="product-price">
                    @if ($listingBaseCompareAt !== null && (float) $listingBaseCompareAt > (float) $listingBasePrice)
                        <span class="product-price__old">{{ number_format((float) $listingBaseCompareAt, 0, '', ' ') }} <span style="font-size:1rem;font-weight:500;">₴</span></span>
                    @endif
                    <span class="product-price__current">{{ number_format((float) $listingBasePrice, 0, '', ' ') }} <span style="font-size:1rem;font-weight:500;">₴</span></span>
                </span>
            </div>
            <p class="product-price-note" id="product-price-note" style="font-size:13px;color:#5f6368;margin:6px 0 0;"></p>
            @if (filled(trim((string) ($listing->sku ?? ''))))
                <p class="product-sku">{{ __('shop.pdp_sku', ['sku' => $listing->sku]) }}</p>
            @endif

            @if (count($optionBlocks))
                <div id="product-options" class="product-options-panel" data-has-variants="0">
                    <h2 class="product-options-heading">{{ __('shop.pdp_options_heading') }}</h2>
                    @foreach ($optionBlocks as $block)
                        @php
                            $rawDefault = $defaultOptionSelection[$block['id']] ?? null;
                            if (is_array($rawDefault)) {
                                $targetIds = collect($rawDefault)->map(fn ($id) => (int) $id)->filter(fn (int $id) => $id > 0)->values()->all();
                            } elseif ($rawDefault !== null) {
                                $targetIds = [(int) $rawDefault];
                            } else {
                                $targetIds = [];
                            }
                            $valueIdsInBlock = collect($block['values'])->pluck('id')->map(fn ($id) => (int) $id)->all();
                            $hasTargetInBlock = count($targetIds) > 0
                                && collect($targetIds)->every(fn (int $id) => in_array($id, $valueIdsInBlock, true));
                            $isMultipleBlock = ($block['selection_mode'] ?? 'single') === 'multiple';
                            $targetValueIdForSingle = count($targetIds) === 1 ? $targetIds[0] : null;
                        @endphp
                        @php
                            $exclusiveForVariantId = 0;
                            $exclusiveApplies = true;
                        @endphp
                        <div
                            class="opt-group {{ $exclusiveForVariantId > 0 && ! $exclusiveApplies ? 'opt-group--exclusive-hidden' : '' }}"
                            data-group-id="{{ $block['id'] }}"
                            data-mode="{{ $block['selection_mode'] }}"
                            data-value-type="{{ $block['value_type'] }}"
                        >
                            <span class="opt-label">{{ $block['name'] }}</span>
                            @if (($block['value_type'] ?? 'text') === 'color')
                                <div class="opt-colors" role="group">
                                    @foreach ($block['values'] as $val)
                                        @php
                                            if ($exclusiveForVariantId > 0 && ! $exclusiveApplies) {
                                                $isOptActive = false;
                                            } elseif ($isMultipleBlock) {
                                                $isOptActive = false;
                                            } else {
                                                $isOptActive = ($hasTargetInBlock && (int) $val['id'] === $targetValueIdForSingle)
                                                    || (! $hasTargetInBlock && $loop->first);
                                            }
                                        @endphp
                                        @php
                                            $swatchPath = isset($val['swatch_image']) && is_string($val['swatch_image']) ? trim($val['swatch_image']) : '';
                                            $swatchHasImg = $swatchPath !== '' && ! filled($val['color_hex'] ?? null);
                                        @endphp
                                        <button
                                            type="button"
                                            class="opt-swatch {{ $isOptActive ? 'active' : '' }} {{ $swatchHasImg ? 'opt-swatch--has-img' : '' }}"
                                            data-value-id="{{ $val['id'] }}"
                                            @if ($swatchHasImg)
                                                style="background-color: {{ $val['color_hex'] ?? '#e8eaed' }};"
                                            @else
                                                style="background-color: {{ $val['color_hex'] ?? '#666' }};"
                                            @endif
                                            title="{{ $val['name'] }}"
                                            aria-label="{{ $val['name'] }}"
                                        >
                                            <span>{{ $val['name'] }}</span>
                                            @if ($swatchHasImg)
                                                <img src="{{ asset('storage/' . ltrim($swatchPath, '/')) }}" alt="" aria-hidden="true">
                                            @endif
                                        </button>
                                    @endforeach
                                </div>
                            @else
                                <div class="opt-chips" role="group">
                                    @foreach ($block['values'] as $val)
                                        @php
                                            if ($exclusiveForVariantId > 0 && ! $exclusiveApplies) {
                                                $isOptActive = false;
                                            } elseif ($isMultipleBlock) {
                                                $isOptActive = false;
                                            } else {
                                                $isOptActive = ($hasTargetInBlock && (int) $val['id'] === $targetValueIdForSingle)
                                                    || (! $hasTargetInBlock && $loop->first);
                                            }
                                        @endphp
                                        <button type="button" class="opt-chip {{ $isOptActive ? 'active' : '' }}" data-value-id="{{ $val['id'] }}">
                                            {{ $val['name'] }}
                                            @if (($val['price'] ?? null) !== null && (float) $val['price'] > 0)
                                                <span style="opacity:.75"> +{{ number_format((float) $val['price'], 0, '', ' ') }} ₴</span>
                                            @endif
                                        </button>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            @elseif (count($productOptionsDisplay))
                <div class="product-options-readonly product-options-panel" id="product-options-readonly" aria-label="{{ __('shop.pdp_options_aria') }}">
                    <h2 class="product-options-heading">{{ __('shop.pdp_options_heading') }}</h2>
                    @foreach ($productOptionsDisplay as $block)
                        @php
                            $roExclusiveVid = (int) ($block['exclusive_for_variant_id'] ?? 0);
                            $roExclusiveApplies = $roExclusiveVid <= 0
                                || ($storefrontDefaultVariantId !== null && $storefrontDefaultVariantId === $roExclusiveVid);
                        @endphp
                        <div class="opt-group opt-group--readonly {{ $roExclusiveVid > 0 && ! $roExclusiveApplies ? 'opt-group--exclusive-hidden' : '' }}">
                            <span class="opt-label">{{ $block['name'] }}</span>
                            @if (($block['value_type'] ?? 'text') === 'color')
                                <div class="opt-colors" role="list">
                                    @foreach ($block['values'] as $val)
                                        @php
                                            $roSwatchPath = isset($val['swatch_image']) && is_string($val['swatch_image']) ? trim($val['swatch_image']) : '';
                                            $roSwatchHasImg = $roSwatchPath !== '' && ! filled($val['color_hex'] ?? null);
                                        @endphp
                                        <span
                                            class="opt-swatch opt-swatch--readonly {{ $roSwatchHasImg ? 'opt-swatch--has-img' : '' }}"
                                            @if (! $roSwatchHasImg)
                                                style="background-color: {{ $val['color_hex'] ?? '#666' }};"
                                            @else
                                                style="background-color: {{ $val['color_hex'] ?? '#e8eaed' }};"
                                            @endif
                                            title="{{ $val['name'] }}"
                                            role="listitem"
                                            aria-label="{{ $val['name'] }}"
                                        >
                                            @if ($roSwatchHasImg)
                                                <img src="{{ asset('storage/' . ltrim($roSwatchPath, '/')) }}" alt="" aria-hidden="true">
                                            @endif
                                        </span>
                                    @endforeach
                                </div>
                            @else
                                <div class="opt-chips" role="list">
                                    @foreach ($block['values'] as $val)
                                        <span class="opt-chip opt-chip--readonly" role="listitem">
                                            {{ $val['name'] }}
                                            @if (($val['price'] ?? null) !== null && (float) $val['price'] > 0)
                                                <span style="opacity:.75"> +{{ number_format((float) $val['price'], 0, '', ' ') }} ₴</span>
                                            @endif
                                        </span>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif

            <p class="product-hint" id="product-variant-hint"></p>

            <div class="product-actions">
                <form method="POST" action="{{ route('cart.store') }}" id="product-cart-form" data-cart-add-form>
                    @csrf
                    <input type="hidden" name="product_id" value="{{ $listing->id }}">
                    <input type="hidden" name="option_value_ids" value="[]" id="input-option-value-ids">
                    <button class="btn-product" type="submit" id="product-add-btn">{{ __('shop.pdp_add_to_cart') }}</button>
                </form>
                @if (($careArticles ?? collect())->isNotEmpty())
                    <a class="product-care-cta" href="{{ route('catalog.care.index', $listing->slug) }}">
                        {{ __('shop.pdp_care_cta') }}
                    </a>
                @endif
                <div class="product-card__like-react product-actions__favorite pdp-actions-like">
                    <button
                        type="button"
                        class="product-card__favorite product-actions__favorite-btn"
                        data-product-id="{{ $listing->id }}"
                        data-favorite-key="catalog"
                        aria-label="{{ __('shop.pdp_favorite_aria') }}"
                        aria-pressed="false"
                    >
                        <svg
                            class="product-card__heart-icon product-actions__favorite-icon"
                            xmlns="http://www.w3.org/2000/svg"
                            width="22"
                            height="22"
                            viewBox="0 0 24 24"
                            fill="none"
                            aria-hidden="true"
                        >
                            <path
                                d="M19.4626 3.99415C16.7809 2.34923 14.4404 3.01211 13.0344 4.06801C12.4578 4.50096 12.1696 4.71743 12 4.71743C11.8304 4.71743 11.5422 4.50096 10.9656 4.06801C9.55962 3.01211 7.21909 2.34923 4.53744 3.99415C1.01807 6.15294 0.221721 13.2749 8.33953 19.2834C9.88572 20.4278 10.6588 21 12 21C13.3412 21 14.1143 20.4278 15.6605 19.2834C23.7783 13.2749 22.9819 6.15294 19.4626 3.99415Z"
                                stroke="currentColor"
                                stroke-width="2"
                                stroke-linecap="round"
                                fill="currentColor"
                            />
                        </svg>
                        <span class="product-card__like-count product-actions__favorite-count" data-product-like-count>{{ (int) ($listing->favorited_by_users_count ?? 0) }}</span>
                    </button>
                </div>
            </div>

            @if (! empty($pdpDefersOnlinePayment ?? false))
                <div
                    id="pdp-defer-payment-modal"
                    class="pdp-defer-modal"
                    hidden
                    aria-hidden="true"
                >
                    <div
                        class="pdp-defer-modal__panel"
                        role="dialog"
                        aria-modal="true"
                        aria-labelledby="pdp-defer-title"
                    >
                        <p class="pdp-defer-modal__eyebrow" aria-hidden="true">{{ __('shop.pdp_defer_eyebrow') }}</p>
                        <h2 id="pdp-defer-title" class="pdp-defer-modal__title">{{ __('shop.pdp_defer_title') }}</h2>
                        <p class="pdp-defer-modal__lead">
                            {{ __('shop.pdp_defer_lead') }}
                        </p>
                        @include('catalog.partials.pdp-defer-modal-contacts', ['items' => $pdpDeferModalContacts ?? []])
                        <div class="pdp-defer-modal__actions">
                            <button type="button" class="pdp-defer-modal__btn pdp-defer-modal__btn--secondary" data-pdp-defer-close>{{ __('shop.pdp_defer_cancel') }}</button>
                            <button type="button" class="pdp-defer-modal__btn pdp-defer-modal__btn--primary" data-pdp-defer-continue>{{ __('shop.pdp_defer_continue') }}</button>
                        </div>
                    </div>
                </div>
            @endif
            </div>
        </div>
    </div>

@php
    $pdpRecommended = $recommendedProducts ?? collect();
@endphp
@if ($pdpRecommended->isNotEmpty())
    <section
        id="pdp-recommended"
        class="home-shop-panel pdp-related-section"
        data-favorite-ids="{{ e(json_encode($favoriteProductIds ?? [])) }}"
        aria-labelledby="pdp-related-heading"
    >
        <h2 id="pdp-related-heading" class="home-shop-panel__title">{{ __('shop.pdp_related') }}</h2>
        <div
            class="home-product-carousel"
            data-home-carousel
            role="region"
            aria-roledescription="{{ __('shop.aria_carousel') }}"
            aria-labelledby="pdp-related-heading"
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
                    @foreach ($pdpRecommended as $product)
                        <div class="home-product-carousel__cell">
                            @include('catalog.partials.product-card', [
                                'listing' => $product,
                                'listingQuotes' => $recommendedListingQuotes ?? [],
                                'bundleQuotes' => [],
                                'cardImagePriority' => $loop->index,
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
    </section>
@endif
</div>

@push('scripts')
<script type="application/json" id="product-show-config">@json($productShowConfig)</script>
@php
    $pdpViteReady = file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot'));
@endphp
@if ($pdpViteReady)
    @vite(['resources/js/product-show-page.js'])
@else
    {{-- Без npm run build / npm run dev сторінка товару лишається робочою (скрипт без import/export). --}}
    <script>
{!! file_get_contents(resource_path('js/product-show-page.js')) !!}
    </script>
@endif
@include('catalog.partials.shop-catalog-scripts')
@endpush
@include('home.partials.home-product-carousel')
@endsection
