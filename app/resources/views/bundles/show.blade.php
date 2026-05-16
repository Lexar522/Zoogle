@extends('layouts.shop')

@section('title', mt($bundle->title).' — ZOOGLE')

@php
    $galleryPhotos = is_array($galleryPhotos ?? null) ? $galleryPhotos : [];
    $bundleItems = $bundleItems ?? collect();
    $priceSubtotal = (float) ($quote['subtotal'] ?? 0);
    $priceDiscount = (float) ($quote['discount'] ?? 0);
    $priceTotal = (float) ($quote['total'] ?? 0);
    $categoryBreadcrumb = is_array($categoryBreadcrumb ?? null) ? $categoryBreadcrumb : [];
    $breadcrumbQueryBase = is_array($breadcrumbQueryBase ?? null) ? $breadcrumbQueryBase : [];
    $bundleSeoDescription = trim(preg_replace('/\s+/u', ' ', strip_tags((string) ($bundle->short_description ?: $bundle->description))));
    if ($bundleSeoDescription === '') {
        $bundleSeoDescription = 'Комплект '.$bundle->title.' у зоомагазині ZOOGLE: прозора ціна, склад набору та швидке оформлення замовлення.';
    }
@endphp

@section('meta_description', \Illuminate\Support\Str::limit($bundleSeoDescription, 155, ''))
@section('canonical_url', route('bundles.show', $bundle->slug))
@section('og_type', 'product')
@section('og_title', mt($bundle->title).' — ZOOGLE')
@section('og_description', \Illuminate\Support\Str::limit($bundleSeoDescription, 155, ''))
@if (count($galleryPhotos))
    @section('og_image', asset('storage/'.$galleryPhotos[0]))
@endif

