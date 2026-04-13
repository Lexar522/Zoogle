@extends('layouts.shop')

@section('title', $listing->title . ' — ZOOGLE')

@push('meta')
    <meta name="description" content="{{ e($pdpMetaDescription ?? $listing->title) }}">
    <link rel="canonical" href="{{ route('catalog.show', $listing->slug) }}">
    <meta property="og:type" content="product">
    <meta property="og:title" content="{{ e($listing->title) }}">
    <meta property="og:description" content="{{ e($pdpMetaDescription ?? $listing->title) }}">
    @if (! empty($pdpOgImageUrl))
        <meta property="og:image" content="{{ $pdpOgImageUrl }}">
    @endif
    <meta name="twitter:card" content="summary_large_image">
    @php
        $schemaAvailability = match (($listingStockMode ?? 'none')) {
            'ok', 'low' => 'https://schema.org/InStock',
            'preorder' => 'https://schema.org/PreOrder',
            default => 'https://schema.org/OutOfStock',
        };
    @endphp
    <script type="application/ld+json">
{!! json_encode([
    '@context' => 'https://schema.org',
    '@type' => 'Product',
    'name' => $listing->title,
    'description' => $pdpMetaDescription ?? $listing->title,
    'sku' => filled(trim((string) ($listing->sku ?? ''))) ? (string) $listing->sku : (string) $listing->slug,
    'offers' => [
        '@type' => 'AggregateOffer',
        'priceCurrency' => 'UAH',
        'lowPrice' => number_format((float) ($pdpOfferLowPrice ?? $listingBasePrice ?? 0), 2, '.', ''),
        'highPrice' => number_format((float) ($pdpOfferHighPrice ?? $listingBasePrice ?? 0), 2, '.', ''),
        'offerCount' => 1,
        'availability' => $schemaAvailability,
    ],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}
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
    .product-page {
        background: #fff;
        color: #202124;
        min-height: 100vh;
        width: min(80vw, 100%);
        margin: clamp(6px, 1vw, 14px) auto clamp(12px, 2vw, 28px);
        box-sizing: border-box;
        padding: clamp(20px, 3vw, 44px);
        padding-left: clamp(16px, 2.4vw, 36px);
        padding-right: clamp(16px, 2.4vw, 36px);
        border-radius: 20px;
        border: 1px solid #e8eaed;
        box-shadow: 0 2px 10px rgba(60, 64, 67, 0.08);
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
        gap: .45rem;
        margin: 0 0 1rem;
    }
    .product-grid {
        display: grid;
        grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
        gap: clamp(24px, 3vw, 48px);
        max-width: min(1680px, 100%);
        margin: 0 auto;
        align-items: start;
    }
    @media (max-width: 900px) {
        .product-grid { grid-template-columns: 1fr; }
    }
    .product-gallery-column {
        min-width: 0;
        width: 100%;
    }
    .product-detail-column {
        min-width: 0;
        width: 100%;
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
        border-radius: 10px;
        font-size: 14px;
        cursor: pointer;
        transition: border-color .15s, background .15s;
    }
    .opt-chip:hover { border-color: #5f6368; }
    .opt-chip.active {
        background: #1a73e8;
        color: #fff;
        border-color: #1a73e8;
    }
    .opt-chip:disabled { opacity: .4; cursor: not-allowed; }
    .opt-colors { display: flex; flex-wrap: wrap; gap: 12px; align-items: center; }
    .opt-swatch {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        border: 2px solid #dadce0;
        cursor: pointer;
        padding: 0;
        box-sizing: border-box;
    }
    .opt-swatch.active { border-color: #1a73e8; box-shadow: 0 0 0 2px rgba(26, 115, 232, 0.2); }
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
    .product-actions { margin-top: 28px; display: flex; flex-direction: column; gap: 12px; }
    .product-actions .btn-product {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 100%;
        max-width: 320px;
        padding: 14px 20px;
        border-radius: 12px;
        border: none;
        font-size: 16px;
        font-weight: 600;
        cursor: pointer;
        background: #ef3829;
        color: #fff;
        box-shadow: 0 4px 14px rgba(239, 56, 41, 0.35);
        transition: background .15s, filter .15s, box-shadow .15s;
    }
    .product-actions .btn-product:hover:not(:disabled) {
        background: #d62f22;
        filter: brightness(1.03);
        box-shadow: 0 6px 18px rgba(239, 56, 41, 0.45);
    }
    .product-actions .btn-product:disabled { opacity: .45; cursor: not-allowed; }
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
    .product-desc { max-width: min(1680px, 100%); margin: 40px auto 0; padding-top: 32px; border-top: 1px solid #e8eaed; }
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
</style>
@endpush

@section('content')
<div class="product-page">
    @if (count($categoryBreadcrumb))
        <div class="product-category-chain">
            <a
                class="btn secondary"
                href="{{ route('catalog.index', array_filter($breadcrumbQueryBase)) }}"
                style="padding:.35rem .65rem;"
            >
                Каталог ×
            </a>
            @foreach ($categoryBreadcrumb as $index => $catPart)
                @php
                    $fallbackCategoryId = $index > 0 ? ($categoryBreadcrumb[$index - 1]['id'] ?? null) : null;
                @endphp
                <a
                    class="btn secondary"
                    href="{{ route('catalog.index', array_filter(array_merge($breadcrumbQueryBase, ['category' => $fallbackCategoryId]))) }}"
                    style="padding:.35rem .65rem;"
                >
                    {{ $catPart['name'] ?? '' }} ×
                </a>
            @endforeach
        </div>
    @else
        <div class="product-breadcrumb">
            <a href="{{ route('catalog.index') }}">Каталог</a>
            <span class="product-breadcrumb__sep">/</span>
            <span class="product-breadcrumb__selected" id="product-breadcrumb-selected">{{ $listing->title }}</span>
        </div>
    @endif

    <div class="product-grid">
        <div class="product-gallery-column">
            <div
                class="product-gallery-main product-gallery-main--fit-cover"
                id="product-main-visual"
            >
                @if (count($galleryPhotos))
                    <img src="{{ asset('storage/' . $galleryPhotos[0]) }}" alt="{{ $listing->title }}" id="product-main-img">
                @else
                    <span class="placeholder">Немає фото</span>
                @endif
            </div>
            <div
                class="product-thumbs product-thumbs--fit-cover"
                id="product-thumbs"
                @if (count($galleryPhotos) <= 1) style="display:none" @endif
                data-has-thumbs="{{ count($galleryPhotos) > 1 ? '1' : '0' }}"
            >
                @foreach ($galleryPhotos as $i => $photo)
                    <button type="button" class="{{ $i === 0 ? 'active' : '' }}" data-src="{{ asset('storage/' . $photo) }}" aria-label="Фото {{ $i + 1 }}">
                        <img src="{{ asset('storage/' . $photo) }}" alt="">
                    </button>
                @endforeach
            </div>
        </div>

        <div class="product-detail-column">
            <h1 class="product-title">{{ $listing->title }}</h1>

            <div class="product-badges" id="product-stock-badges">
                @if ($listingStockMode === 'preorder')
                    <span class="badge pre">Передзамовлення</span>
                @elseif ($listingStockMode === 'low')
                    <span class="badge low">Закінчується</span>
                @elseif ($listingStockMode === 'ok')
                    <span class="badge ok">В наявності</span>
                @else
                    <span class="badge no">Немає в наявності</span>
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
                <p class="product-sku">Артикул: {{ $listing->sku }}</p>
            @endif

            @if (count($optionBlocks))
                <div id="product-options" class="product-options-panel" data-has-variants="0">
                    <h2 class="product-options-heading">Опції</h2>
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
                <div class="product-options-readonly product-options-panel" id="product-options-readonly" aria-label="Опції товару">
                    <h2 class="product-options-heading">Опції</h2>
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
                    <button class="btn-product" type="submit" id="product-add-btn">Додати в кошик</button>
                </form>
            </div>
        </div>
    </div>

    @if ($listing->hasRichDescriptionSection())
        <div class="product-desc">
            @if ($listing->hasDisplayableShortDescription())
                <h2>Короткий опис</h2>
                <div class="product-desc__body">{!! $listing->safeShortDescriptionHtml() !!}</div>
            @endif
            @if ($listing->hasDisplayableDescription())
                <h2 style="margin-top:24px;">Опис</h2>
                <div class="product-desc__body">{!! $listing->safeDescriptionHtml() !!}</div>
            @endif
        </div>
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
@endpush
@endsection
