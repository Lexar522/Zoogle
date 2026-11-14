@extends('layouts.shop')

@section('title', mt($bundle->title).' — ZOOGLE')

@php
    $galleryPhotos = is_array($galleryPhotos ?? null) ? $galleryPhotos : [];
    $bundleItems = $bundleItems ?? collect();
    $priceSubtotal = (float) ($quote['subtotal'] ?? 0);
    $priceDiscount = (float) ($quote['discount'] ?? 0);
    $priceTotal = (float) ($quote['total'] ?? 0);
    $categoryBreadcrumb = is_array($categoryBreadcrumb ?? null) ? $categoryBreadcrumb : [];
    $subcategoryFilterChip = is_array($subcategoryFilterChip ?? null) ? $subcategoryFilterChip : null;
    $breadcrumbQueryBase = is_array($breadcrumbQueryBase ?? null) ? $breadcrumbQueryBase : [];
    $bundleSeoDescription = trim(preg_replace('/\s+/u', ' ', strip_tags((string) ($bundle->short_description ?: $bundle->description))));
    if ($bundleSeoDescription === '') {
        $bundleSeoDescription = 'Комплект '.$bundle->title.' у зоомагазині ZOOGLE: прозора ціна, склад набору та швидке оформлення замовлення.';
    }
@endphp

@section('meta_description', \Illuminate\Support\Str::limit($bundleSeoDescription, 155, ''))
@section('canonical_url', route('bundles.show', $bundle->slug))
@push('styles')
@vite('resources/css/storefront/bundle-show.css')
@endpush

