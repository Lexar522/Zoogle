@extends('layouts.shop')

@section('title', mt($listing->title) . ' — ZOOGLE')
@section('meta_description', $pdpMetaDescription ?? mt($listing->title))
@section('og_title', mt($listing->title) . ' — ZOOGLE')
@section('og_description', $pdpMetaDescription ?? mt($listing->title))
@section('og_type', 'product')
@section('og_image', $pdpOgImageUrl ?? '')
@section('canonical_url', route('catalog.show', $listing->slug))
@section('robots', config('shop.seo_index_product_pages') ? 'index,follow' : 'noindex,follow')
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
        ];

        $schemaLowPrice = number_format((float) ($pdpOfferLowPrice ?? $listingBasePrice ?? 0), 2, '.', '');
        $schemaHighPrice = number_format((float) ($pdpOfferHighPrice ?? $listingBasePrice ?? 0), 2, '.', '');
        // Для безкоштовних оголошень Google потрібен Offer; діапазон цін можна описати лише як AggregateOffer.
        $schemaProduct['offers'] = $schemaLowPrice === $schemaHighPrice
            ? [
                '@type' => 'Offer',
                'url' => route('catalog.show', $listing->slug),
                'priceCurrency' => 'UAH',
                'price' => $schemaLowPrice,
                'itemCondition' => 'https://schema.org/NewCondition',
                'availability' => $schemaAvailability,
            ]
            : [
                '@type' => 'AggregateOffer',
                'priceCurrency' => 'UAH',
                'lowPrice' => $schemaLowPrice,
                'highPrice' => $schemaHighPrice,
                'offerCount' => max(1, (int) ($pdpOfferCount ?? 1)),
                'availability' => $schemaAvailability,
            ];

        if (! empty($categoryDisplayForPage)) {
            $schemaProduct['category'] = (string) $categoryDisplayForPage;
        }
        $schemaProduct['brand'] = [
            '@type' => 'Brand',
            'name' => 'ZOOGLE',
        ];

        if (! empty($pdpOgImageUrl)) {
            $schemaProduct['image'] = [$pdpOgImageUrl];
        }

        $schemaCrumbs = [
            ['name' => 'ZOOGLE', 'url' => route('home')],
        ];
        foreach ($categoryBreadcrumb ?? [] as $crumb) {
            $crumbName = trim((string) ($crumb['name'] ?? ''));
            $crumbId = (int) ($crumb['id'] ?? 0);
            if ($crumbName === '') {
                continue;
            }
            $schemaCrumbs[] = [
                'name' => $crumbName,
                'url' => $crumbId > 0 ? route('catalog.index', ['category' => $crumbId]) : route('catalog.index'),
            ];
        }
        $schemaCrumbs[] = [
            'name' => mt($listing->title),
            'url' => route('catalog.show', $listing->slug),
        ];
        $schemaBreadcrumb = [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => array_map(
                fn (array $crumb, int $index): array => [
                    '@type' => 'ListItem',
                    'position' => $index + 1,
                    'name' => $crumb['name'],
                    'item' => $crumb['url'],
                ],
                $schemaCrumbs,
                array_keys($schemaCrumbs),
            ),
        ];
    @endphp
    <script type="application/ld+json">
{!! json_encode($schemaProduct, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) !!}
    </script>
    <script type="application/ld+json">
{!! json_encode($schemaBreadcrumb, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) !!}
    </script>
    <meta property="product:price:amount" content="{{ $schemaLowPrice }}">
    <meta property="product:price:currency" content="UAH">
@endpush

