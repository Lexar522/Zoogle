<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" href="{{ asset('favicon.png') }}" type="image/png" sizes="144x144">
    <link rel="shortcut icon" href="{{ asset('favicon.png') }}" type="image/png">
    <link rel="apple-touch-icon" href="{{ asset('favicon.png') }}">
    <script>
    (function () {
        try {
            if (localStorage.getItem('shopCatalogOneCol') === '1') {
                document.documentElement.classList.add('shop-layout--catalog-one-col');
            }
        } catch (e) {}
    })();
    </script>
    <script>
    (function () {
        function stylesApplied() {
            void document.documentElement.offsetWidth;
            return getComputedStyle(document.documentElement).getPropertyValue('--zoogle-style-ok').trim() === '1';
        }

        function zoogleReapplyShopStyles() {
            var nodes = document.querySelectorAll('head style, head link[rel="stylesheet"]');
            var i;
            for (i = 0; i < nodes.length; i++) {
                nodes[i].disabled = true;
            }
            for (i = 0; i < nodes.length; i++) {
                nodes[i].disabled = false;
            }
            if (stylesApplied()) {
                return true;
            }
            var styles = document.querySelectorAll('head style');
            for (i = 0; i < styles.length; i++) {
                var clone = styles[i].cloneNode(true);
                if (styles[i].parentNode) {
                    styles[i].parentNode.replaceChild(clone, styles[i]);
                }
            }
            var links = document.querySelectorAll('head link[rel="stylesheet"]');
            for (i = 0; i < links.length; i++) {
                var href = links[i].getAttribute('href');
                if (href) {
                    links[i].setAttribute('href', href);
                }
            }
            return stylesApplied();
        }

        function revealPendingPhotos() {
            var photos = document.querySelectorAll('.product-card__media img.product-card__photo--pending, .product-card__media img[data-src]');
            var i;
            var img;
            var dataSrc;
            for (i = 0; i < photos.length; i++) {
                img = photos[i];
                dataSrc = img.getAttribute('data-src');
                if (!img.getAttribute('src') && dataSrc) {
                    img.loading = 'eager';
                    img.setAttribute('src', dataSrc);
                }
                img.classList.remove('product-card__photo--pending');
            }
        }

        window.zoogleReapplyShopStyles = zoogleReapplyShopStyles;
        window.zoogleShopStylesApplied = stylesApplied;
        window.zoogleRevealPendingPhotos = revealPendingPhotos;

        function isBackForward(event) {
            if (event && event.persisted) {
                return true;
            }
            try {
                var nav = performance.getEntriesByType('navigation')[0];
                return !!(nav && nav.type === 'back_forward');
            } catch (err) {
                return false;
            }
        }

        window.addEventListener('pageshow', function (event) {
            if (!isBackForward(event)) {
                return;
            }
            if (!event.persisted && stylesApplied()) {
                return;
            }
            if (event.persisted) {
                window.zoogleBackNavigation = true;
                window.setTimeout(function () {
                    window.zoogleBackNavigation = false;
                }, 800);
            }
            zoogleReapplyShopStyles();
            revealPendingPhotos();
            window.requestAnimationFrame(function () {
                if (stylesApplied()) {
                    try { sessionStorage.removeItem('zoogleBackStyleReload'); } catch (err) {}
                    return;
                }
                var once = '';
                try { once = sessionStorage.getItem('zoogleBackStyleReload') || ''; } catch (err) {}
                if (once === '1') {
                    try { sessionStorage.removeItem('zoogleBackStyleReload'); } catch (err2) {}
                    return;
                }
                try { sessionStorage.setItem('zoogleBackStyleReload', '1'); } catch (err3) { return; }
                window.location.reload();
            });
        });
    })();
    </script>
    @php
        $siteName = 'ZOOGLE';
        $defaultSeoTitle = __('shop.layout_default_title');
        $defaultSeoDescription = __('shop.layout_default_description');

        $seoTitle = trim((string) $__env->yieldContent('title', $defaultSeoTitle));
        $seoDescription = trim(preg_replace('/\s+/u', ' ', (string) $__env->yieldContent('meta_description', $defaultSeoDescription)));
        $seoCanonical = trim((string) $__env->yieldContent('canonical_url', url()->current()));
        $seoRobots = trim((string) $__env->yieldContent('robots', 'index,follow'));
        $seoOgType = trim((string) $__env->yieldContent('og_type', 'website'));
        if ($seoOgType === '') {
            $seoOgType = 'website';
        }
        $ogSharePath = public_path('images/og-share.png');
        $defaultOgImage = asset('images/og-share.png');
        if (is_file($ogSharePath)) {
            $defaultOgImage .= '?v='.filemtime($ogSharePath);
        }
        $seoOgTitle = trim((string) $__env->yieldContent('og_title'));
        if ($seoOgTitle === '') {
            $seoOgTitle = $seoTitle;
        }
        $seoOgDescription = trim((string) $__env->yieldContent('og_description'));
        if ($seoOgDescription === '') {
            $seoOgDescription = $seoDescription;
        }
        $seoOgImageOverride = trim((string) $__env->yieldContent('og_image'));
        $seoOgImage = $seoOgImageOverride !== '' ? $seoOgImageOverride : $defaultOgImage;
        $seoOgImageIsDefault = $seoOgImageOverride === '';
        $seoOgImageAlt = trim((string) $__env->yieldContent('og_image_alt'));
        if ($seoOgImageAlt === '') {
            $seoOgImageAlt = $seoOgImageIsDefault ? __('shop.layout_og_image_alt') : $seoOgTitle;
        }
        $seoOgLocale = match (app()->getLocale()) {
            'ru' => 'ru_RU',
            'en' => 'en_US',
            default => 'uk_UA',
        };
    @endphp
    <title>{{ $seoTitle }}</title>
    <meta name="description" content="{{ $seoDescription }}">
    @if ($seoRobots !== '')
        <meta name="robots" content="{{ $seoRobots }}">
    @endif
    @if ($seoCanonical !== '')
        <link rel="canonical" href="{{ $seoCanonical }}">
    @endif
    @php($shopMarketing = \App\Models\ShopMarketingSetting::resolved())
    @if ($shopMarketing['google_site_verification'] !== null)
        <meta name="google-site-verification" content="{{ $shopMarketing['google_site_verification'] }}">
    @endif
    @if ($shopMarketing['facebook_domain_verification'] !== null)
        <meta name="facebook-domain-verification" content="{{ $shopMarketing['facebook_domain_verification'] }}">
    @endif
    <meta property="og:locale" content="{{ $seoOgLocale }}">
    <meta property="og:site_name" content="{{ $siteName }}">
    <meta property="og:type" content="{{ $seoOgType }}">
    <meta property="og:title" content="{{ $seoOgTitle }}">
    <meta property="og:description" content="{{ $seoOgDescription }}">
    <meta property="og:url" content="{{ $seoCanonical !== '' ? $seoCanonical : url()->current() }}">
    @if ($seoOgImage !== '')
        <meta property="og:image" content="{{ $seoOgImage }}">
        <meta property="og:image:alt" content="{{ $seoOgImageAlt }}">
        @if ($seoOgImageIsDefault)
            <meta property="og:image:width" content="1200">
            <meta property="og:image:height" content="630">
            <meta property="og:image:type" content="image/png">
        @endif
    @endif
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $seoOgTitle }}">
    <meta name="twitter:description" content="{{ $seoOgDescription }}">
    @if ($seoOgImage !== '')
        <meta name="twitter:image" content="{{ $seoOgImage }}">
        <meta name="twitter:image:alt" content="{{ $seoOgImageAlt }}">
    @endif
    <meta name="theme-color" content="#367df1">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    @php($shopFontsUrl = 'https://fonts.googleapis.com/css2?family=Rubik:ital,wght@0,400;0,500;0,600;0,700;0,800;1,400;1,600&family=Source+Sans+3:ital,wght@0,400;0,600;0,700;1,400;1,600&display=swap')
    {{-- Шрифти не блокують перший показ сторінки (PageSpeed): текст одразу видно запасним шрифтом --}}
    <link rel="preload" as="style" href="{{ $shopFontsUrl }}">
    <link rel="stylesheet" href="{{ $shopFontsUrl }}" media="print" onload="this.media='all'">
    <noscript><link rel="stylesheet" href="{{ $shopFontsUrl }}"></noscript>
    @stack('meta')
    @vite([
        'resources/css/shop.css',
        'resources/css/category-hub.css',
        'resources/js/category-hub.js',
    ])
    @stack('styles')
