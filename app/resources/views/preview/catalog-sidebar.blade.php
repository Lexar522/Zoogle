@extends('layouts.shop')

@section('title', 'Приклад каталогу — ZOOGLE')
@section('robots', 'noindex,nofollow')

@section('content')
@php
    $selectedId = (int) ($categoryValueId ?? 0);
    $tree = $categoryNavigationTree ?? [];
    $previewPlaceholders = [
        ['title' => 'Лежак «Хмарка»', 'price' => 890, 'icon' => 'bed'],
        ['title' => 'Миска подвійна', 'price' => 249, 'icon' => 'bowl'],
        ['title' => 'Повідець нейлоновий', 'price' => 179, 'icon' => 'leash'],
        ['title' => 'Наповнювач деревний', 'price' => 320, 'icon' => 'bag'],
        ['title' => 'Канат для собак', 'price' => 145, 'icon' => 'rope'],
        ['title' => 'Шампунь для собак', 'price' => 210, 'icon' => 'bottle'],
        ['title' => 'Переноска', 'price' => 650, 'icon' => 'carrier'],
        ['title' => 'Кігтеточка', 'price' => 430, 'icon' => 'post'],
    ];
    $count = $previewProducts->count() + count($previewPlaceholders);
    $mod10 = $count % 10;
    $mod100 = $count % 100;
    $countWord = ($mod10 === 1 && $mod100 !== 11)
        ? 'позиція'
        : (($mod10 >= 2 && $mod10 <= 4 && ($mod100 < 12 || $mod100 > 14)) ? 'позиції' : 'позицій');
@endphp
<div class="preview-catalog">
    <p class="preview-catalog__note">Приклад макета: категорії зліва, товари справа. Це не замінює поточну головну.</p>
    <div class="preview-catalog__layout">
        <aside class="preview-catalog__side" aria-label="Категорії">
            <p class="preview-catalog__side-title">Категорії</p>
            <nav class="side-nav">
                <a class="side-nav__all @if ($selectedId === 0) is-active @endif" href="{{ route('preview.catalog-sidebar') }}">Усі товари</a>
                <ul class="side-nav__list" role="list">
                    @foreach ($tree as $node)
                        @include('preview.partials.sidebar-node', [
                            'node' => $node,
                            'depth' => 0,
                            'categoryValueId' => $selectedId,
                            'previewOpenCategoryIds' => $previewOpenCategoryIds ?? [],
                        ])
                    @endforeach
                </ul>
            </nav>
        </aside>

        <section class="preview-catalog__main" aria-label="Товари">
            <header class="preview-catalog__head">
                <div>
                    <h1 class="preview-catalog__title">Каталог</h1>
                    <p class="preview-catalog__count">{{ $count }} {{ $countWord }}</p>
                </div>
            </header>
            <div class="grid preview-catalog__grid">
                @foreach ($previewProducts as $listing)
                    @include('catalog.partials.product-card', [
                        'listing' => $listing,
                        'listingQuotes' => $listingQuotes,
                        'favoriteProductIds' => $favoriteProductIds,
                        'cardImagePriority' => $loop->index,
                    ])
                @endforeach
                @foreach ($previewPlaceholders as $placeholder)
                    @include('preview.partials.placeholder-card', ['placeholder' => $placeholder])
                @endforeach
            </div>
        </section>
    </div>
</div>
@endsection