@php
    $galleryPhotos = is_array($galleryPhotos ?? null) ? $galleryPhotos : [];
    $galleryItems = is_array($galleryItems ?? null) ? $galleryItems : [];
    if ($galleryItems === [] && $galleryPhotos !== []) {
        $galleryItems = array_map(
            static fn (string $path): array => \App\Support\ListingMedia::storefrontItemForPath($path, []),
            $galleryPhotos
        );
    }

    $categoryDisplayForPage = $categoryDisplayForPage ?? $categoryLabel ?? null;
    $listingBasePrice = (float) ($listingBasePrice ?? 0);
    $listingBaseCompareAt = isset($listingBaseCompareAt) && $listingBaseCompareAt !== null ? (float) $listingBaseCompareAt : null;
    $listingStockMode = $listingStockMode ?? 'none';
    $optionsIncomplete = (bool) ($optionsIncomplete ?? false);

    $defaultOptionSelection = $defaultOptionSelection ?? [];
    $optionsPreselectedFromVariant = $optionsPreselectedFromVariant ?? false;
    $productOptionsDisplay = is_array($productOptionsDisplay ?? null) ? $productOptionsDisplay : [];
    $storefrontDefaultVariantId = $storefrontDefaultVariantId ?? $matchedVariantId ?? null;
    $needsBareVariantPicker = (bool) ($needsBareVariantPicker ?? false);
    $barePickerPreselect = $storefrontDefaultVariantId;
    $variantsPayload = is_array($variantsPayload ?? null) ? $variantsPayload : [];
    $matchedVariantId = isset($matchedVariantId) ? (int) $matchedVariantId : (int) ($storefrontDefaultVariantId ?? 0);
    $categoryBreadcrumb = is_array($categoryBreadcrumb ?? null) ? $categoryBreadcrumb : [];
    $subcategoryFilterChip = is_array($subcategoryFilterChip ?? null) ? $subcategoryFilterChip : null;
    $breadcrumbQueryBase = array_filter([
        'on_sale' => request()->boolean('on_sale') ? 1 : null,
        'category' => request()->integer('category') > 0 ? request()->integer('category') : null,
    ], fn ($v) => $v !== null && $v !== '');

    $formatDimensionCm = static function (mixed $value): ?string {
        if ($value === null || $value === '') {
            return null;
        }

        $number = (float) $value;
        if ($number <= 0) {
            return null;
        }

        return rtrim(rtrim(number_format($number, 2, '.', ''), '0'), '.');
    };
    $productDimensionValues = [
        'length' => $formatDimensionCm($listing->length_cm ?? null),
        'width' => $formatDimensionCm($listing->width_cm ?? null),
        'height' => $formatDimensionCm($listing->height_cm ?? null),
    ];
    $productDimensionRows = array_filter([
        $productDimensionValues['length'] !== null ? 'Довжина: '.$productDimensionValues['length'].' см.' : null,
        $productDimensionValues['width'] !== null ? 'Ширина: '.$productDimensionValues['width'].' см.' : null,
        $productDimensionValues['height'] !== null ? 'Висота: '.$productDimensionValues['height'].' см.' : null,
    ]);
@endphp

@push('styles')
@vite('resources/css/storefront/product-show.css')
@endpush