</head>
<body>
    <div class="cart-toast-stack" data-cart-toast-stack aria-live="polite" aria-atomic="true"></div>
    @include('partials.shop-header')
    {{-- Одразу після хедера: sticky-фільтри/сайдбар використовують --header-sticky-offset --}}
    <script>
    (function () {
        var h = document.querySelector('.site-header');
        if (!h) return;
        var force = document.body && document.body.dataset.forceHeaderCompact === '1';
        var bar = h.querySelector('[data-site-header-compact-bar]');
        var mb = force ? 0 : (parseFloat(getComputedStyle(h).marginBottom) || 0);
        var headerRect = h.getBoundingClientRect();
        if (force && bar) {
            var rh = Math.ceil(bar.getBoundingClientRect().height);
            h.style.setProperty('--site-header-compact-sticky-height', rh > 0 && rh <= 160 ? rh + 'px' : '92px');
        } else {
            h.style.removeProperty('--site-header-compact-sticky-height');
        }
        var px = Math.ceil(headerRect.height + mb);
        if (!force && px < 180) px = 200;
        document.documentElement.style.setProperty('--header-sticky-offset', px + 'px');
    })();
    </script>

    @include('cart.partials.drawer-shell')
    @include('partials.shop-mobile-menu')

    <div class="site-shell">
        <div class="container site-main">
            @if (session('success'))
                <div class="alert success" role="status">{{ session('success') }}</div>
            @endif
            @if (session('error'))
                <div class="alert error" role="alert">{{ session('error') }}</div>
            @endif

            @hasSection('sidebar')
                <div class="layout-catalog">
                    <aside class="catalog-sidebar" aria-label="Фільтри каталогу">
                        @yield('sidebar')
                    </aside>
                    <div class="catalog-main">
                        @yield('content')
                    </div>
                </div>
            @else
                @yield('content')
            @endif
        </div>
    </div>

    @include('partials.shop-footer')
    @include('partials.shop-floating-contacts')
    @vite(['resources/js/catalog-card-media.js'])
    @include('partials.shop-search-suggest')
    @include('partials.marketing-tags')
    @stack('scripts')
    <script src="{{ \App\Support\StorefrontAsset::url('js/storefront/cart-drawer.js') }}" defer></script>
    <script src="{{ \App\Support\StorefrontAsset::url('js/storefront/header-compact.js') }}" defer></script>
    <script src="{{ \App\Support\StorefrontAsset::url('js/storefront/site-menu.js') }}" defer></script>
</body>
</html>