@section('content')
    <div class="bundle-page">
        @if (count($categoryBreadcrumb))
            <div class="product-category-chain-card">
                <div class="product-category-chain" aria-label="Навігація по категоріях">
                    <a href="{{ route('catalog.index', array_filter($breadcrumbQueryBase)) }}">
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
            <nav class="bundle-breadcrumb" aria-label="Хлібні крихти">
                <a href="{{ route('catalog.index') }}">Каталог</a>
                <span class="bundle-breadcrumb__sep">/</span>
                <a href="{{ route('bundles.index') }}">Комплекти</a>
                <span class="bundle-breadcrumb__sep">/</span>
                <span class="bundle-breadcrumb__selected">{{ mt($bundle->title) }}</span>
            </nav>
        @endif

        <div class="bundle-grid">
            <div class="bundle-main-column">
                <div class="bundle-gallery-card">
                    <div class="bundle-gallery-main">
                        @if (count($galleryPhotos))
                            <img src="{{ asset('storage/' . $galleryPhotos[0]) }}" alt="{{ mt($bundle->title) }}" id="bundle-main-img">
                        @else
                            <div class="bundle-gallery-placeholder">Немає фото комплекту</div>
                        @endif
                    </div>

                    @if (count($galleryPhotos) > 1)
                        <div class="bundle-thumbs" aria-label="Мініатюри комплекту">
                            @foreach ($galleryPhotos as $photo)
                                <button type="button" class="{{ $loop->first ? 'active' : '' }}" data-src="{{ asset('storage/' . $photo) }}" aria-label="Фото {{ $loop->iteration }}">
                                    <img src="{{ \App\Support\ListingPhotoDerivative::cardUrlForPath($photo) }}" alt="" decoding="async">
                                </button>
                            @endforeach
                        </div>
                    @endif
                </div>

                @php
                    $hasBundleDescription = filled($bundle->short_description) || filled($bundle->description);
                    $hasBundleItems = count($bundleItems) > 0;
                @endphp

                @if ($hasBundleDescription)
                    <div class="bundle-description bundle-description-card">
                        <h2 class="bundle-description-card__title">Опис комплекту</h2>
                        @if (filled($bundle->short_description))
                            <p class="bundle-short bundle-short--lead">{{ $bundle->short_description }}</p>
                        @endif
                        @if (filled($bundle->description))
                            <div>{!! nl2br(e($bundle->description)) !!}</div>
                        @endif
                    </div>
                @endif

                @if ($hasBundleItems)
                    @php
                        $bundleItemsCount = count($bundleItems);
                        $bundleItemsOverflow = $bundleItemsCount > 5;
                    @endphp
                    <section class="bundle-composition-card">
                        <h2 class="bundle-composition-card__title">Склад комплекту</h2>

                        <div class="bundle-items-wrap" data-bundle-items>
                            <div
                                class="bundle-items @if ($bundleItemsOverflow) is-collapsed @endif"
                                id="bundle-items-list"
                                role="list"
                                @if ($bundleItemsOverflow) tabindex="0" aria-label="Склад комплекту, прокрутіть список" @endif
                            >
                                @foreach ($bundleItems as $row)
                                    <article class="bundle-item" role="listitem">
                                        <div class="bundle-item__main">
                                            <h3 class="bundle-item__title">{{ $row['title'] }}</h3>
                                            <span class="bundle-item__qty">× {{ $row['qty'] }}</span>
                                            @if (! empty($row['option_labels'] ?? []))
                                                <div class="bundle-item__options">
                                                    {{ implode(' · ', $row['option_labels']) }}
                                                </div>
                                            @endif
                                        </div>

                                        <div class="bundle-item__pricing">
                                            @if (($row['old_unit_price'] ?? null) !== null && (float) $row['old_unit_price'] > (float) $row['unit_price'] + 0.001)
                                                <div class="bundle-item__unit-old">
                                                    {{ number_format((float) $row['old_unit_price'], 2, ',', ' ') }} ₴
                                                </div>
                                            @endif
                                            <div class="bundle-item__unit">
                                                {{ number_format((float) $row['unit_price'], 2, ',', ' ') }} ₴ / шт
                                            </div>
                                            <div class="bundle-item__total">
                                                {{ number_format((float) $row['line_total'], 2, ',', ' ') }} ₴
                                            </div>
                                        </div>
                                    </article>
                                @endforeach
                            </div>

                            @if ($bundleItemsOverflow)
                                <button
                                    type="button"
                                    class="bundle-items__toggle"
                                    data-bundle-items-toggle
                                    data-total="{{ $bundleItemsCount }}"
                                    aria-expanded="false"
                                    aria-controls="bundle-items-list"
                                >
                                    Показати всі ({{ $bundleItemsCount }})
                                </button>
                            @endif
                        </div>
                    </section>
                @endif
            </div>

            <div class="bundle-summary-column">
                <div class="bundle-summary-card">
                <p class="bundle-eyebrow">Комплект</p>
                <h1 class="bundle-title">{{ mt($bundle->title) }}</h1>
                @if (filled(trim((string) ($bundle->sku ?? ''))))
                    <p class="bundle-sku">{{ __('shop.pdp_sku', ['sku' => $bundle->sku]) }}</p>
                @endif

                <div class="bundle-badges">
                    <span class="bundle-badge bundle-badge--ok">Доступний до замовлення</span>
                    @if ($priceDiscount > 0.001)
                        <span class="bundle-badge bundle-badge--sale">Є знижка на комплект</span>
                    @endif
                    <span class="bundle-badge bundle-badge--meta">{{ count($bundleItems) }} товарів у складі</span>
                </div>

                @include('catalog.partials.storefront-options-interactive', [
                    'optionBlocks' => $bundleOptionsDisplay ?? [],
                    'panelId' => 'bundle-options',
                ])

                <div class="bundle-price-box">
                    <p class="bundle-price-box__label">Підсумкова ціна комплекту</p>
                    <div class="bundle-price">
                        @if ($priceDiscount > 0.001)
                            <span class="bundle-price__old">
                                {{ number_format($priceSubtotal, 2, ',', ' ') }}
                                <span class="bundle-price__currency">₴</span>
                            </span>
                        @endif
                        <span class="bundle-price__current">
                            <span id="bundle-price-total">{{ number_format($priceTotal, 2, ',', ' ') }}</span>
                            <span class="bundle-price__currency">₴</span>
                        </span>
                    </div>

                    <div class="bundle-price-box__meta">
                        <div class="bundle-price-box__stat">
                            <span class="bundle-price-box__stat-label">Сума товарів</span>
                            <span class="bundle-price-box__stat-value" id="bundle-price-subtotal-stat">{{ number_format($priceSubtotal, 2, ',', ' ') }} ₴</span>
                        </div>
                        <div class="bundle-price-box__stat">
                            <span class="bundle-price-box__stat-label">Знижка комплекту</span>
                            <span class="bundle-price-box__stat-value">{{ number_format($priceDiscount, 2, ',', ' ') }} ₴</span>
                        </div>
                        <div class="bundle-price-box__stat">
                            <span class="bundle-price-box__stat-label">До оплати</span>
                            <span class="bundle-price-box__stat-value" id="bundle-price-pay-stat">{{ number_format($priceTotal, 2, ',', ' ') }} ₴</span>
                        </div>
                    </div>
                </div>

                <div class="bundle-actions">
                    <form method="post" action="{{ route('bundles.add-to-cart', $bundle) }}" data-cart-add-form>
                        @csrf
                        <input type="hidden" name="option_value_ids" value="[]" id="bundle-option-value-ids">
                        <button class="btn-product" type="submit">Додати комплект у кошик</button>
                    </form>
                </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script type="application/json" id="bundle-show-config">@json($bundleShowConfig ?? [])</script>
@php
    $bundleViteReady = file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot'));
@endphp
@if ($bundleViteReady)
    @vite(['resources/js/bundle-show-page.js'])
@else
    <script>
{!! file_get_contents(resource_path('js/bundle-show-page.js')) !!}
    </script>
@endif
<script>
    (function () {
        var main = document.getElementById('bundle-main-img');
        if (!main) return;

        document.querySelectorAll('.bundle-thumbs button[data-src]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var src = btn.getAttribute('data-src');
                if (!src) return;

                main.src = src;
                document.querySelectorAll('.bundle-thumbs button.active').forEach(function (active) {
                    active.classList.remove('active');
                });
                btn.classList.add('active');
            });
        });
    })();

    (function () {
        var wrap = document.querySelector('[data-bundle-items]');
        if (!wrap) return;

        var list = wrap.querySelector('#bundle-items-list');
        var toggle = wrap.querySelector('[data-bundle-items-toggle]');
        if (!list || !toggle) return;

        var total = toggle.getAttribute('data-total') || '';

        toggle.addEventListener('click', function () {
            var expanded = list.classList.toggle('is-expanded');
            list.classList.toggle('is-collapsed', !expanded);
            toggle.setAttribute('aria-expanded', expanded ? 'true' : 'false');
            toggle.textContent = expanded
                ? 'Згорнути список'
                : 'Показати всі (' + total + ')';
        });
    })();
</script>
@endpush