@section('content')
<div class="product-page" data-favorite-ids="{{ e(json_encode($favoriteProductIds ?? [])) }}">
    @if (count($categoryBreadcrumb))
        <div class="product-category-chain-card">
            <div class="product-category-chain" aria-label="Навігація по категоріях">
                <a
                    href="{{ route('catalog.index', array_filter($breadcrumbQueryBase)) }}"
                >
                    {{ __('shop.catalog_toolbar_catalog') }}
                </a>
                @foreach ($categoryBreadcrumb as $catPart)
                    @php
                        $categoryLinkId = (int) ($catPart['id'] ?? 0);
                        $categoryLinkQuery = array_filter(
                            array_merge(
                                $breadcrumbQueryBase,
                                $categoryLinkId > 0 ? ['category' => $categoryLinkId] : []
                            ),
                            fn ($v) => $v !== null && $v !== ''
                        );
                    @endphp
                    <span class="product-category-chain__sep" aria-hidden="true">&gt;</span>
                    <a href="{{ route('catalog.index', $categoryLinkQuery) }}">
                        {{ $catPart['name'] ?? '' }}
                    </a>
                @endforeach
                @if (! empty($subcategoryFilterChip))
                    <span class="product-category-chain__sep" aria-hidden="true">&gt;</span>
                    <a href="{{ $subcategoryFilterChip['href'] }}">{{ $subcategoryFilterChip['label'] }}</a>
                @endif
            </div>
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
                        class="product-gallery-main product-gallery-main--fit-cover @if (count($galleryItems) > 1) product-gallery-main--multi-img @endif"
                        id="product-main-visual"
                    >
                        @if (count($galleryItems))
                            @include('catalog.partials.product-gallery-media', [
                                'item' => $galleryItems[0],
                                'id' => ($galleryItems[0]['type'] ?? 'image') === 'video' ? 'product-main-video' : 'product-main-img',
                                'alt' => mt($listing->title),
                                'autoplay' => ($galleryItems[0]['type'] ?? 'image') === 'video',
                                'loop' => ($galleryItems[0]['type'] ?? 'image') === 'video',
                            ])
                        @else
                            <span class="placeholder">{{ __('shop.pdp_no_photo') }}</span>
                        @endif
                    </div>
                    <div
                        class="product-thumbs product-thumbs--fit-cover"
                        id="product-thumbs"
                        @if (count($galleryItems) <= 1) style="display:none" @endif
                        data-has-thumbs="{{ count($galleryItems) > 1 ? '1' : '0' }}"
                    >
                        @foreach ($galleryItems as $i => $item)
                            @php
                                $optimizeThumbs = \App\Models\ShopMediaSetting::optimizeImagesEnabled();
                                $thumbUrl = $optimizeThumbs ? (string) ($item['card_url'] ?? '') : '';
                                if ($thumbUrl === '') {
                                    $thumbUrl = (string) (($item['type'] ?? '') === 'video' ? ($item['poster_url'] ?? '') : ($item['url'] ?? ''));
                                }
                                $thumbIsVideo = ! $optimizeThumbs && ($item['type'] ?? '') === 'video' && (string) ($item['url'] ?? '') !== '';
                                $thumbIsGif = ! $optimizeThumbs && ($item['type'] ?? '') === 'gif';
                            @endphp
                            <button type="button" class="{{ $i === 0 ? 'active' : '' }}" data-gallery-index="{{ $i }}" aria-label="{{ __('shop.pdp_photo_aria', ['n' => $i + 1]) }}">
                                @if ($thumbIsVideo)
                                    <video src="{{ $item['url'] }}" muted playsinline loop autoplay preload="auto" data-zoogle-animated-media="video" style="{{ $item['object_position'] ?? '' }}"></video>
                                @elseif ($thumbUrl !== '')
                                    <img
                                        src="{{ $thumbUrl }}"
                                        alt=""
                                        style="{{ $item['object_position'] ?? '' }}"
                                        @if ($thumbIsGif) data-zoogle-animated-media="gif" data-zoogle-animated-gif="1" loading="eager" decoding="sync" @else decoding="async" @endif
                                    >
                                @endif
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
                        <div class="product-care-section__intro">
                            <h2 id="product-care-heading" class="product-care-section__title">{{ __('shop.pdp_care_section_title') }}</h2>
                        </div>
                        <a class="product-care-section__all-btn" href="{{ route('catalog.care.index', $listing->slug) }}">{{ __('shop.pdp_care_all') }}</a>
                    </div>
                    <div class="product-care-grid">
                        @foreach ($careArticles->take(3) as $article)
                            <a class="product-care-card" href="{{ $article->storefrontUrl() }}">
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
            <p class="product-price-note" id="product-price-note"></p>
            @if (filled(trim((string) ($listing->sku ?? ''))) || $productDimensionRows !== [])
                <div class="product-meta">
                    @if (filled(trim((string) ($listing->sku ?? ''))))
                        <p class="product-sku">{{ __('shop.pdp_sku', ['sku' => $listing->sku]) }}</p>
                    @endif
                    @if ($productDimensionRows !== [])
                        <div class="product-dimensions">
                            @foreach ($productDimensionRows as $dimensionRow)
                                <p class="product-dimension-row">{{ $dimensionRow }}</p>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endif
            </div>

            <div class="product-buy-panel">
            @if (count($optionBlocks) || $needsBareVariantPicker)
                <div id="product-options" class="product-options-panel" data-has-variants="{{ count($variantsPayload) > 0 ? '1' : '0' }}">
                    <h2 class="product-options-heading">{{ __('shop.pdp_options_heading') }}</h2>
                    @if ($needsBareVariantPicker && $variantsPayload !== [])
                        <div class="product-variant-picker">
                            <label class="product-variant-picker__label" for="product-variant-picker">{{ __('shop.pdp_variant_picker_label') }}</label>
                            <select id="product-variant-picker" class="product-variant-picker__select">
                                @foreach ($variantsPayload as $variantRow)
                                    <option
                                        value="{{ (int) ($variantRow['id'] ?? 0) }}"
                                        @selected((int) ($variantRow['id'] ?? 0) === (int) $barePickerPreselect)
                                    >
                                        {{ $variantRow['label'] ?? ('#'.($variantRow['id'] ?? '')) }}
                                        — {{ number_format((float) ($variantRow['price'] ?? 0), 0, '', ' ') }} ₴
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    @endif
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
                            $basePriceValueId = collect($block['values'])->first(
                                fn (array $val): bool => ! empty($val['is_base_price'])
                            )['id'] ?? null;
                            $basePriceValueId = $basePriceValueId !== null ? (int) $basePriceValueId : null;
                        @endphp
                        @php
                            $exclusiveForVariantId = (int) ($block['exclusive_for_variant_id'] ?? 0);
                            $exclusiveApplies = $exclusiveForVariantId <= 0
                                || $exclusiveForVariantId === (int) $matchedVariantId;
                        @endphp
                        <div
                            class="opt-group {{ $exclusiveForVariantId > 0 && ! $exclusiveApplies ? 'opt-group--exclusive-hidden' : '' }}"
                            data-group-id="{{ $block['id'] }}"
                            data-mode="{{ $block['selection_mode'] }}"
                            data-value-type="{{ $block['value_type'] }}"
                            @if ($exclusiveForVariantId > 0) data-exclusive-for-variant-id="{{ $exclusiveForVariantId }}" @endif
                            @if (! empty($block['exclusivity_key'])) data-exclusivity-key="{{ $block['exclusivity_key'] }}" @endif
                        >
                            <span class="opt-label">{{ $block['name'] }}</span>
                            @if (($block['value_type'] ?? 'text') === 'color')
                                <div class="opt-colors" role="group">
                                    @foreach ($block['values'] as $val)
                                        @php
                                            if ($exclusiveForVariantId > 0 && ! $exclusiveApplies) {
                                                $isOptActive = false;
                                            } elseif ($isMultipleBlock) {
                                                $isOptActive = $hasTargetInBlock
                                                    && in_array((int) $val['id'], $targetIds, true);
                                            } elseif ($hasTargetInBlock) {
                                                $isOptActive = (int) $val['id'] === $targetValueIdForSingle;
                                            } elseif (! empty($block['exclusivity_key'])) {
                                                // Сімʼя «Назва: …» — без дефолту в цій групі нічого не підсвічуємо
                                                $isOptActive = false;
                                            } else {
                                                $isOptActive = ($basePriceValueId !== null && (int) $val['id'] === $basePriceValueId)
                                                    || ($basePriceValueId === null && $loop->first);
                                            }
                                        @endphp
                                        @php
                                            $swatchPath = isset($val['swatch_image']) && is_string($val['swatch_image']) ? trim($val['swatch_image']) : '';
                                            $swatchHasImg = $swatchPath !== '' && ! filled($val['color_hex'] ?? null);
                                        @endphp
                                        <button
                                            type="button"
                                            class="opt-swatch {{ $isOptActive ? 'active' : '' }} {{ ! empty($val['is_base_price']) ? 'opt-swatch--base-price' : '' }} {{ $swatchHasImg ? 'opt-swatch--has-img' : '' }}"
                                            data-value-id="{{ $val['id'] }}"
                                            @if (! empty($val['is_base_price'])) data-is-base-price="1" @endif
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
                                                $isOptActive = $hasTargetInBlock
                                                    && in_array((int) $val['id'], $targetIds, true);
                                            } elseif ($hasTargetInBlock) {
                                                $isOptActive = (int) $val['id'] === $targetValueIdForSingle;
                                            } elseif (! empty($block['exclusivity_key'])) {
                                                $isOptActive = false;
                                            } else {
                                                $isOptActive = ($basePriceValueId !== null && (int) $val['id'] === $basePriceValueId)
                                                    || ($basePriceValueId === null && $loop->first);
                                            }
                                        @endphp
                                        <button
                                            type="button"
                                            class="opt-chip {{ $isOptActive ? 'active' : '' }} {{ ! empty($val['is_base_price']) ? 'opt-chip--base-price' : '' }}"
                                            data-value-id="{{ $val['id'] }}"
                                            @if (! empty($val['is_base_price'])) data-is-base-price="1" @endif
                                        >
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
                @include('catalog.partials.storefront-options-readonly', [
                    'optionBlocks' => $productOptionsDisplay,
                    'storefrontDefaultVariantId' => $storefrontDefaultVariantId,
                    'panelId' => 'product-options-readonly',
                ])
            @endif

            <p class="product-hint" id="product-variant-hint"></p>

            <div class="product-actions">
                <form method="POST" action="{{ route('cart.store') }}" id="product-cart-form" data-cart-add-form>
                    @csrf
                    <input type="hidden" name="product_id" value="{{ $listing->id }}">
                    <input type="hidden" name="option_value_ids" value="[]" id="input-option-value-ids">
                    <input type="hidden" name="variant_id" value="{{ (int) ($matchedVariantId ?? 0) }}" id="input-variant-id">
                    <button class="btn-product" type="submit" id="product-add-btn" @disabled($listingStockMode === 'none' || ! empty($optionsIncomplete))>{{ __('shop.pdp_add_to_cart') }}</button>
                </form>
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
            class="home-product-carousel home-product-carousel--mobile-feed"
            data-home-carousel
            role="region"
            data-carousel-roledescription="{{ __('shop.aria_carousel') }}"
            data-list-roledescription="{{ __('shop.aria_product_list') }}"
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

@if (\App\Support\MarketingTags::enabled())
    @push('scripts')
        <script>
            window.zgCurrentItem = @json(['id' => (string) $listing->id, 'name' => mt($listing->title), 'price' => (float) ($pdpOfferLowPrice ?? $listingBasePrice ?? 0)]);
            window.zgTrack('view_item', { currency: 'UAH', value: window.zgCurrentItem.price, items: [window.zgCurrentItem] });
        </script>
    @endpush
@endif