@push('styles')
<style>
    .preview-catalog {
        width: min(1320px, 100%);
        margin: 16px auto 36px;
    }
    .preview-catalog__note {
        margin: 0 0 12px;
        color: #5f6368;
        font-size: 13px;
        line-height: 1.4;
    }
    .preview-catalog__layout {
        display: grid;
        grid-template-columns: 272px minmax(0, 1fr);
        gap: 20px;
        align-items: start;
    }
    .preview-catalog__side {
        position: sticky;
        top: var(--preview-side-top, 12px);
        max-height: calc(100vh - var(--preview-side-top, 12px) - 12px);
        overflow: auto;
        overscroll-behavior: contain;
        scrollbar-gutter: stable;
        background: #fff;
        border: 1px solid #e8eaed;
        border-radius: 16px;
        padding: 12px 4px 8px 8px;
    }
    .preview-catalog__side-title {
        margin: 0 8px 8px;
        color: #202124;
        font-size: 14px;
        font-weight: 500;
        letter-spacing: 0.1px;
    }
    .side-nav__all,
    .side-nav__link {
        display: flex;
        align-items: center;
        min-height: 40px;
        padding: 0 12px;
        border-radius: 8px;
        color: #3c4043;
        font-size: 14px;
        font-weight: 400;
        line-height: 20px;
        text-decoration: none;
    }
    .side-nav__all {
        margin: 0 0 4px;
    }
    .side-nav__all.is-active,
    .side-nav__item.is-active > .side-nav__row .side-nav__link {
        background: #e8f0fe;
        color: #1967d2;
        font-weight: 500;
    }
    .side-nav__all:hover,
    .side-nav__link:hover,
    .side-nav__expand:hover {
        background: #f1f3f4;
    }
    .side-nav__all.is-active:hover,
    .side-nav__item.is-active > .side-nav__row .side-nav__link:hover {
        background: #d2e3fc;
    }
    .side-nav__list,
    .side-nav__sub {
        list-style: none;
        margin: 0;
        padding: 0;
    }
    .side-nav__sub {
        display: none;
        margin-left: 12px;
    }
    .side-nav__item.is-open > .side-nav__sub {
        display: block;
    }
    .side-nav__row {
        display: flex;
        align-items: center;
        gap: 2px;
    }
    .side-nav__link {
        flex: 1 1 auto;
        min-width: 0;
    }
    .side-nav__label {
        display: block;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    .side-nav__expand {
        flex: 0 0 32px;
        width: 32px;
        height: 32px;
        border: 0;
        border-radius: 50%;
        background: transparent;
        color: #5f6368;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
    }
    .side-nav__item.is-open > .side-nav__row .side-nav__expand svg {
        transform: rotate(180deg);
    }
    .preview-catalog__main {
        min-width: 0;
        background: #fff;
        border: 1px solid #e8eaed;
        border-radius: 16px;
        padding: 16px 16px 20px;
    }
    .preview-catalog__head {
        margin-bottom: 16px;
        padding-bottom: 12px;
        border-bottom: 1px solid #e8eaed;
    }
    .preview-catalog__title {
        margin: 0;
        color: #202124;
        font-size: 22px;
        font-weight: 500;
        letter-spacing: 0;
        line-height: 28px;
    }
    .preview-catalog__count,
    .preview-catalog__empty {
        margin: 4px 0 0;
        color: #5f6368;
        font-size: 14px;
        line-height: 20px;
    }
    .preview-catalog__grid {
        --product-card-col-min: 220px;
        gap: 16px;
        grid-template-columns: repeat(auto-fill, minmax(min(100%, max(220px, calc((100% - 48px) / 4))), 1fr));
    }
    .preview-catalog__grid .product-card__body {
        padding: 12px 12px 12px;
    }
    .preview-catalog__grid .product-card__footer {
        flex-wrap: nowrap;
        align-items: center;
        gap: 8px;
    }
    .preview-catalog__grid .product-card__price-inline {
        flex: 1 1 auto;
        min-width: 0;
        overflow: visible;
    }
    .preview-catalog__grid .product-card__price,
    .preview-catalog__grid .product-card__price--sale {
        font-size: 1.05rem;
    }
    .preview-catalog__grid .product-card__action-buttons {
        gap: 6px;
    }
    .preview-catalog__grid .product-card__cart-react,
    .preview-catalog__grid .product-card__like-react {
        padding: 3px;
    }
    .preview-catalog__grid .product-card__cart-react .product-card__cart-add,
    .preview-catalog__grid .product-card__like-react .product-card__favorite {
        width: 30px;
        min-width: 30px;
        height: 30px;
        min-height: 30px;
    }
    .preview-placeholder__icon {
        width: 64px;
        height: 64px;
        color: #80868b;
    }
    .product-card--placeholder .product-card__cart-add,
    .product-card--placeholder .product-card__favorite {
        pointer-events: none;
    }
    .preview-catalog__tip {
        position: fixed;
        z-index: 4000;
        max-width: min(280px, calc(100vw - 16px));
        padding: 6px 8px;
        border-radius: 4px;
        background: #3c4043;
        color: #fff;
        font-size: 12px;
        font-weight: 400;
        line-height: 16px;
        letter-spacing: 0.2px;
        pointer-events: none;
        opacity: 0;
        visibility: hidden;
    }
    .preview-catalog__tip.is-visible {
        opacity: 1;
        visibility: visible;
    }
    @media (max-width: 900px) {
        .preview-catalog__layout {
            grid-template-columns: 1fr;
        }
        .preview-catalog__side {
            position: static;
            max-height: 280px;
        }
    }
</style>
@endpush

@push('scripts')
<script>
(function () {
    var header = document.querySelector('.site-header');

    function syncPreviewSideSticky() {
        var top = 12;
        var bar = header && header.querySelector('[data-site-header-compact-bar]');
        if (header && header.classList.contains('site-header--compact-visible') && bar) {
            var height = Math.ceil(bar.getBoundingClientRect().height);
            if (height > 0 && height < 200) top = height + 12;
        }
        document.documentElement.style.setProperty('--preview-side-top', top + 'px');
    }

    syncPreviewSideSticky();
    if (header && window.MutationObserver) {
        new MutationObserver(syncPreviewSideSticky).observe(header, { attributes: true, attributeFilter: ['class'] });
    }
    window.addEventListener('resize', syncPreviewSideSticky);

    document.querySelectorAll('.side-nav__expand').forEach(function (button) {
        button.addEventListener('click', function () {
            var item = button.closest('.side-nav__item');
            if (!item) return;
            var open = item.classList.toggle('is-open');
            button.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
    });

    var timer = null;
    var current = null;
    var tip = null;

    function hideTip() {
        if (timer) {
            clearTimeout(timer);
            timer = null;
        }
        current = null;
        if (tip) tip.classList.remove('is-visible');
    }

    function showTip(label) {
        var text = (label.textContent || '').trim();
        if (text === '' || label.scrollWidth <= label.clientWidth + 1) return;
        if (!tip) {
            tip = document.createElement('div');
            tip.className = 'preview-catalog__tip';
            tip.setAttribute('role', 'tooltip');
            document.body.appendChild(tip);
        }
        tip.textContent = text;
        tip.classList.add('is-visible');
        tip.style.left = '0px';
        tip.style.top = '0px';
        var rect = label.getBoundingClientRect();
        var tipRect = tip.getBoundingClientRect();
        var left = Math.max(8, Math.min(rect.left, window.innerWidth - tipRect.width - 8));
        var top = rect.bottom + 6;
        if (top + tipRect.height > window.innerHeight - 8) {
            top = Math.max(8, rect.top - tipRect.height - 6);
        }
        tip.style.left = Math.round(left) + 'px';
        tip.style.top = Math.round(top) + 'px';
    }

    document.querySelectorAll('.side-nav__label').forEach(function (label) {
        label.addEventListener('mouseenter', function () {
            hideTip();
            current = label;
            timer = setTimeout(function () {
                timer = null;
                if (current === label) showTip(label);
            }, 1000);
        });
        label.addEventListener('mouseleave', hideTip);
    });
})();
</script>
@endpush