@push('styles')
<style>
    .bundle-page {
        color: #202124;
        width: min(80vw, 100%);
        margin: clamp(6px, 1vw, 14px) auto clamp(12px, 2vw, 28px);
        padding: 0;
    }
    .bundle-page a { color: #1a73e8; text-decoration: none; }
    .bundle-page a:hover { color: #174ea6; }
    .bundle-breadcrumb {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
        align-items: center;
        margin-bottom: 20px;
        font-size: 13px;
        color: #5f6368;
    }
    .bundle-breadcrumb__sep { opacity: .65; }
    .bundle-breadcrumb__selected {
        color: #3c4043;
        font-weight: 500;
    }
    .bundle-category-chain {
        display: flex;
        flex-wrap: wrap;
        gap: .45rem;
        margin: 0 0 1rem;
    }
    .bundle-category-chain .btn {
        border-radius: 20px;
    }
    .bundle-grid {
        display: grid;
        grid-template-columns: minmax(0, 1.08fr) minmax(360px, 0.92fr);
        gap: clamp(12px, 1.6vw, 22px);
        align-items: start;
    }
    @media (max-width: 980px) {
        .bundle-grid {
            grid-template-columns: 1fr;
        }
    }
    .bundle-main-column {
        min-width: 0;
        display: grid;
        gap: clamp(12px, 1.4vw, 18px);
    }
    .bundle-gallery-card,
    .bundle-summary-card,
    .bundle-description-card,
    .bundle-section {
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
        .bundle-gallery-card:hover,
        .bundle-summary-card:hover,
        .bundle-description-card:hover,
        .bundle-section:hover {
            transform: translateY(-3px);
            border-color: #dbe4ee;
            box-shadow: 0 18px 42px rgba(15, 23, 42, 0.13);
        }
    }
    @media (prefers-reduced-motion: reduce) {
        .bundle-gallery-card,
        .bundle-summary-card,
        .bundle-description-card,
        .bundle-section {
            transition: box-shadow .24s ease, border-color .24s ease;
            will-change: auto;
        }

        .bundle-gallery-card:hover,
        .bundle-summary-card:hover,
        .bundle-description-card:hover,
        .bundle-section:hover {
            transform: none;
        }
    }
    .bundle-gallery-card {
        padding: clamp(12px, 1.6vw, 18px);
    }
    .bundle-summary-column {
        min-width: 0;
    }
    .bundle-summary-card {
        padding: clamp(20px, 2.6vw, 34px);
    }
    @media (min-width: 1024px) {
        .bundle-summary-column {
            position: sticky;
            top: clamp(16px, calc(var(--header-sticky-offset, 0px) + 12px), 96px);
            align-self: start;
        }
        .bundle-summary-card {
            max-height: calc(100vh - clamp(16px, calc(var(--header-sticky-offset, 0px) + 12px), 96px) - 16px);
            overflow-y: auto;
        }
    }
    .bundle-gallery-main {
        background: #f1f3f4;
        border-radius: 20px;
        border: 1px solid #e8eaed;
        aspect-ratio: 1;
        overflow: hidden;
        display: flex;
        align-items: center;
        justify-content: center;
        box-shadow: 0 1px 2px rgba(60, 64, 67, 0.12);
    }
    .bundle-gallery-main img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        object-position: center;
    }
    .bundle-gallery-placeholder {
        color: #5f6368;
        font-size: 14px;
        padding: 24px;
        text-align: center;
    }
    .bundle-thumbs {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        margin-top: 10px;
    }
    .bundle-thumbs button {
        width: 64px;
        height: 64px;
        padding: 0;
        border-radius: 8px;
        border: 1px solid #dadce0;
        background: #fff;
        overflow: hidden;
        cursor: pointer;
        box-shadow: 0 1px 2px rgba(60, 64, 67, 0.1);
        transition: border-color .15s ease, box-shadow .15s ease, transform .15s ease;
    }
    .bundle-thumbs button:hover {
        border-color: #1a73e8;
        box-shadow: 0 1px 4px rgba(60, 64, 67, 0.2);
        transform: translateY(-1px);
    }
    .bundle-thumbs button.active {
        border-color: #1a73e8;
        box-shadow: 0 0 0 1px rgba(26, 115, 232, 0.35);
    }
    .bundle-thumbs img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        object-position: center;
    }
    .bundle-eyebrow {
        margin: 0 0 8px;
        color: #5f6368;
        font-size: 13px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }
    .bundle-title {
        font-size: clamp(1.5rem, 3vw, 2rem);
        font-weight: 700;
        margin: 0 0 12px;
        line-height: 1.2;
        color: #367df1;
    }
    .bundle-badges {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        margin-bottom: 18px;
    }
    .bundle-badge {
        font-size: 12px;
        font-weight: 600;
        padding: 6px 12px;
        border-radius: 8px;
    }
    .bundle-badge--ok { background: #dcfae6; color: #067647; }
    .bundle-badge--sale { background: #fee4e2; color: #b42318; }
    .bundle-badge--meta { background: #f1f3f4; color: #3c4043; }
    .bundle-price-box {
        padding: 18px 20px;
        border: 1px solid #e8eaed;
        border-radius: 16px;
        background: linear-gradient(180deg, #fff 0%, #f8fbff 100%);
        margin-bottom: 18px;
    }
    .bundle-price-box__label {
        margin: 0 0 8px;
        font-size: 13px;
        color: #5f6368;
        font-weight: 600;
    }
    .bundle-price {
        display: flex;
        flex-wrap: wrap;
        align-items: baseline;
        gap: 10px 14px;
        margin: 0 0 10px;
    }
    .bundle-price__old {
        font-size: 1.2rem;
        font-weight: 500;
        color: #80868b;
        text-decoration: line-through;
    }
    .bundle-price__current {
        font-size: 2rem;
        font-weight: 700;
        color: #339a39;
    }
    .bundle-price__currency {
        font-size: .72em;
        color: #5f6368;
        margin-left: 3px;
    }
    .bundle-price-box__meta {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 12px;
    }
    @media (max-width: 640px) {
        .bundle-price-box__meta {
            grid-template-columns: 1fr;
        }
    }
    .bundle-price-box__stat {
        padding: 12px 14px;
        border-radius: 12px;
        background: #fff;
        border: 1px solid #edf0f2;
    }
    .bundle-price-box__stat-label {
        display: block;
        margin-bottom: 4px;
        font-size: 12px;
        color: #5f6368;
    }
    .bundle-price-box__stat-value {
        font-size: 1rem;
        font-weight: 600;
        color: #202124;
    }
    .bundle-short,
    .bundle-description {
        color: #3c4043;
        font-size: 14px;
        line-height: 1.6;
    }
    .bundle-short { margin: 0; }
    .bundle-short--lead {
        margin-bottom: 12px;
        font-size: 15px;
        color: #202124;
    }
    .bundle-description {
        margin: 0;
    }
    .bundle-description-card {
        padding: clamp(18px, 2.4vw, 30px);
    }
    .bundle-description-card__title {
        margin: 0 0 12px;
        font-size: 18px;
        color: #202124;
    }
    .bundle-actions {
        display: flex;
        flex-direction: column;
        gap: 12px;
        margin-top: 22px;
    }
    .bundle-actions .btn-product {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 100%;
        max-width: none;
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
    .bundle-actions .btn-product:hover {
        background: #d62f22;
        filter: brightness(1.03);
        box-shadow: 0 6px 18px rgba(239, 56, 41, 0.45);
    }
    .bundle-section {
        margin-top: 22px;
        padding: clamp(18px, 2.4vw, 30px);
    }
    .bundle-section__title {
        margin: 0 0 18px;
        font-size: 1.25rem;
        color: #202124;
    }
    .bundle-items {
        display: grid;
        gap: 16px;
    }
    .bundle-item {
        display: grid;
        grid-template-columns: 110px minmax(0, 1fr) auto;
        gap: 16px;
        align-items: center;
        padding: 16px;
        border: 1px solid #e8eaed;
        border-radius: 16px;
        background: #fff;
        box-shadow: 0 1px 2px rgba(60, 64, 67, 0.08);
    }
    @media (max-width: 760px) {
        .bundle-item {
            grid-template-columns: 84px minmax(0, 1fr);
        }
    }
    .bundle-item__media {
        width: 110px;
        height: 110px;
        border-radius: 12px;
        border: 1px solid #e8eaed;
        background: #f1f3f4;
        overflow: hidden;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    @media (max-width: 760px) {
        .bundle-item__media {
            width: 84px;
            height: 84px;
        }
    }
    .bundle-item__media img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        object-position: center;
    }
    .bundle-item__media--empty {
        color: #5f6368;
        font-size: 12px;
        text-align: center;
        padding: 8px;
    }
    .bundle-item__title {
        margin: 0 0 6px;
        font-size: 1rem;
        line-height: 1.35;
        color: #202124;
    }
    .bundle-item__meta {
        display: flex;
        flex-wrap: wrap;
        gap: 10px 16px;
        margin-bottom: 8px;
        color: #5f6368;
        font-size: 13px;
    }
    .bundle-item__excerpt {
        margin: 0;
        color: #5f6368;
        font-size: 13px;
        line-height: 1.5;
    }
    .bundle-item__pricing {
        min-width: 180px;
        text-align: right;
        display: flex;
        flex-direction: column;
        gap: 8px;
    }
    @media (max-width: 760px) {
        .bundle-item__pricing {
            grid-column: 1 / -1;
            min-width: 0;
            text-align: left;
        }
    }
    .bundle-item__unit-old {
        font-size: 13px;
        color: #80868b;
        text-decoration: line-through;
    }
    .bundle-item__unit {
        font-size: 1rem;
        font-weight: 600;
        color: #202124;
    }
    .bundle-item__total {
        font-size: 1.1rem;
        font-weight: 700;
        color: #339a39;
    }
    .bundle-item__link {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        align-self: flex-end;
        padding: 10px 14px;
        border-radius: 20px;
        border: 1px solid #dadce0;
        background: #fff;
        color: #1a73e8 !important;
        font-size: 14px;
        font-weight: 600;
        transition: border-color .15s ease, background-color .15s ease;
    }
    .bundle-item__link:hover {
        border-color: #1a73e8;
        background: #f8fbff;
    }

    @media (max-width: 768px) {
        .bundle-page {
            width: 100%;
            margin: 0 auto 18px;
            padding: 0 12px;
        }

        .bundle-breadcrumb {
            margin-bottom: 14px;
            font-size: 12px;
        }

        .bundle-grid {
            gap: 12px;
        }

        .bundle-main-column {
            gap: 12px;
        }

        .bundle-gallery-card,
        .bundle-summary-card,
        .bundle-description-card,
        .bundle-section {
            border-radius: 18px;
        }

        .bundle-gallery-card {
            padding: 10px;
        }

        .bundle-summary-card,
        .bundle-description-card,
        .bundle-section {
            padding: 16px;
        }

        .bundle-title {
            font-size: clamp(1.35rem, 7vw, 1.75rem);
        }

        .bundle-gallery-main {
            border-radius: 16px;
        }

        .bundle-thumbs {
            flex-wrap: nowrap;
            overflow-x: auto;
            padding-bottom: 4px;
            scrollbar-width: thin;
        }

        .bundle-thumbs button {
            width: 58px;
            height: 58px;
            flex: 0 0 auto;
        }

        .bundle-price-box {
            padding: 16px;
            border-radius: 16px;
        }

        .bundle-price__current {
            font-size: 1.72rem;
        }

        .bundle-actions .btn-product {
            max-width: none;
            min-height: 48px;
            border-radius: 20px;
        }

        .bundle-section {
            margin-top: 16px;
        }

        .bundle-item {
            gap: 12px;
            padding: 12px;
            border-radius: 16px;
        }

        .bundle-item__pricing {
            gap: 6px;
        }

        .bundle-item__link {
            width: 100%;
            min-height: 42px;
            align-self: stretch;
        }
    }
</style>
@endpush

@section('content')
    <div class="bundle-page">
        @if (count($categoryBreadcrumb))
            <div class="bundle-category-chain" aria-label="Навігація по категоріях">
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
                                    <img src="{{ asset('storage/' . $photo) }}" alt="">
                                </button>
                            @endforeach
                        </div>
                    @endif
                </div>

                @if (filled($bundle->short_description) || filled($bundle->description))
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
            </div>

            <div class="bundle-summary-column">
                <div class="bundle-summary-card">
                <p class="bundle-eyebrow">Комплект</p>
                <h1 class="bundle-title">{{ mt($bundle->title) }}</h1>

                <div class="bundle-badges">
                    <span class="bundle-badge bundle-badge--ok">Доступний до замовлення</span>
                    @if ($priceDiscount > 0.001)
                        <span class="bundle-badge bundle-badge--sale">Є знижка на комплект</span>
                    @endif
                    <span class="bundle-badge bundle-badge--meta">{{ count($bundleItems) }} товарів у складі</span>
                </div>

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
                            {{ number_format($priceTotal, 2, ',', ' ') }}
                            <span class="bundle-price__currency">₴</span>
                        </span>
                    </div>

                    <div class="bundle-price-box__meta">
                        <div class="bundle-price-box__stat">
                            <span class="bundle-price-box__stat-label">Сума товарів</span>
                            <span class="bundle-price-box__stat-value">{{ number_format($priceSubtotal, 2, ',', ' ') }} ₴</span>
                        </div>
                        <div class="bundle-price-box__stat">
                            <span class="bundle-price-box__stat-label">Знижка комплекту</span>
                            <span class="bundle-price-box__stat-value">{{ number_format($priceDiscount, 2, ',', ' ') }} ₴</span>
                        </div>
                        <div class="bundle-price-box__stat">
                            <span class="bundle-price-box__stat-label">До оплати</span>
                            <span class="bundle-price-box__stat-value">{{ number_format($priceTotal, 2, ',', ' ') }} ₴</span>
                        </div>
                    </div>
                </div>

                <div class="bundle-actions">
                    <form method="post" action="{{ route('bundles.add-to-cart', $bundle) }}" data-cart-add-form>
                        @csrf
                        <button class="btn-product" type="submit">Додати комплект у кошик</button>
                    </form>
                </div>
                </div>
            </div>
        </div>

        <section class="bundle-section">
            <h2 class="bundle-section__title">Склад комплекту</h2>

            <div class="bundle-items">
                @foreach ($bundleItems as $row)
                    <article class="bundle-item">
                        <div class="bundle-item__media @if (! $row['photo']) bundle-item__media--empty @endif">
                            @if ($row['photo'])
                                <img src="{{ asset('storage/' . $row['photo']) }}" alt="{{ $row['title'] }}">
                            @else
                                Немає фото
                            @endif
                        </div>

                        <div>
                            <h3 class="bundle-item__title">{{ $row['title'] }}</h3>
                            <div class="bundle-item__meta">
                                <span>Кількість: {{ $row['qty'] }}</span>
                                <span>Опції не враховуються</span>
                            </div>

                            @if ($row['excerpt'] !== '')
                                <p class="bundle-item__excerpt">{{ $row['excerpt'] }}</p>
                            @endif
                        </div>

                        <div class="bundle-item__pricing">
                            @if (($row['old_unit_price'] ?? null) !== null && (float) $row['old_unit_price'] > (float) $row['unit_price'] + 0.001)
                                <div class="bundle-item__unit-old">
                                    {{ number_format((float) $row['old_unit_price'], 2, ',', ' ') }} ₴ / шт
                                </div>
                            @endif
                            <div class="bundle-item__unit">
                                {{ number_format((float) $row['unit_price'], 2, ',', ' ') }} ₴ / шт
                            </div>
                            <div class="bundle-item__total">
                                {{ number_format((float) $row['line_total'], 2, ',', ' ') }} ₴
                            </div>
                            @if ($row['url'])
                                <a href="{{ $row['url'] }}" class="bundle-item__link">Перейти до товару</a>
                            @endif
                        </div>
                    </article>
                @endforeach
            </div>
        </section>
    </div>
@endsection

@push('scripts')
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
</script>
@endpush
