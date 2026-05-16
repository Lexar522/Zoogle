<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <script>
        (function () {
            try {
                if (localStorage.getItem('shopCatalogOneCol') === '1') {
                    document.documentElement.classList.add('shop-layout--catalog-one-col');
                }
            } catch (e) {}
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
        $seoOgTitle = trim((string) $__env->yieldContent('og_title', $seoTitle));
        $seoOgDescription = trim(preg_replace('/\s+/u', ' ', (string) $__env->yieldContent('og_description', $seoDescription)));
        $seoOgImage = trim((string) $__env->yieldContent('og_image', ''));
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
    <meta property="og:locale" content="{{ $seoOgLocale }}">
    <meta property="og:site_name" content="{{ $siteName }}">
    <meta property="og:type" content="{{ $seoOgType }}">
    <meta property="og:title" content="{{ $seoOgTitle }}">
    <meta property="og:description" content="{{ $seoOgDescription }}">
    <meta property="og:url" content="{{ $seoCanonical !== '' ? $seoCanonical : url()->current() }}">
    @if ($seoOgImage !== '')
        <meta property="og:image" content="{{ $seoOgImage }}">
    @endif
    <meta name="twitter:card" content="{{ $seoOgImage !== '' ? 'summary_large_image' : 'summary' }}">
    <meta name="twitter:title" content="{{ $seoOgTitle }}">
    <meta name="twitter:description" content="{{ $seoOgDescription }}">
    @if ($seoOgImage !== '')
        <meta name="twitter:image" content="{{ $seoOgImage }}">
    @endif
    <meta name="theme-color" content="#367df1">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Rubik:ital,wght@0,300..900;1,300..900&display=swap" rel="stylesheet">
    @stack('meta')
    <script>window.__SHOP = @json(trans('shop'));</script>
    <style>
        /* Плавне догравання липкого top (фільтри/сайдбар) без зміни тривалості анімації хедера */
        @property --header-sticky-offset {
            syntax: '<length>';
            inherits: true;
            initial-value: 248px;
        }
        :root {
            /* Палітра: синій — текст; червоний — CTA; зелений — ціна / «купити»; жовтий — акценти */
            --bg: #f0f2f6;
            --surface: #ffffff;
            --text: #367df1;
            --muted: #667085;
            --border: #e4e7ec;
            --header-bg: #ffffff;
            --header-text: #367df1;
            --color-cta: #ef3829;
            --color-cta-hover: #d62f22;
            --color-cta-rgb: 239, 56, 41;
            --color-price: #339a39;
            --color-buy: #339a39;
            --color-buy-hover: #2d8630;
            --color-buy-rgb: 51, 154, 57;
            --color-accent: #feb400;
            --color-accent-rgb: 254, 180, 0;
            --radius: 12px;
            /* Каталог: панель результатів, картки товарів, чіпи сортування, пагінація */
            --shop-radius-catalog: 20px;
            --shadow: 0 1px 3px rgba(16, 24, 40, 0.08);
            --shadow-hover-lift: 0 10px 32px rgba(16, 24, 40, 0.14);
            /* Тіні кнопок як у .product-card__btn */
            --shop-product-btn-shadow: 0 10px 18px rgba(15, 23, 42, 0.14);
            --shop-product-btn-shadow-hover: 0 0 0 4px rgba(54, 125, 241, 0.14), 0 16px 24px rgba(15, 23, 42, 0.16);
            --shop-product-btn-shadow-hover-green: 0 0 0 4px rgba(51, 155, 59, 0.22), 0 16px 24px rgba(15, 23, 42, 0.16);
            --shop-product-btn-shadow-hover-cta: 0 0 0 4px rgba(var(--color-cta-rgb), 0.22), 0 16px 24px rgba(15, 23, 42, 0.16);
            --font: "Rubik", system-ui, -apple-system, "Segoe UI", Roboto, Arial, sans-serif;
            --font-weight-base: 600;
            --logo-width: 506px;
            /* Shell / «зліплення» категорій з хедером (catalog/index); JS одразу підставляє точніший --header-sticky-offset */
            --shop-shell-pad-top: 16px;
            --shop-header-margin-bottom: 12px;
            /* Має бути близько до реальної висоти хеда + margin-bottom, інакше перший кадр зміщує липкий рядок (CLS) */
            --header-sticky-offset: 248px;
            /* Картка товару в каталозі: мін. ширина колонки + співвідношення сторін фото */
            --product-card-col-min: 260px;
            --product-card-photo-ratio: 1 / 1;
            /* Плавні hover по всьому магазину */
            --ease-hover: cubic-bezier(0.4, 0, 0.2, 1);
            --duration-hover: 0.32s;
            /* Швидший морф хедера: менше часу на repaint/reflow, менше відчуття лагу */
            --header-motion-duration: 0.36s;
            --header-motion-ease: cubic-bezier(0.17, 1, 0.3, 1);
            /* Трохи довше для зміни висоти / сітки — виглядає м’якше за hover */
            --header-resize-duration: 0.42s;
            --header-resize-ease: cubic-bezier(0.22, 1, 0.36, 1);
            /* Згладжування стрибків JS оновлення --header-sticky-offset; не довше за кадр анімації хедера */
            --header-sticky-offset-transition-duration: 0.22s;
            --header-sticky-offset-transition-ease: cubic-bezier(0.32, 0.94, 0.34, 1);
            transition:
                --header-sticky-offset var(--header-sticky-offset-transition-duration) var(--header-sticky-offset-transition-ease);
        }
        @media (prefers-reduced-motion: reduce) {
            :root {
                --duration-hover: 0.01ms;
                --header-motion-duration: 0.01ms;
                --header-resize-duration: 0.01ms;
                --header-sticky-offset-transition-duration: 0.01ms;
            }
        }
        *, *::before, *::after { box-sizing: border-box; }
        /* Щоб скрол до якорів / фокус не ховав блоки під розгорнутим хедером */
        html {
            scroll-padding-top: var(--header-sticky-offset);
            font-family: var(--font);
            font-optical-sizing: auto;
            font-weight: var(--font-weight-base);
            background: var(--bg);
            min-height: 100%;
        }
        body {
            margin: 0;
            min-height: 100vh;
            min-width: 0;
            display: flex;
            flex-direction: column;
            font-family: var(--font);
            font-optical-sizing: auto;
            font-weight: var(--font-weight-base);
            background: var(--bg);
            color: var(--text);
        }
        /* Браузери часто задають system UI для форм — успадковуємо Rubik і вагу, як у чипах каталогу */
        button,
        input,
        textarea,
        select,
        optgroup,
        ::file-selector-button {
            font-family: inherit;
            font-optical-sizing: inherit;
            font-weight: inherit;
        }
        /* На всю ширину вікна з комфортними полями по краях */
        .container {
            width: 100%;
            max-width: 100%;
            margin: 0;
            padding: 0 clamp(16px, 3vw, 48px);
        }
        @media (max-width: 640px) {
            .container {
                padding-left: max(12px, env(safe-area-inset-left, 0px));
                padding-right: max(12px, env(safe-area-inset-right, 0px));
            }
        }

        /* Header */
        .site-header {
            flex-shrink: 0;
            min-width: 0;
            --site-header-card-inset: clamp(18px, 2.8vw, 36px);
            padding-top: clamp(12px, 1.8vw, 22px);
            /* Стартовий резерв під compact-bar на сторінках лише з компактним режимом */
            --site-header-compact-sticky-height: 92px;
            background: var(--bg);
            color: var(--header-text);
            border-bottom-width: 0;
            border-bottom-style: solid;
            border-bottom-color: transparent;
            box-shadow: none;
            position: relative;
            z-index: 2;
            margin-bottom: var(--shop-header-margin-bottom);
            transition:
                background-color var(--header-motion-duration) var(--header-motion-ease),
                box-shadow var(--header-motion-duration) var(--header-motion-ease),
                color var(--header-motion-duration) var(--header-motion-ease),
                border-bottom-color var(--header-motion-duration) var(--header-motion-ease);
        }
        .site-header:hover {
            box-shadow: none;
        }
        /* Завжди блок (без display:contents → інакше під час морфу категорії «зависають» до кінця анімації). */
        .site-header__sticky {
            display: block;
            min-width: 0;
            position: static;
            top: auto;
            z-index: auto;
            transition:
                background-color var(--header-motion-duration) var(--header-motion-ease),
                box-shadow var(--header-motion-duration) var(--header-motion-ease),
                border-color var(--header-motion-duration) var(--header-motion-ease);
        }
        .site-header.site-header--force-compact {
            padding-top: var(--site-header-compact-sticky-height);
        }
        .site-header__hero .site-header__zone--center {
            gap: 22px;
            padding-top: clamp(18px, 2.8vw, 34px);
        }
        .site-header__hero .site-logo {
            margin-top: 52px;
        }
        .site-header__hero .site-header__search,
        .site-header__hero .site-header__search--center {
            margin-top: clamp(14px, 2.2vw, 28px);
        }
        .site-header__hero .site-nav__link--cart.cart-pill {
            width: clamp(108px, 10vw, 148px);
            min-width: 108px;
            max-width: 100%;
        }
        .site-header__row {
            display: grid;
            grid-template-columns: minmax(0, 1fr) minmax(0, auto) minmax(0, 1fr);
            align-items: start;
            gap: 12px;
            width: 100%;
            max-width: 100%;
        }
        /* Великий блок лише у розгорнутому .site-header__hero */
        .site-header__hero .site-header__row {
            min-height: clamp(268px, 42vh, 400px);
            align-items: start;
        }
        .site-header__bottom {
            display: block;
            position: relative;
            z-index: 1;
            /* visible: випадаючі підменю категорій */
            overflow: visible;
            opacity: 1;
            box-sizing: border-box;
            width: calc(100% - 2 * var(--site-header-card-inset));
            max-width: calc(100% - 2 * var(--site-header-card-inset));
            margin-left: var(--site-header-card-inset);
            margin-right: var(--site-header-card-inset);
            margin-top: clamp(8px, 1.2vw, 14px);
            margin-bottom: 0;
            pointer-events: auto;
            transition: margin-bottom 0.22s cubic-bezier(0.32, 0.94, 0.34, 1);
        }
        .site-header__bottom-inner {
            min-height: 0;
            overflow: visible;
            width: 100%;
            max-width: min(80vw, 100%);
            margin-left: auto;
            margin-right: auto;
            box-sizing: border-box;
        }
        .site-header__zone--left {
            justify-self: start;
            align-self: start;
            min-width: 0;
        }
        .site-header__zone--center {
            justify-self: center;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 10px;
            min-width: 0;
            max-width: 100%;
            transition:
                gap var(--header-resize-duration) var(--header-resize-ease),
                padding-top var(--header-resize-duration) var(--header-resize-ease);
        }
        .site-header__zone--right {
            justify-self: end;
            min-width: 0;
            overflow: visible;
        }
        .site-header__contacts-google {
            display: flex;
            flex-direction: column;
            flex-wrap: nowrap;
            align-items: flex-start;
            gap: 10px;
            max-width: 100%;
        }
        .site-header__contact-card {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            min-width: 0;
            padding: 0;
            border: 0;
            border-radius: 0;
            background: transparent;
            text-decoration: none;
            box-shadow: none;
        }
        .site-header__contact-icon {
            color: #64748b;
            flex-shrink: 0;
            width: 21px;
            height: 21px;
            transition: color var(--header-motion-duration) var(--header-motion-ease);
        }
        .site-header__contact-value {
            font-size: 1.05rem;
            font-weight: 600;
            color: #5f6368;
            line-height: 1.2;
            overflow-wrap: anywhere;
            word-break: break-word;
            transition: color var(--header-motion-duration) var(--header-motion-ease);
        }
        .site-header__contact-card:hover .site-header__contact-icon {
            color: #475569;
        }
        .site-header__contact-card:hover .site-header__contact-value {
            color: #202124;
        }
        .site-logo {
            display: block;
            width: 100%;
            max-width: var(--logo-width);
            margin-left: 0;
            margin-right: 0;
            margin-top: 18px;
            align-self: center;
            text-decoration: none;
            color: inherit;
            line-height: 0;
            background: transparent;
            transition:
                margin-top var(--header-resize-duration) var(--header-resize-ease),
                width var(--header-resize-duration) var(--header-resize-ease),
                max-width var(--header-resize-duration) var(--header-resize-ease);
        }
        .site-header__top-actions {
            display: inline-flex;
            flex-wrap: nowrap;
            align-items: center;
            justify-content: flex-end;
            gap: 10px;
            min-width: 0;
            overflow: visible;
        }
        .site-header__compact-bar .site-header__top-actions {
            display: inline-flex;
            align-self: center;
            justify-self: end;
            margin-top: 0;
            gap: 8px;
        }
        .site-header__account,
        .site-header__lang {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 44px;
            height: 44px;
            min-height: 44px;
            padding: 0;
            border-radius: 20px;
            border: 1px solid var(--border);
            background: #fff;
            color: #2563eb;
            text-decoration: none;
            font: inherit;
            cursor: pointer;
            box-shadow: var(--shop-product-btn-shadow);
            transition:
                background-color var(--header-motion-duration) var(--header-motion-ease),
                border-color var(--header-motion-duration) var(--header-motion-ease),
                color var(--header-motion-duration) var(--header-motion-ease),
                box-shadow var(--header-motion-duration) var(--header-motion-ease);
        }
        .site-header__account:hover,
        .site-header__lang:hover {
            box-shadow: var(--shop-product-btn-shadow-hover);
        }
        .site-header__lang-details {
            position: relative;
            display: inline-flex;
        }
        .site-header__lang-details > summary.site-header__lang {
            list-style: none;
        }
        .site-header__lang-details > summary.site-header__lang::-webkit-details-marker {
            display: none;
        }
        .site-header__lang-menu {
            position: absolute;
            top: calc(100% + 6px);
            right: 0;
            z-index: 400;
            min-width: 12.5rem;
            padding: 6px;
            margin: 0;
            border-radius: 14px;
            border: 1px solid var(--border);
            background: #fff;
            box-shadow: var(--shop-product-btn-shadow-hover);
            box-sizing: border-box;
        }
        .site-header__lang-option {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 9px 11px;
            border-radius: 10px;
            text-decoration: none;
            color: #2563eb;
            font-weight: 600;
            font-size: 0.875rem;
            line-height: 1.25;
        }
        .site-header__lang-option:hover {
            background: rgba(54, 125, 241, 0.08);
        }
        .site-header__lang-option.is-active {
            background: #eaf2ff;
            color: #1d4ed8;
        }
        .site-header__lang-option-code {
            min-width: 1.85rem;
            font-weight: 800;
        }
        .site-header__lang-option-label {
            color: #344054;
            font-weight: 600;
        }
        .site-header__lang-option.is-active .site-header__lang-option-label {
            color: #1d4ed8;
        }
        .site-header__user-chip {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            max-width: min(240px, 38vw);
            padding: 4px 12px 4px 4px;
            min-height: 44px;
            border-radius: 20px;
            border: 1px solid var(--border);
            background: #fff;
            color: var(--text);
            text-decoration: none;
            cursor: pointer;
            box-shadow: var(--shop-product-btn-shadow);
            transition:
                background-color var(--header-motion-duration) var(--header-motion-ease),
                border-color var(--header-motion-duration) var(--header-motion-ease),
                box-shadow var(--header-motion-duration) var(--header-motion-ease);
        }
        .site-header__user-chip:hover {
            border-color: rgba(54, 125, 241, 0.35);
            box-shadow: var(--shop-product-btn-shadow-hover);
        }
        .site-header__user-avatar {
            flex-shrink: 0;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid rgba(254, 180, 0, 0.55);
            box-sizing: border-box;
        }
        .site-header__user-avatar--initials {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(145deg, #ffd54a 0%, var(--color-accent) 100%);
            color: #1a2b4a;
            font-size: 0.75rem;
            font-weight: 800;
            letter-spacing: -0.02em;
            line-height: 1;
        }
        .site-header__user-text {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            justify-content: center;
            min-width: 0;
            line-height: 1.2;
        }
        .site-header__user-greeting {
            font-size: 0.68rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: var(--muted);
        }
        .site-header__user-name {
            font-size: 0.88rem;
            font-weight: 800;
            color: var(--text);
            max-width: 100%;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        @media (max-width: 540px) {
            .site-header__user-chip {
                max-width: min(200px, 56vw);
            }
            .site-header__user-greeting {
                display: none;
            }
        }
        @media (max-width: 380px) {
            .site-header__user-text {
                display: none;
            }
            .site-header__user-chip {
                max-width: none;
                padding: 4px;
            }
        }
        .site-header__search {
            min-width: 0;
            width: 35vw;
            max-width: 35vw;
            position: relative;
            transition:
                margin-top var(--header-resize-duration) var(--header-resize-ease),
                width var(--header-resize-duration) var(--header-resize-ease),
                max-width var(--header-resize-duration) var(--header-resize-ease),
                min-width var(--header-resize-duration) var(--header-resize-ease);
        }
        .site-header__search .searchbar {
            font-size: 14px;
            font-family: var(--font);
            color: #202124;
            display: flex;
            z-index: 3;
            height: 52px;
            background: #fff;
            border: 1px solid #dfe1e5;
            box-shadow: var(--shop-product-btn-shadow);
            border-radius: 40px;
            margin: 0 auto;
            width: 100%;
            max-width: 100%;
            transition:
                height var(--header-resize-duration) var(--header-resize-ease),
                border-color var(--header-motion-duration) var(--header-motion-ease),
                box-shadow var(--header-motion-duration) var(--header-motion-ease),
                border-radius var(--header-resize-duration) var(--header-resize-ease);
        }
        .site-header__search:hover .searchbar,
        .site-header__search:focus-within .searchbar {
            box-shadow: var(--shop-product-btn-shadow-hover);
            border-color: rgba(223, 225, 229, 0);
        }
        .site-header__search .searchbar-wrapper {
            flex: 1;
            min-width: 0;
            display: flex;
            padding: 5px 8px 0 14px;
        }
        .site-header__search .searchbar-left {
            display: flex;
            align-items: center;
            padding-right: 13px;
            margin-top: -5px;
            color: #202124;
        }
        .site-header__search .search-icon-wrapper {
            margin: auto;
        }
        .site-header__search .search-icon {
            margin-top: 3px;
            color: #9aa0a6;
            height: 20px;
            line-height: 20px;
            width: 20px;
        }
        .site-header__search .searchbar-icon {
            display: inline-block;
            fill: currentColor;
            height: 24px;
            line-height: 24px;
            position: relative;
            width: 24px;
        }
        .site-header__search .searchbar-center {
            display: flex;
            flex: 1;
            flex-wrap: wrap;
            min-width: 0;
        }
        .site-header__search .searchbar-input-spacer {
            color: transparent;
            flex: 100%;
            white-space: pre;
            height: 34px;
            font-size: 16px;
            font-family: var(--font);
            font-optical-sizing: auto;
            transition: height var(--header-resize-duration) var(--header-resize-ease);
        }
        .site-header__search .searchbar-input {
            background-color: transparent;
            border: none;
            margin: 0;
            padding: 0;
            color: rgba(0, 0, 0, 0.87);
            word-wrap: break-word;
            outline: none;
            display: flex;
            flex: 100%;
            margin-top: -37px;
            height: 34px;
            font-size: 16px;
            font-family: var(--font);
            font-optical-sizing: auto;
            max-width: 100%;
            width: 100%;
            transition:
                height var(--header-resize-duration) var(--header-resize-ease),
                margin-top var(--header-resize-duration) var(--header-resize-ease);
        }
        .site-header__search .searchbar-input::placeholder {
            color: #70757a;
        }
        .site-header__hero .site-header__search .searchbar {
            height: 56px;
            border-radius: 40px;
        }
        .site-header__hero .site-header__search .searchbar-input-spacer {
            height: 36px;
        }
        .site-header__hero .site-header__search .searchbar-input {
            height: 36px;
            margin-top: -39px;
        }
        .site-header__search .searchbar-right {
            display: flex;
            flex: 0 0 auto;
            margin-top: -5px;
            align-items: stretch;
            flex-direction: row;
        }
        .site-header__search-btn {
            flex: 1 0 auto;
            display: flex;
            cursor: pointer;
            align-items: center;
            border: 0;
            background: transparent;
            outline: none;
            padding: 0 8px;
            width: 2.8em;
            min-width: 2.8em;
            border-radius: 20px;
            font-family: var(--font);
        }
        .site-header__search-btn svg {
            width: 24px;
            height: 24px;
            display: block;
        }
        .site-header__search-btn:hover {
            background: rgba(241, 243, 244, 0.9);
        }
        .site-header__search--center {
            width: 35vw;
            max-width: 35vw;
            margin-bottom: 6px;
            transition: margin-bottom var(--header-resize-duration) var(--header-resize-ease);
        }
        /* Ширина лого; висота за пропорціями файлу (zoogle-logo-new.png 1277×320) — без цього
           до decode картинки висота ≈0 і після завантаження смикається sticky/фільтри */
        .site-logo__img {
            display: block;
            width: 100%;
            height: auto;
            aspect-ratio: 1277 / 320;
            object-fit: contain;
            object-position: center center;
            background: transparent;
            background-color: transparent;
            mix-blend-mode: normal;
            transform-origin: center center;
            transition:
                width var(--header-resize-duration) var(--header-resize-ease),
                max-width var(--header-resize-duration) var(--header-resize-ease);
        }
        .site-header:hover .site-logo__img {
            transform: none;
        }
        @media (prefers-reduced-motion: reduce) {
            .site-logo__img { transition: none; }
            .site-header:hover .site-logo__img { transform: none; }
        }

        /* Окрема fixed-смуга: з’являється після прокрутки великого блоку (без морфу DOM) */
        .site-header__compact-bar {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 50;
            min-width: 0;
            padding-top: 10px;
            padding-bottom: 10px;
            background: #ffffff;
            color: #202124;
            box-shadow: 0 1px 6px rgba(60, 64, 67, 0.12);
            border-bottom: 1px solid #dadce0;
            transform: translateY(-100%);
            opacity: 0;
            visibility: hidden;
            pointer-events: none;
            transition:
                transform 0.22s cubic-bezier(0.32, 0.94, 0.34, 1),
                opacity 0.22s ease,
                visibility 0.22s,
                background-color var(--header-motion-duration) var(--header-motion-ease),
                box-shadow var(--header-motion-duration) var(--header-motion-ease);
        }
        .site-header--compact-visible .site-header__compact-bar,
        .site-header--force-compact .site-header__compact-bar {
            transform: translateY(0);
            opacity: 1;
            visibility: visible;
            pointer-events: auto;
        }
        .site-header--force-compact .site-header__hero {
            display: none;
        }
        /* лого | контакти | пошук | дії — DOM: left, center(logo,search), right → order */
        .site-header__compact-bar .site-header__row {
            min-height: 0;
            grid-template-columns: auto auto minmax(0, 1fr) auto;
            align-items: center;
            gap: clamp(10px, 1.4vw, 16px);
        }
        .site-header__compact-bar .site-header__zone--left {
            display: flex;
            flex-direction: column;
            justify-content: center;
            gap: 0;
            min-width: 0;
            max-width: min(360px, 48vw);
            order: 2;
            justify-self: start;
            align-self: center;
            margin-left: 0;
            opacity: 1;
            transform: none;
            overflow: visible;
        }
        .site-header__compact-bar .site-header__zone--center {
            display: contents;
        }
        .site-header__compact-bar .site-logo {
            margin-top: 0;
            margin-left: 0;
            margin-right: 0;
            align-self: center;
            justify-self: start;
            width: min(168px, 18vw);
            max-width: min(168px, 18vw);
            order: 1;
            transform: none;
        }
        .site-header__compact-bar .site-logo__img {
            width: 100%;
            max-width: 100%;
        }
        .site-header__compact-bar .site-header__search,
        .site-header__compact-bar .site-header__search--center {
            width: min(520px, 100%);
            max-width: min(520px, 42vw);
            min-width: 0;
            justify-self: end;
            align-self: center;
            margin-top: 0;
            margin-bottom: 0;
            order: 3;
        }
        .site-header__compact-bar .site-header__zone--right {
            order: 4;
            justify-self: end;
            align-self: center;
        }
        .site-header__compact-bar .site-header__contacts-google {
            flex-direction: row;
            flex-wrap: wrap;
            align-items: center;
            gap: 4px 8px;
            position: relative;
            z-index: 0;
            isolation: isolate;
            overflow: visible;
        }
        .site-header__compact-bar .site-header__contact-card {
            gap: 5px;
            position: relative;
            z-index: 1;
        }
        .site-header__compact-bar .site-header__contact-icon {
            width: 18px;
            height: 18px;
            color: #64748b;
        }
        .site-header__compact-bar .site-header__contact-value {
            font-size: 1rem;
            font-weight: 600;
            color: #5f6368;
            line-height: 1.2;
        }
        /* Малий хедер: тільки іконки; текст при :hover / :focus-within */
        @media (hover: hover) and (pointer: fine) {
            .site-header__compact-bar .site-header__zone--left {
                overflow: visible;
            }
            .site-header__compact-bar .site-header__contacts-google {
                align-items: center;
            }
            .site-header__compact-bar .site-header__contact-card {
                position: relative;
                z-index: 1;
                max-width: none;
                border-radius: 8px;
                padding: 2px 3px 2px 2px;
                margin: 0;
                flex-shrink: 0;
                transition:
                    background-color 0.18s ease,
                    box-shadow 0.18s ease;
            }
            .site-header__compact-bar .site-header__contact-value {
                display: block;
                max-width: 0;
                min-width: 0;
                overflow: hidden;
                opacity: 0;
                white-space: nowrap;
                transition:
                    max-width 0.32s cubic-bezier(0.32, 0.94, 0.34, 1),
                    opacity 0.2s ease;
            }
            .site-header__compact-bar .site-header__contact-card:hover,
            .site-header__compact-bar .site-header__contact-card:focus-within {
                z-index: 30;
                background: #f8fafc;
                box-shadow: 0 1px 4px rgba(60, 64, 67, 0.18);
            }
            .site-header__compact-bar .site-header__contact-card:hover .site-header__contact-value,
            .site-header__compact-bar .site-header__contact-card:focus-within .site-header__contact-value {
                max-width: min(256px, 50vw);
                opacity: 1;
            }
        }
        @media (prefers-reduced-motion: reduce) {
            .site-header__compact-bar .site-header__contact-value {
                transition: none;
            }
        }
        @media (hover: none), (pointer: coarse) {
            .site-header__compact-bar .site-header__contact-value {
                max-width: none;
                min-width: 0;
                opacity: 1;
            }
        }
        .site-header__compact-bar .site-header__contact-card:hover .site-header__contact-icon {
            color: #475569;
        }
        .site-header__compact-bar .site-header__contact-card:hover .site-header__contact-value {
            color: #202124;
        }
        .site-header__compact-bar .site-header__search .searchbar {
            height: 44px;
            border-radius: 40px;
            background: #fff;
            border-color: #dfe1e5;
            box-shadow: var(--shop-product-btn-shadow);
            align-items: center;
        }
        .site-header__compact-bar .site-header__search .searchbar-wrapper {
            padding: 0 6px 0 12px;
            align-items: center;
            min-height: 0;
        }
        .site-header__compact-bar .site-header__search .searchbar-left,
        .site-header__compact-bar .site-header__search .searchbar-right {
            margin-top: 0;
        }
        .site-header__compact-bar .site-header__search .search-icon {
            margin-top: 0;
        }
        .site-header__compact-bar .site-header__search .searchbar-center {
            align-items: center;
            align-content: center;
        }
        .site-header__compact-bar .site-header__search .searchbar-input-spacer {
            height: 32px;
            font-size: 0.9rem;
        }
        .site-header__compact-bar .site-header__search .searchbar-input {
            height: 32px;
            margin-top: -32px;
            font-size: 0.9rem;
        }
        .site-header__compact-bar .site-header__search-btn {
            width: 2.4em;
            min-width: 2.4em;
            padding: 0 6px;
        }
        .site-header__compact-bar .site-header__account,
        .site-header__compact-bar .site-header__lang {
            width: 44px;
            height: 44px;
            min-height: 44px;
            background: #fff;
            border-color: #dadce0;
            color: #2563eb;
        }
        .site-header__compact-bar .site-header__user-chip {
            max-width: min(210px, 50vw);
            min-height: 44px;
            padding: 4px 10px 4px 3px;
            gap: 8px;
        }
        .site-header__compact-bar .site-header__user-avatar,
        .site-header__compact-bar .site-header__user-avatar--img,
        .site-header__compact-bar .site-header__user-avatar--initials {
            width: 36px;
            height: 36px;
        }
        .site-header__compact-bar .site-header__user-name {
            font-size: 0.82rem;
        }
        .site-header__compact-bar .site-nav__link--cart.cart-pill {
            min-width: 96px;
            min-height: 44px;
            height: 44px;
            border-radius: 20px;
        }
        .site-header__compact-bar .cart-pill__icon {
            width: 19px;
            height: 19px;
        }
        .site-header__compact-bar .cart-pill__label {
            font-size: 0.82rem;
        }
        .site-header__compact-bar .cart-pill__count {
            font-size: 0.87rem;
        }
        .site-header__compact-bar .cart-pill__left {
            gap: 7px;
            padding: 0 8px 0 10px;
        }
        @media (max-width: 900px) {
            .site-header__compact-bar .site-header__zone--left {
                display: none;
            }
            .site-header__compact-bar .site-header__row {
                grid-template-columns: 1fr;
                justify-items: stretch;
            }
            .site-header__compact-bar .site-header__zone--center {
                display: flex;
                flex-direction: column;
                align-items: stretch;
                gap: 10px;
            }
            .site-header__compact-bar .site-logo {
                order: 0;
                max-width: min(200px, 70vw);
            }
            .site-header__compact-bar .site-logo__img {
                width: min(200px, 100%);
                max-width: 100%;
            }
            .site-header__compact-bar .site-header__search,
            .site-header__compact-bar .site-header__search--center {
                max-width: 100%;
                justify-self: stretch;
                order: 0;
            }
            .site-header__compact-bar .site-header__zone--right {
                order: 0;
                justify-self: stretch;
            }
        }
        @media (prefers-reduced-motion: reduce) {
            .site-header,
            .site-header__compact-bar,
            .site-header__row,
            .site-header__zone--center,
            .site-header__contacts-google,
            .site-header__contact-card,
            .site-header__contact-icon,
            .site-header__contact-value,
            .site-logo,
            .site-logo__img,
            .site-header__search,
            .site-header__search--center,
            .site-header__sticky,
            .site-header__bottom,
            .site-header__search .searchbar,
            .site-header__search .searchbar-input,
            .site-header__account,
            .site-header__user-chip,
            .site-header__lang,
            .site-header__gt,
            .site-nav__link--cart {
                transition-duration: 0.01ms !important;
            }
        }

        .visually-hidden {
            position: absolute;
            width: 1px;
            height: 1px;
            padding: 0;
            margin: -1px;
            overflow: hidden;
            clip: rect(0, 0, 0, 0);
            white-space: nowrap;
            border: 0;
        }
        .site-nav {
            display: flex;
            gap: 8px;
            align-items: center;
        }
        .site-nav__link {
            color: #2563eb;
            text-decoration: none;
            padding: 8px 12px;
            border-radius: 20px;
            font-size: 0.95rem;
            font-weight: 500;
            background-color: transparent;
            transition:
                background-color var(--duration-hover) var(--ease-hover),
                color calc(var(--duration-hover) * 0.9) var(--ease-hover);
        }
        .site-nav__link:hover { color: #1d4ed8; background-color: rgba(54, 125, 241, 0.08); }
        .site-nav__link.is-active {
            color: #fff;
            background-color: var(--color-cta);
            font-weight: 600;
        }
        .site-nav__link.is-active:hover {
            color: #fff;
            background-color: var(--color-cta-hover);
        }
        /* Кошик у хедері: білий фон; з товарами — зелений #339B3B */
        .site-nav__link--cart.cart-pill {
            display: inline-flex;
            align-items: stretch;
            flex-direction: row;
            position: relative;
            width: auto;
            min-width: 104px;
            max-width: 100%;
            height: 44px;
            min-height: 44px;
            padding: 0;
            border: 1px solid #e4e7ec;
            border-radius: 20px;
            background-color: #fff;
            color: #707277 !important;
            font-weight: 600;
            font-size: 0.94rem;
            overflow: hidden;
            box-shadow:
                0 10px 18px rgba(15, 23, 42, 0.12),
                0 10px 26px rgba(var(--color-cta-rgb), 0.24);
            transition:
                background-color 0.2s ease,
                color 0.2s ease,
                transform 0.15s ease,
                filter 0.2s ease,
                box-shadow 0.2s ease;
        }
        .site-nav__link--cart.cart-pill:hover:not(.cart-pill--has-items) {
            color: #339b3b !important;
            box-shadow:
                0 10px 18px rgba(15, 23, 42, 0.14),
                0 12px 30px rgba(var(--color-cta-rgb), 0.3);
        }
        .site-nav__link--cart.cart-pill:hover:not(.cart-pill--has-items) .cart-pill__icon {
            fill: #339b3b;
        }
        .site-nav__link--cart.cart-pill:hover:not(.cart-pill--has-items) .cart-pill__label {
            color: #339b3b;
        }
        .site-nav__link--cart.cart-pill:hover:not(.cart-pill--has-items) .cart-pill__count {
            color: #339b3b;
        }
        .site-nav__link--cart.cart-pill:focus-visible {
            outline: 2px solid #f5356e;
            outline-offset: 2px;
        }
        .site-nav__link--cart.cart-pill:active {
            transform: scale(0.97);
        }
        .site-nav__link--cart.cart-pill.cart-pill--has-items {
            background-color: #339b3b;
            border-color: #339b3b;
            color: #fff !important;
            box-shadow:
                0 10px 18px rgba(15, 23, 42, 0.12),
                0 10px 26px rgba(var(--color-buy-rgb), 0.26);
        }
        .site-nav__link--cart.cart-pill.cart-pill--has-items:hover {
            color: #fff !important;
            filter: brightness(1.06);
            box-shadow:
                0 0 0 4px rgba(var(--color-buy-rgb), 0.22),
                0 16px 24px rgba(15, 23, 42, 0.14),
                0 10px 28px rgba(var(--color-buy-rgb), 0.28);
        }
        .cart-pill__left {
            flex: 1 1 70%;
            min-width: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            padding: 0 8px 0 11px;
        }
        .cart-pill__icon {
            flex-shrink: 0;
            width: 20px;
            height: 20px;
            fill: #707277;
            transition: fill 0.2s ease, transform 0.2s ease;
        }
        .cart-pill__label {
            color: inherit;
            font-size: inherit;
            font-weight: 600;
            font-family: inherit;
            white-space: nowrap;
        }
        .cart-pill__count {
            flex: 0 0 auto;
            display: flex;
            align-items: center;
            justify-content: center;
            min-width: 2.1em;
            padding: 0 8px 0 5px;
            color: inherit;
            font-size: 0.94rem;
            transition: color 0.2s ease;
        }
        .cart-pill--has-items .cart-pill__icon {
            fill: #fff;
            animation: cart-pill-icon-pop 0.22s ease-out 1;
        }
        .cart-pill--has-items .cart-pill__count {
            color: #fff;
        }
        .cart-pill__count-num {
            display: inline-block;
            transform-origin: center center;
        }
        .cart-pill__count-num--pulse {
            animation: cartPillBadgePulse 0.42s cubic-bezier(0.22, 1, 0.36, 1);
            will-change: transform, filter;
        }
        @keyframes cartPillBadgePulse {
            0% {
                transform: scale(1);
                filter: brightness(1);
            }
            40% {
                transform: scale(1.14);
                filter: brightness(1.2);
            }
            100% {
                transform: scale(1);
                filter: brightness(1);
            }
        }
        @keyframes cart-pill-icon-pop {
            0% {
                transform: scale(0.65);
            }
            100% {
                transform: scale(1.08);
            }
        }
        @media (prefers-reduced-motion: reduce) {
            .cart-pill--has-items .cart-pill__icon {
                animation: none;
            }
            .cart-pill__count-num--pulse {
                animation: none;
            }
            .site-nav__link--cart.cart-pill:active {
                transform: none;
            }
        }
        .site-nav__link--muted { opacity: 0.85; }
        .site-header__actions { display: flex; align-items: center; gap: 8px; }
        body.cart-drawer-open {
            overflow: hidden;
        }
        .cart-drawer[hidden] {
            display: none;
        }
        .cart-drawer {
            position: fixed;
            inset: 0;
            z-index: 96;
            --cart-drawer-ease: cubic-bezier(0.32, 0.72, 0, 1);
            --cart-drawer-duration: 0.38s;
            --cart-ui-font: var(--font);
            --cart-line-shadow:
                0 2px 10px rgba(15, 23, 42, 0.06),
                0 1px 3px rgba(15, 23, 42, 0.04);
            --cart-line-shadow-hover:
                0 18px 46px rgba(15, 23, 42, 0.14),
                0 6px 16px rgba(15, 23, 42, 0.08);
            --cart-btn-shadow: 0 2px 8px rgba(15, 23, 42, 0.08);
            --cart-btn-shadow-hover: 0 6px 18px rgba(15, 23, 42, 0.14);
        }
        .cart-drawer__scrim {
            position: absolute;
            inset: 0;
            border: 0;
            background: rgba(15, 23, 42, 0.38);
            opacity: 0;
            cursor: pointer;
            transition: opacity var(--cart-drawer-duration) var(--cart-drawer-ease);
        }
        .cart-drawer__panel {
            position: absolute;
            top: 0;
            right: 0;
            height: 100%;
            width: min(560px, 100vw);
            display: flex;
            flex-direction: column;
            box-sizing: border-box;
            background: #fff;
            border-left: 0.5px solid rgba(0, 0, 0, 0.08);
            box-shadow:
                -32px 0 100px rgba(15, 23, 42, 0.14),
                -16px 0 48px rgba(15, 23, 42, 0.08),
                -4px 0 12px rgba(15, 23, 42, 0.04);
            transform: translateX(100%);
            transition: transform var(--cart-drawer-duration) var(--cart-drawer-ease);
            font-family: var(--cart-ui-font);
            font-feature-settings: 'kern' 1, 'liga' 1;
            font-variant-numeric: tabular-nums;
            font-optical-sizing: auto;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
            text-rendering: optimizeLegibility;
        }
        @media (prefers-reduced-motion: reduce) {
            .cart-drawer__scrim,
            .cart-drawer__panel {
                transition-duration: 0.01ms;
            }
            .cart-drawer__line:hover {
                transform: none;
            }
            .cart-drawer__line:hover .cart-drawer__line-media img {
                transform: none;
            }
            .cart-drawer__empty-link:hover,
            .cart-drawer__checkout-btn:hover {
                transform: none;
            }
            .cart-drawer__qty-btn:hover {
                transform: none;
            }
        }
        .cart-drawer.is-open .cart-drawer__scrim {
            opacity: 1;
        }
        .cart-drawer.is-open .cart-drawer__panel {
            transform: translateX(0);
        }
        .cart-drawer__panel-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 16px;
            padding: 24px 26px 18px;
            border-bottom: 0.5px solid rgba(0, 0, 0, 0.08);
            flex-shrink: 0;
        }
        .cart-drawer__panel-title {
            margin: 0;
            color: #141821;
            font-size: clamp(1.22rem, 2.8vw, 1.48rem);
            font-weight: 800;
            letter-spacing: -0.035em;
            line-height: 1.18;
        }
        .cart-drawer__panel-meta {
            margin: 8px 0 0;
            color: rgba(52, 64, 84, 0.82);
            font-size: 0.9375rem;
            font-weight: 600;
            letter-spacing: -0.014em;
            line-height: 1.4;
        }
        .cart-drawer__panel-meta [data-cart-panel-count] {
            font-weight: 800;
            font-variant-numeric: tabular-nums;
            color: var(--color-price);
            letter-spacing: -0.02em;
        }
        .cart-drawer__close-btn {
            width: 40px;
            height: 40px;
            border-radius: 999px;
            border: none;
            background: rgba(120, 120, 128, 0.12);
            color: #1d1d1f;
            font-size: 26px;
            line-height: 1;
            cursor: pointer;
            box-shadow: 0 1px 2px rgba(15, 23, 42, 0.06);
            transition:
                background-color 0.2s var(--cart-drawer-ease),
                box-shadow 0.22s var(--cart-drawer-ease),
                transform 0.15s var(--cart-drawer-ease);
        }
        .cart-drawer__close-btn:hover {
            background: rgba(120, 120, 128, 0.18);
            box-shadow: var(--cart-btn-shadow-hover);
        }
        .cart-drawer__close-btn:active {
            transform: scale(0.96);
        }
        .cart-drawer__content {
            flex: 1 1 auto;
            overflow-y: auto;
            padding: 20px 26px max(24px, env(safe-area-inset-bottom, 0px));
            scrollbar-width: thin;
            scrollbar-color: rgba(0, 0, 0, 0.22) transparent;
        }
        .cart-drawer__content::-webkit-scrollbar {
            width: 6px;
        }
        .cart-drawer__content::-webkit-scrollbar-thumb {
            background: rgba(0, 0, 0, 0.18);
            border-radius: 6px;
        }
        /* Як `.pdp-defer-modal__panel`: та сама «картка» зверху екрана */
        .cart-toast-stack {
            position: fixed;
            left: 50%;
            top: max(12px, env(safe-area-inset-top, 0px));
            z-index: 260;
            display: flex;
            flex-direction: column;
            gap: 10px;
            width: min(calc(100vw - 32px), 540px);
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
            --apple-body: rgba(29, 29, 31, 0.92);
        }
        .cart-toast {
            position: relative;
            box-sizing: border-box;
            overflow: hidden;
            max-width: 100%;
            width: 100%;
            margin: 0;
            padding: 28px 26px 20px;
            border-radius: 22px;
            border: 0.5px solid rgba(0, 0, 0, 0.08);
            background: #fff;
            box-shadow:
                0 28px 80px rgba(0, 0, 0, 0.12),
                0 12px 32px rgba(0, 0, 0, 0.08);
            font-size: 1.0625rem;
            font-weight: 600;
            line-height: 1.58;
            letter-spacing: -0.008em;
            color: var(--apple-body);
            opacity: 0;
            transform: translateY(-14px) scale(0.97);
            transition:
                opacity 0.32s cubic-bezier(0.32, 0.72, 0, 1),
                transform 0.38s cubic-bezier(0.32, 0.72, 0, 1);
            pointer-events: auto;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
            text-rendering: optimizeLegibility;
        }
        .cart-toast::before {
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
        .cart-toast.is-visible {
            opacity: 1;
            transform: translateY(0) scale(1);
        }
        @media (max-width: 768px) {
            .cart-toast-stack {
                width: min(calc(100vw - 24px), 540px);
            }
            .cart-toast {
                border-radius: 20px;
                padding: 26px 18px 18px;
            }
            .cart-toast::before {
                top: 9px;
            }
        }
        @media (prefers-reduced-motion: reduce) {
            .cart-toast {
                transition-duration: 0.01ms;
            }
        }
        .cart-drawer__empty {
            padding: 22px 10px 14px;
            text-align: center;
        }
        .cart-drawer__empty-title {
            margin: 0 0 10px;
            color: #141821;
            font-size: clamp(1.08rem, 2.6vw, 1.22rem);
            font-weight: 800;
            letter-spacing: -0.03em;
            line-height: 1.25;
        }
        .cart-drawer__empty-text {
            max-width: 26em;
            margin: 0 auto 18px;
            color: var(--muted);
            font-size: 0.9375rem;
            font-weight: 500;
            letter-spacing: -0.012em;
            line-height: 1.58;
        }
        .cart-drawer__empty-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 11px 16px;
            border-radius: 12px;
            background: #1a73e8;
            color: #fff !important;
            text-decoration: none;
            font-size: 0.9375rem;
            font-weight: 700;
            letter-spacing: -0.015em;
            box-shadow: 0 4px 14px rgba(26, 115, 232, 0.35);
            transition:
                background-color 0.2s var(--cart-drawer-ease),
                box-shadow 0.22s var(--cart-drawer-ease),
                transform 0.18s var(--cart-drawer-ease);
        }
        .cart-drawer__empty-link:hover {
            background: #1557b0;
            box-shadow: 0 8px 22px rgba(26, 115, 232, 0.42);
            transform: translateY(-2px);
        }
        .cart-drawer__empty-link:active {
            transform: translateY(0);
        }
        .cart-drawer__lines {
            display: flex;
            flex-direction: column;
            gap: 14px;
        }
        .cart-drawer__line {
            position: relative;
            display: grid;
            grid-template-columns: 92px minmax(0, 1fr);
            gap: 14px;
            padding: 14px;
            border: 0.5px solid rgba(0, 0, 0, 0.08);
            border-radius: 18px;
            background: #fff;
            box-shadow: var(--cart-line-shadow);
            transform: translateY(0);
            transition:
                border-color 0.22s var(--cart-drawer-ease),
                box-shadow 0.28s var(--cart-drawer-ease),
                transform 0.28s var(--cart-drawer-ease);
        }
        .cart-drawer__line:hover {
            border-color: rgba(15, 23, 42, 0.12);
            box-shadow: var(--cart-line-shadow-hover);
            transform: translateY(-3px);
        }
        .cart-drawer__line-hit {
            position: absolute;
            inset: 0;
            z-index: 1;
            border-radius: inherit;
        }
        .cart-drawer__remove-form,
        .cart-drawer__line-actions {
            position: relative;
            z-index: 2;
        }
        .cart-drawer__line-header > a.cart-drawer__line-title {
            position: relative;
            z-index: 3;
        }
        .cart-drawer__line-media {
            width: 92px;
            height: 92px;
            border-radius: 14px;
            overflow: hidden;
            background: #f2f4f7;
            border: 0.5px solid rgba(0, 0, 0, 0.06);
            box-shadow: inset 0 1px 2px rgba(255, 255, 255, 0.65);
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .cart-drawer__line-media img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            object-position: center;
            transform: scale(1);
            transition: transform 0.38s var(--cart-drawer-ease);
        }
        .cart-drawer__line:hover .cart-drawer__line-media img {
            transform: scale(1.06);
        }
        .cart-drawer__line-media-empty {
            padding: 10px;
            font-size: 11px;
            font-weight: 600;
            letter-spacing: -0.01em;
            text-align: center;
            color: var(--muted);
            line-height: 1.35;
        }
        .cart-drawer__line-main {
            min-width: 0;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        .cart-drawer__line-main--checkout {
            flex-direction: row;
            align-items: flex-start;
            gap: 12px;
        }
        .cart-drawer__line-body {
            flex: 1 1 auto;
            min-width: 0;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        .cart-drawer__line-aside {
            flex: 0 0 auto;
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 8px;
        }
        .cart-drawer__line-pricing--checkout-aside {
            margin: 0;
            justify-content: flex-end;
            flex-direction: column;
            align-items: flex-end;
            gap: 2px;
        }
        .cart-drawer__line-actions--checkout-aside {
            margin: 0;
        }
        .cart-drawer__line-header {
            display: flex;
            align-items: flex-start;
            gap: 8px;
        }
        .cart-drawer__line-title {
            flex: 1 1 auto;
            min-width: 0;
            color: #141821;
            font-size: clamp(0.94rem, 2.4vw, 1.03rem);
            font-weight: 700;
            letter-spacing: -0.022em;
            line-height: 1.38;
            text-decoration: none;
        }
        .cart-drawer__line-title:visited {
            color: #141821;
        }
        .cart-drawer__bundle-meta {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 8px;
        }
        .cart-drawer__bundle-badge {
            display: inline-flex;
            align-items: center;
            padding: 4px 10px;
            border-radius: 999px;
            background: #eef4ff;
            color: #1f3f75;
            font-size: 11px;
            font-weight: 800;
            letter-spacing: 0.02em;
            text-transform: uppercase;
            line-height: 1.35;
        }
        .cart-drawer__bundle-meta-text {
            color: var(--muted);
            font-size: 12px;
            font-weight: 500;
            letter-spacing: -0.01em;
            line-height: 1.35;
        }
        .cart-drawer__bundle-list {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }
        .cart-drawer__bundle-item {
            display: inline-flex;
            align-items: center;
            max-width: 100%;
            padding: 6px 10px;
            border-radius: 999px;
            background: #f8fafc;
            color: #344054;
            font-size: 12px;
            font-weight: 600;
            letter-spacing: -0.015em;
            line-height: 1.35;
            border: 1px solid var(--border);
        }
        .cart-drawer__remove-form {
            flex: 0 0 auto;
            margin: 0;
        }
        .cart-drawer__remove-btn {
            width: 32px;
            height: 32px;
            border: 1px solid var(--border);
            border-radius: 999px;
            background: #fff;
            color: #667085;
            font-size: 20px;
            line-height: 1;
            cursor: pointer;
            box-shadow: var(--cart-btn-shadow);
            transition:
                background-color 0.2s var(--cart-drawer-ease),
                border-color 0.2s var(--cart-drawer-ease),
                color 0.2s var(--cart-drawer-ease),
                box-shadow 0.22s var(--cart-drawer-ease),
                transform 0.18s var(--cart-drawer-ease);
        }
        .cart-drawer__remove-btn:hover {
            background: #fef2f2;
            border-color: rgba(239, 68, 68, 0.35);
            color: #b42318;
            box-shadow: var(--cart-btn-shadow-hover);
            transform: scale(1.06);
        }
        .cart-drawer__remove-btn:active {
            transform: scale(0.98);
        }
        @media (max-width: 768px) {
            .cart-drawer__remove-btn {
                width: 44px;
                height: 44px;
                min-width: 44px;
                min-height: 44px;
                font-size: 22px;
            }
            .cart-drawer__qty-btn {
                width: 44px;
                height: 44px;
                min-width: 44px;
                min-height: 44px;
            }
            .cart-drawer__qty-input {
                height: 44px;
            }
        }
        .cart-drawer__options {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }
        .cart-drawer__option-badge {
            display: inline-flex;
            align-items: center;
            max-width: 100%;
            padding: 6px 10px;
            border-radius: 999px;
            background: #f2f4f7;
            color: #344054;
            font-size: 12px;
            font-weight: 600;
            letter-spacing: -0.012em;
            line-height: 1.35;
        }
        .cart-drawer__option-swatch {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 8px 4px 4px;
            border-radius: 999px;
            background: #f8fafc;
            border: 1px solid var(--border);
            color: #344054;
            font-size: 12px;
            font-weight: 600;
            letter-spacing: -0.012em;
        }
        .cart-drawer__option-swatch-dot {
            width: 24px;
            height: 24px;
            border-radius: 999px;
            overflow: hidden;
            background: #d0d5dd;
            border: 1px solid rgba(16, 24, 40, 0.08);
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        .cart-drawer__option-swatch-dot img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .cart-drawer__line-pricing {
            display: flex;
            align-items: baseline;
            justify-content: space-between;
            gap: 8px;
            flex-wrap: wrap;
        }
        .cart-drawer__unit-price {
            color: var(--color-price);
            font-size: clamp(0.84rem, 2vw, 0.92rem);
            font-weight: 600;
            letter-spacing: -0.018em;
            font-variant-numeric: tabular-nums;
        }
        .cart-drawer__unit-price--old {
            text-decoration: line-through;
            color: #98a2b3;
            font-weight: 500;
            letter-spacing: -0.01em;
        }
        .cart-drawer__line-total {
            color: var(--color-price);
            font-size: clamp(0.98rem, 2.4vw, 1.06rem);
            font-weight: 800;
            letter-spacing: -0.028em;
            font-variant-numeric: tabular-nums;
        }
        .cart-drawer__line-actions {
            display: flex;
            justify-content: flex-end;
        }
        .cart-drawer__qty-form {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            margin: 0;
        }
        .cart-drawer__qty-btn,
        .cart-drawer__qty-input {
            height: 36px;
            border-radius: 10px;
            border: 1px solid var(--border);
            background: #fff;
            font: inherit;
        }
        .cart-drawer__qty-btn {
            width: 36px;
            color: #344054;
            font-size: 20px;
            cursor: pointer;
            box-shadow: var(--cart-btn-shadow);
            transition:
                background-color 0.2s var(--cart-drawer-ease),
                border-color 0.2s var(--cart-drawer-ease),
                color 0.2s var(--cart-drawer-ease),
                box-shadow 0.22s var(--cart-drawer-ease),
                transform 0.18s var(--cart-drawer-ease);
        }
        .cart-drawer__qty-btn:hover {
            background: #f8fafc;
            border-color: rgba(15, 23, 42, 0.18);
            box-shadow: var(--cart-btn-shadow-hover);
            color: #1d2939;
            transform: translateY(-1px);
        }
        .cart-drawer__qty-btn:active {
            transform: translateY(0);
            box-shadow: var(--cart-btn-shadow);
        }
        .cart-drawer__qty-input {
            width: 64px;
            padding: 0 10px;
            text-align: center;
            color: #141821;
            font-weight: 700;
            font-variant-numeric: tabular-nums;
            letter-spacing: -0.02em;
            -moz-appearance: textfield;
            appearance: textfield;
        }
        .cart-drawer__qty-input::-webkit-outer-spin-button,
        .cart-drawer__qty-input::-webkit-inner-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }
        .cart-drawer__footer {
            margin-top: 18px;
            padding-top: 18px;
            border-top: 0.5px solid rgba(0, 0, 0, 0.08);
            display: flex;
            flex-direction: column;
            gap: 14px;
        }
        .cart-drawer__summary {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 10px;
        }
        .cart-drawer__summary > div {
            padding: 12px 12px;
            border-radius: 14px;
            background: linear-gradient(180deg, #fafbfc 0%, #f4f6f9 100%);
            border: 0.5px solid rgba(15, 23, 42, 0.07);
            box-shadow:
                0 2px 8px rgba(15, 23, 42, 0.05),
                inset 0 1px 0 rgba(255, 255, 255, 0.9);
        }
        .cart-drawer__summary-label {
            display: block;
            margin-bottom: 6px;
            color: rgba(102, 112, 133, 0.95);
            font-size: 0.68rem;
            font-weight: 700;
            letter-spacing: 0.09em;
            text-transform: uppercase;
            line-height: 1.2;
        }
        .cart-drawer__summary-value {
            color: #141821;
            font-size: clamp(0.95rem, 2.2vw, 1.02rem);
            font-weight: 700;
            letter-spacing: -0.024em;
            font-variant-numeric: tabular-nums;
        }
        .cart-drawer__summary-value[data-cart-summary-total] {
            color: var(--color-price);
            font-weight: 800;
            font-size: clamp(1.02rem, 2.6vw, 1.12rem);
            letter-spacing: -0.03em;
        }
        .cart-drawer__summary-value--pulse,
        .cart-drawer__line-total--pulse {
            animation: cartDrawerNumberPulse 0.42s var(--cart-drawer-ease);
            transform-origin: right center;
            will-change: transform, color, filter;
        }
        .cart-drawer__qty-input--pulse {
            animation: cartDrawerQtyPulse 0.42s var(--cart-drawer-ease);
            transform-origin: center center;
            will-change: transform;
        }
        @keyframes cartDrawerNumberPulse {
            0% {
                transform: translateY(0) scale(1);
                color: var(--color-price);
                filter: brightness(1);
            }
            40% {
                transform: translateY(-1px) scale(1.04);
                color: #2aad48;
                filter: brightness(1.06);
            }
            100% {
                transform: translateY(0) scale(1);
                color: var(--color-price);
                filter: brightness(1);
            }
        }
        @keyframes cartDrawerQtyPulse {
            0% {
                transform: translateY(0) scale(1);
            }
            40% {
                transform: translateY(-1px) scale(1.04);
            }
            100% {
                transform: translateY(0) scale(1);
            }
        }
        .cart-drawer__checkout-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            min-height: 50px;
            border-radius: 14px;
            background: var(--color-cta);
            color: #fff !important;
            text-decoration: none;
            font-size: clamp(1rem, 2.4vw, 1.09rem);
            font-weight: 800;
            letter-spacing: -0.024em;
            box-shadow:
                0 4px 16px rgba(239, 56, 41, 0.28),
                0 2px 6px rgba(239, 56, 41, 0.18);
            transition:
                filter 0.2s var(--cart-drawer-ease),
                box-shadow 0.24s var(--cart-drawer-ease),
                transform 0.18s var(--cart-drawer-ease);
        }
        .cart-drawer__checkout-btn:hover {
            background: var(--color-cta-hover);
            filter: brightness(1.03);
            box-shadow:
                0 14px 36px rgba(239, 56, 41, 0.38),
                0 6px 14px rgba(239, 56, 41, 0.22);
            transform: translateY(-2px);
        }
        .cart-drawer__checkout-btn:active {
            transform: scale(0.98);
        }
        @media (max-width: 480px) {
            .cart-drawer__panel {
                width: 100vw;
            }
            .cart-drawer__panel-header {
                padding-left: 18px;
                padding-right: 18px;
            }
            .cart-drawer__content {
                padding-left: 18px;
                padding-right: 18px;
            }
            .cart-drawer__summary {
                grid-template-columns: 1fr;
            }
            .cart-drawer__line {
                grid-template-columns: 78px minmax(0, 1fr);
            }
            .cart-drawer__line-media {
                width: 78px;
                height: 78px;
            }
        }
        @media (max-width: 900px) {
            /* Великий хедер: 1-й ряд — контакти + дії зверху; 2-й — лого та пошук (без трьох окремих «поверхових» рядів) */
            .site-header__hero .site-header__row {
                display: grid;
                grid-template-columns: minmax(0, 1fr) auto;
                grid-template-rows: auto auto;
                align-items: start;
                justify-items: stretch;
                gap: 10px 12px;
            }
            .site-header__hero .site-header__zone--left {
                grid-column: 1;
                grid-row: 1;
                justify-self: start;
                align-self: start;
            }
            .site-header__hero .site-header__zone--right {
                grid-column: 2;
                grid-row: 1;
                justify-self: end;
                align-self: start;
            }
            .site-header__hero .site-header__zone--center {
                grid-column: 1 / -1;
                grid-row: 2;
                width: 100%;
                max-width: 560px;
                justify-self: center;
            }
            .site-header__hero .site-header__sticky .site-header__row {
                min-height: 0;
                align-items: start;
            }
            .site-header__contacts-google {
                width: 100%;
                max-width: 360px;
                align-items: flex-start;
            }
            .site-header__contact-card {
                min-width: 0;
            }
            .site-header__zone--center {
                width: 100%;
                max-width: 560px;
            }
            .site-header__search,
            .site-header__search--center {
                width: 100%;
                max-width: min(700px, 100%);
            }
            .site-header__hero .site-logo {
                margin-top: 40px;
            }
            .site-header__hero .site-header__zone--center {
                gap: 18px;
                padding-top: clamp(12px, 2.2vw, 22px);
            }
        }
        @media (max-width: 600px) {
            .site-header {
                --site-header-card-inset: 14px;
            }
            .site-header__contact-value {
                font-size: 0.82rem;
            }
            .site-header__hero .site-header__search .searchbar {
                height: 48px;
            }
            .site-header__hero .site-header__search .searchbar-wrapper {
                padding: 3px 6px 0 12px;
            }
            .site-header__hero .site-header__search .searchbar-input-spacer {
                height: 32px;
            }
            .site-header__hero .site-header__search .searchbar-input {
                height: 32px;
                margin-top: -35px;
            }
            .site-header__hero .site-nav__link--cart.cart-pill {
                height: 44px;
                min-height: 44px;
                border-radius: 20px;
                min-width: 96px;
                width: clamp(96px, 38vw, 142px);
            }
        }

        .btn--header {
            background-color: var(--color-cta);
            color: #fff !important;
            padding: 10px 18px;
            border-radius: 10px;
            font-weight: 600;
            text-decoration: none;
            border: none;
            cursor: pointer;
            font-size: 0.95rem;
            box-shadow: var(--shop-product-btn-shadow);
            transition:
                background-color var(--duration-hover) var(--ease-hover),
                box-shadow var(--duration-hover) var(--ease-hover);
        }
        .btn--header:hover {
            background-color: var(--color-cta-hover);
            box-shadow: var(--shop-product-btn-shadow-hover-cta);
        }

        /* Main shell */
        .site-shell {
            flex: 1;
            min-width: 0;
            min-height: 0;
            position: relative;
            z-index: 1;
            padding: var(--shop-shell-pad-top) 0 48px;
        }
        @media (max-width: 640px) {
            :root {
                --shop-shell-pad-top: 12px;
            }
            .site-shell {
                padding: var(--shop-shell-pad-top) 0 36px;
            }
        }
        .site-main { min-width: 0; overflow: visible; }
        .container.site-main {
            padding-left: 0;
            padding-right: 0;
        }
        .catalog-main {
            min-width: 0;
            min-height: 0;
            overflow: visible;
            align-self: start;
        }

        /* Alerts */
        .alert { padding: 12px 16px; border-radius: var(--radius); margin-bottom: 16px; font-size: 0.95rem; }
        .alert.success { background: #ecfdf3; color: #067647; border: 1px solid #abefc6; }
        .alert.error { background: #fef3f2; color: #b42318; border: 1px solid #fecdca; }

        /* Cards & grid (shop) */
        .card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 18px 20px;
            margin-bottom: 16px;
            box-shadow: var(--shadow);
        }

        /* Сторінки /info/{slug} — центрована панель, типографіка */
        .info-page {
            width: 100%;
            max-width: 52rem;
            margin: 0 auto;
            padding: clamp(16px, 3.5vw, 36px) 0 clamp(28px, 5vw, 52px);
        }
        .info-page__panel {
            margin: 0 auto;
            max-width: 40rem;
            padding: clamp(26px, 4.5vw, 44px) clamp(20px, 4vw, 36px);
            border-radius: var(--shop-radius-catalog);
            border: 1px solid rgba(255, 255, 255, 0.88);
            background: linear-gradient(180deg, rgba(255, 255, 255, 0.98) 0%, rgba(248, 250, 252, 0.99) 100%);
            box-shadow:
                0 12px 28px rgba(15, 23, 42, 0.08),
                0 4px 12px rgba(15, 23, 42, 0.04);
        }
        .info-page__title {
            margin: 0;
            padding: 0;
            font-size: clamp(1.35rem, 2.6vw, 1.85rem);
            font-weight: 800;
            letter-spacing: -0.025em;
            line-height: 1.2;
            text-align: center;
            color: #0f172a;
        }
        .info-page__body {
            margin: 22px auto 0;
            max-width: 34rem;
            font-size: clamp(0.9375rem, 1.6vw, 1.0625rem);
            font-weight: 500;
            line-height: 1.68;
            color: #475467;
            text-align: center;
            text-wrap: pretty;
        }
        .info-page__body a {
            color: var(--text, #367df1);
            font-weight: 600;
            text-decoration: underline;
            text-decoration-color: rgba(54, 125, 241, 0.35);
            text-underline-offset: 0.15em;
        }
        .info-page__body a:hover {
            text-decoration-color: rgba(54, 125, 241, 0.75);
        }
        @media (max-width: 480px) {
            .info-page__panel {
                padding: 22px 18px 26px;
            }
            .info-page__body {
                margin-top: 18px;
            }
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(min(var(--product-card-col-min), 100%), 1fr));
            gap: 16px;
            align-items: stretch;
        }
        @media (max-width: 640px) {
            .grid {
                gap: 12px;
            }
        }
        .btn {
            display: inline-block;
            border: 0;
            background-color: var(--color-cta);
            color: #fff;
            padding: 10px 16px;
            border-radius: 10px;
            cursor: pointer;
            text-decoration: none;
            font-size: 0.95rem;
            font-weight: 600;
            transition:
                background-color var(--duration-hover) var(--ease-hover),
                color var(--duration-hover) var(--ease-hover),
                filter var(--duration-hover) var(--ease-hover),
                opacity var(--duration-hover) var(--ease-hover),
                transform 0.22s var(--ease-hover),
                box-shadow var(--duration-hover) var(--ease-hover);
        }
        .btn:not(.btn-buy):not(.secondary):not(.danger):hover { background-color: var(--color-cta-hover); }
        .btn-buy {
            background-color: var(--color-buy);
            color: #fff;
        }
        .btn-buy:hover { background-color: var(--color-buy-hover); }
        .btn.secondary { background-color: #475467; color: #fff; }
        .btn.secondary:hover { background-color: #3d4a5c; }
        .btn.danger { background-color: #b42318; color: #fff; }
        .btn.danger:hover { background-color: #991b1b; }
        input, select, textarea {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid var(--border);
            border-radius: 10px;
            font: inherit;
        }
        label { font-size: 0.875rem; font-weight: 600; display: block; margin-bottom: 6px; color: #1d4ed8; }
        .muted { color: var(--muted); font-size: 0.9rem; line-height: 1.45; }
        .status { display: inline-block; font-size: 12px; padding: 4px 10px; border-radius: 999px; font-weight: 600; }
        .status.available { background: #dcfae6; color: #067647; }
        .status.preorder { background: #fffaeb; color: #b54708; }
        .status.low-stock { background: #fef3c7; color: #92400e; }
        .status.sold { background: #fee4e2; color: #b42318; }
        .row { display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; }
        .row-2 { display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px; }
        @media (max-width: 800px) {
            .row, .row-2 { grid-template-columns: 1fr; }
        }

        /* Оформлення замовлення — чистий «гуглівський» ритм: відступи, сірі лейбли, плоскі картки */
        .checkout-page {
            max-width: 1024px;
            margin: 0 auto;
            padding-bottom: clamp(24px, 5vw, 48px);
        }
        .checkout-page__toolbar {
            padding-top: 8px;
            margin-bottom: 28px;
        }
        .checkout-page__title {
            margin: 0 0 10px;
            font-size: 1.5rem;
            font-weight: 400;
            letter-spacing: -0.03em;
            color: #202124;
            line-height: 1.25;
        }
        .checkout-page__lead {
            margin: 0;
            font-size: 0.875rem;
            font-weight: 400;
            color: #5f6368;
            line-height: 1.5;
            max-width: 40rem;
        }
        .checkout-page .card {
            border-radius: 12px;
            border: 1px solid #dadce0;
            box-shadow:
                0 1px 2px rgba(60, 64, 67, 0.28),
                0 1px 3px 1px rgba(60, 64, 67, 0.1);
            background: #fff;
            padding: 22px 24px;
        }
        .checkout-page > section.card {
            margin-bottom: 16px;
        }
        .checkout-page__section-heading {
            margin: 0 0 18px;
            padding-bottom: 2px;
            font-size: 1.125rem;
            font-weight: 500;
            letter-spacing: -0.01em;
            color: #202124;
            line-height: 1.3;
        }
        .checkout-page label {
            display: block;
            margin-bottom: 8px;
            font-size: 0.8125rem;
            font-weight: 500;
            letter-spacing: 0.01em;
            color: #5f6368;
            line-height: 1.4;
        }
        .checkout-page input,
        .checkout-page select,
        .checkout-page textarea {
            width: 100%;
            padding: 13px 16px;
            border-radius: 8px;
            border: 1px solid #dadce0;
            background: #fff;
            color: #202124;
            font-weight: 400;
            line-height: 1.5;
            transition:
                border-color 0.15s ease,
                box-shadow 0.15s ease;
        }
        .checkout-page input::placeholder,
        .checkout-page textarea::placeholder {
            color: #80868b;
            font-weight: 400;
        }
        .checkout-page input:hover,
        .checkout-page select:hover,
        .checkout-page textarea:hover {
            border-color: #bdc1c6;
        }
        .checkout-page input:focus,
        .checkout-page select:focus,
        .checkout-page textarea:focus {
            outline: none;
            border-color: #1a73e8;
            box-shadow: 0 0 0 1px #1a73e8;
        }
        .checkout-page select {
            min-height: 48px;
            cursor: pointer;
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='20' height='20' viewBox='0 0 24 24' fill='%235f6368'%3E%3Cpath d='M7 10l5 5 5-5z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 12px center;
            padding-right: 40px;
        }
        .checkout-page textarea {
            min-height: 88px;
            resize: vertical;
            vertical-align: top;
        }
        .checkout-page #comment {
            min-height: 120px;
        }
        .checkout-page .row-2 {
            gap: 16px 20px;
        }
        .checkout-page .muted {
            color: #5f6368;
            line-height: 1.5;
        }
        .checkout-page .cart-drawer__line--checkout .cart-drawer__line-total {
            color: var(--color-price);
            font-variant-numeric: tabular-nums;
        }
        .cart-drawer__checkout-qty {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 64px;
            height: 36px;
            padding: 0 10px;
            border-radius: 10px;
            border: 1px solid var(--border);
            background: #fff;
            color: #202124;
            font-weight: 600;
            font-size: 0.9rem;
            font-variant-numeric: tabular-nums;
        }
        .checkout-page .cart-drawer__lines {
            gap: 16px;
        }
        .checkout-page__total {
            margin: 22px 0 0;
            padding-top: 18px;
            border-top: 1px solid #e8eaed;
            font-size: 1.125rem;
            font-weight: 500;
            color: var(--color-price);
            font-variant-numeric: tabular-nums;
            letter-spacing: -0.02em;
        }
        .checkout-page__form {
            display: flex;
            flex-direction: column;
            gap: 0;
        }
        .checkout-page__form--blocks {
            gap: 16px;
        }
        .checkout-page__form--blocks > .card {
            display: flex;
            flex-direction: column;
            gap: 20px;
            margin-bottom: 0;
        }
        .checkout-page .checkout-delivery-section {
            width: 100%;
        }
        .checkout-page .checkout-delivery-section > .checkout-page__section-heading {
            margin: 0 0 12px;
        }
        .checkout-page .checkout-delivery-row {
            display: flex;
            flex-wrap: nowrap;
            align-items: stretch;
            gap: 16px;
        }
        .checkout-page .checkout-delivery-row > .checkout-delivery__card {
            flex: 1 1 0;
            min-width: 0;
            min-height: 0;
        }
        .checkout-page .checkout-delivery__card--map .checkout-delivery__maps-inner {
            flex: 1 1 auto;
            min-height: 0;
        }
        .checkout-page__submit {
            margin-top: 4px;
            padding-top: 12px;
        }
        .checkout-page__submit .btn-buy {
            display: inline-flex;
            width: 100%;
            min-height: 48px;
            padding: 12px 22px;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 500;
            justify-content: center;
            box-shadow: var(--shop-product-btn-shadow);
        }
        .checkout-page__submit .btn-buy:hover {
            box-shadow: var(--shop-product-btn-shadow-hover-green);
        }
        .checkout-page .alert.error {
            margin: 8px 0 0;
            font-size: 0.875rem;
            border-radius: 8px;
        }
        .checkout-page .btn.secondary {
            border-radius: 8px;
            font-weight: 500;
            padding: 10px 18px;
            box-shadow: none;
        }
        .checkout-page .np-delivery {
            display: flex;
            flex-direction: column;
            gap: 16px;
            padding: 18px 0 6px;
            border-top: 1px solid #e8eaed;
            margin-top: 8px;
        }
        .checkout-page .pickup-delivery .np-field {
            margin: 0;
        }
        .np-delivery {
            display: flex;
            flex-direction: column;
            gap: 12px;
            padding: 12px 0 4px;
            border-top: 1px solid rgba(15, 23, 42, 0.08);
            margin-top: 4px;
        }
        .np-autocomplete {
            position: relative;
        }
        .np-suggest {
            position: absolute;
            left: 0;
            right: 0;
            top: calc(100% + 4px);
            z-index: 40;
            margin: 0;
            padding: 4px 0;
            list-style: none;
            max-height: 220px;
            overflow: auto;
            background: #fff;
            border: 1px solid rgba(15, 23, 42, 0.12);
            border-radius: 10px;
            box-shadow: 0 10px 30px rgba(15, 23, 42, 0.12);
        }
        .np-suggest li {
            padding: 8px 12px;
            cursor: pointer;
            font-size: 0.92rem;
        }
        .np-suggest li:hover {
            background: rgba(51, 154, 57, 0.08);
        }
        .checkout-page__actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: center;
            margin-top: 8px;
        }
        .checkout-page--success .checkout-page__status {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin: 0 0 14px;
            padding: 8px 14px;
            border-radius: 999px;
            font-size: 0.8rem;
            font-weight: 700;
            letter-spacing: 0.03em;
            text-transform: uppercase;
            background: rgba(51, 154, 57, 0.12);
            color: #1d5c22;
        }
        .checkout-page--success .card p {
            margin: 0 0 10px;
        }
        .checkout-page--success .card p:last-child {
            margin-bottom: 0;
        }
        .checkout-page__order-number {
            color: #0f172a;
        }

        /* Catalog layout: sidebar + content */
        .layout-catalog {
            display: grid;
            grid-template-columns: minmax(240px, 300px) 1fr;
            gap: clamp(16px, 2vw, 32px);
            align-items: start;
        }
        @media (max-width: 900px) {
            .layout-catalog {
                grid-template-columns: 1fr;
                gap: clamp(12px, 3vw, 24px);
            }
        }
        .catalog-sidebar {
            position: sticky;
            top: var(--header-sticky-offset);
        }
        @media (max-width: 900px) {
            .catalog-sidebar { position: static; }
        }
        .catalog-filters__title {
            font-size: 0.94rem;
            font-weight: 700;
            margin: 0 0 4px;
            color: var(--text);
        }
        .catalog-category-list {
            list-style: none;
            margin: 14px 0 0;
            padding: 0;
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        .catalog-category-list__link {
            display: block;
            padding: 8px 0;
            border-radius: 10px;
            text-decoration: none;
            color: #2563eb;
            font-size: 0.9rem;
            font-weight: 500;
            background-color: transparent;
            transition:
                background-color var(--duration-hover) var(--ease-hover),
                color calc(var(--duration-hover) * 0.9) var(--ease-hover);
        }
        .catalog-category-list__link:hover {
            background-color: rgba(54, 125, 241, 0.1);
            color: #1d4ed8;
        }
        .catalog-category-list__link.is-active {
            background-color: rgba(54, 125, 241, 0.14);
            color: #1e40af;
            font-weight: 600;
        }
        .catalog-category-list__link.is-active:hover {
            background-color: rgba(54, 125, 241, 0.18);
            color: #1d4ed8;
        }
        /* Каталог: панель сортування — плашки, акцент #ED3828 */
        .catalog-toolbar {
            padding-top: 18px;
            margin-bottom: 16px;
        }
        .catalog-toolbar__head {
            display: flex;
            flex-direction: column;
            align-items: stretch;
            gap: 12px;
        }
        .catalog-toolbar__head-top {
            display: flex;
            flex-direction: column;
            align-items: stretch;
            gap: 10px;
        }
        .catalog-toolbar__head-row {
            display: flex;
            flex-wrap: wrap;
            align-items: baseline;
            justify-content: space-between;
            gap: 8px 16px;
        }
        .catalog-toolbar__title {
            margin: 0;
            font-size: 1.35rem;
            font-weight: 500;
            letter-spacing: -0.02em;
            color: #202124;
        }
        .catalog-toolbar__meta {
            font-size: 0.875rem;
            font-weight: 600;
            color: #707277;
            line-height: 1.2;
            font-family: inherit;
        }
        .catalog-toolbar__meta strong {
            font-weight: 700;
            color: #707277;
        }
        .catalog-toolbar__chips-row {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 10px;
            min-width: 0;
        }
        .catalog-toolbar__chips-row:has(.catalog-toolbar__chips--active) {
            justify-content: space-between;
        }
        .catalog-toolbar__chips-row:not(:has(.catalog-toolbar__chips--active)) {
            justify-content: flex-end;
        }
        .catalog-toolbar__chips--active {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: center;
            min-width: 0;
            flex: 1 1 auto;
        }
        .catalog-toolbar__chips--sort {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: center;
            min-width: 0;
            flex: 0 1 auto;
        }
        .catalog-toolbar__chips-row:has(.catalog-toolbar__chips--active) .catalog-toolbar__chips--sort {
            margin-left: auto;
        }
        .catalog-toolbar__chips {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: center;
            min-width: 0;
        }
        .catalog-toolbar__chip {
            touch-action: manipulation;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin: 0;
            padding: 0 16px;
            min-height: 40px;
            border: 0;
            border-radius: var(--shop-radius-catalog);
            font-size: 0.875rem;
            font-weight: 700;
            font-family: inherit;
            line-height: 1.2;
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
        /* Як у обраної кнопки сорту (.catalog-toolbar__chips--sort … .is-active), палітра циклічно 1–6 */
        .catalog-toolbar__chips--active > a.catalog-toolbar__chip--filter {
            text-decoration: none;
            border: 0;
        }
        .catalog-toolbar__chips--active > a.catalog-toolbar__chip--filter:hover {
            transform: translateY(-6px);
            box-shadow:
                0 28px 56px rgba(15, 23, 42, 0.22),
                0 14px 32px rgba(15, 23, 42, 0.16),
                0 6px 14px rgba(15, 23, 42, 0.1);
        }
        .catalog-toolbar__chips--active > a.catalog-toolbar__chip--filter:active {
            transform: translateY(-2px) scale(0.97);
        }
        .catalog-toolbar__chips--active > a.catalog-toolbar__chip--filter:nth-of-type(6n + 1),
        .catalog-toolbar__chips--active > a.catalog-toolbar__chip--filter:nth-of-type(6n + 1):visited {
            background-color: #3b82f2;
            color: #fff;
            font-weight: 700;
        }
        .catalog-toolbar__chips--active > a.catalog-toolbar__chip--filter:nth-of-type(6n + 1):hover {
            color: #fff;
            filter: brightness(1.06);
        }
        .catalog-toolbar__chips--active > a.catalog-toolbar__chip--filter:nth-of-type(6n + 1):focus-visible {
            outline: 2px solid #3b82f2;
            outline-offset: 2px;
        }
        .catalog-toolbar__chips--active > a.catalog-toolbar__chip--filter:nth-of-type(6n + 2),
        .catalog-toolbar__chips--active > a.catalog-toolbar__chip--filter:nth-of-type(6n + 2):visited {
            background-color: #ed3626;
            color: #fff;
            font-weight: 700;
        }
        .catalog-toolbar__chips--active > a.catalog-toolbar__chip--filter:nth-of-type(6n + 2):hover {
            color: #fff;
            filter: brightness(1.06);
        }
        .catalog-toolbar__chips--active > a.catalog-toolbar__chip--filter:nth-of-type(6n + 2):focus-visible {
            outline: 2px solid #ed3626;
            outline-offset: 2px;
        }
        .catalog-toolbar__chips--active > a.catalog-toolbar__chip--filter:nth-of-type(6n + 3),
        .catalog-toolbar__chips--active > a.catalog-toolbar__chip--filter:nth-of-type(6n + 3):visited {
            background-color: #fcb501;
            color: #fff;
            font-weight: 700;
        }
        .catalog-toolbar__chips--active > a.catalog-toolbar__chip--filter:nth-of-type(6n + 3):hover {
            color: #fff;
            filter: brightness(1.04);
        }
        .catalog-toolbar__chips--active > a.catalog-toolbar__chip--filter:nth-of-type(6n + 3):focus-visible {
            outline: 2px solid #fcb501;
            outline-offset: 2px;
        }
        .catalog-toolbar__chips--active > a.catalog-toolbar__chip--filter:nth-of-type(6n + 4),
        .catalog-toolbar__chips--active > a.catalog-toolbar__chip--filter:nth-of-type(6n + 4):visited {
            background-color: #397dea;
            color: #fff;
            font-weight: 700;
        }
        .catalog-toolbar__chips--active > a.catalog-toolbar__chip--filter:nth-of-type(6n + 4):hover {
            color: #fff;
            filter: brightness(1.06);
        }
        .catalog-toolbar__chips--active > a.catalog-toolbar__chip--filter:nth-of-type(6n + 4):focus-visible {
            outline: 2px solid #397dea;
            outline-offset: 2px;
        }
        .catalog-toolbar__chips--active > a.catalog-toolbar__chip--filter:nth-of-type(6n + 5),
        .catalog-toolbar__chips--active > a.catalog-toolbar__chip--filter:nth-of-type(6n + 5):visited {
            background-color: #33983b;
            color: #fff;
            font-weight: 700;
        }
        .catalog-toolbar__chips--active > a.catalog-toolbar__chip--filter:nth-of-type(6n + 5):hover {
            color: #fff;
            filter: brightness(1.06);
        }
        .catalog-toolbar__chips--active > a.catalog-toolbar__chip--filter:nth-of-type(6n + 5):focus-visible {
            outline: 2px solid #33983b;
            outline-offset: 2px;
        }
        .catalog-toolbar__chips--active > a.catalog-toolbar__chip--filter:nth-of-type(6n + 6),
        .catalog-toolbar__chips--active > a.catalog-toolbar__chip--filter:nth-of-type(6n + 6):visited {
            background-color: #ee3c2b;
            color: #fff;
            font-weight: 700;
        }
        .catalog-toolbar__chips--active > a.catalog-toolbar__chip--filter:nth-of-type(6n + 6):hover {
            color: #fff;
            filter: brightness(1.06);
        }
        .catalog-toolbar__chips--active > a.catalog-toolbar__chip--filter:nth-of-type(6n + 6):focus-visible {
            outline: 2px solid #ee3c2b;
            outline-offset: 2px;
        }
        .catalog-toolbar__chips--sort > .catalog-toolbar__chip:hover {
            transform: translateY(-6px);
            box-shadow:
                0 28px 56px rgba(15, 23, 42, 0.22),
                0 14px 32px rgba(15, 23, 42, 0.16),
                0 6px 14px rgba(15, 23, 42, 0.1);
        }
        .catalog-toolbar__chips--sort > button.catalog-toolbar__chip:nth-of-type(1) {
            background-color: rgba(59, 130, 242, 0.12);
            color: #3b82f2;
        }
        .catalog-toolbar__chips--sort > button.catalog-toolbar__chip:nth-of-type(1):hover {
            background-color: #3b82f2;
            color: #fff;
        }
        .catalog-toolbar__chips--sort > button.catalog-toolbar__chip:nth-of-type(1):focus-visible {
            outline: 2px solid #3b82f2;
            outline-offset: 2px;
        }
        .catalog-toolbar__chips--sort > button.catalog-toolbar__chip:nth-of-type(1).is-active {
            background-color: #3b82f2;
            color: #fff;
            font-weight: 700;
        }
        .catalog-toolbar__chips--sort > button.catalog-toolbar__chip:nth-of-type(1).is-active:hover {
            color: #fff;
            filter: brightness(1.06);
        }
        .catalog-toolbar__chips--sort > button.catalog-toolbar__chip:nth-of-type(2) {
            background-color: rgba(237, 54, 38, 0.12);
            color: #ed3626;
        }
        .catalog-toolbar__chips--sort > button.catalog-toolbar__chip:nth-of-type(2):hover {
            background-color: #ed3626;
            color: #fff;
        }
        .catalog-toolbar__chips--sort > button.catalog-toolbar__chip:nth-of-type(2):focus-visible {
            outline: 2px solid #ed3626;
            outline-offset: 2px;
        }
        .catalog-toolbar__chips--sort > button.catalog-toolbar__chip:nth-of-type(2).is-active {
            background-color: #ed3626;
            color: #fff;
            font-weight: 700;
        }
        .catalog-toolbar__chips--sort > button.catalog-toolbar__chip:nth-of-type(2).is-active:hover {
            color: #fff;
            filter: brightness(1.06);
        }
        .catalog-toolbar__chips--sort > button.catalog-toolbar__chip:nth-of-type(3) {
            background-color: rgba(252, 181, 1, 0.18);
            color: #fcb501;
        }
        .catalog-toolbar__chips--sort > button.catalog-toolbar__chip:nth-of-type(3):hover {
            background-color: #fcb501;
            color: #fff;
        }
        .catalog-toolbar__chips--sort > button.catalog-toolbar__chip:nth-of-type(3):focus-visible {
            outline: 2px solid #fcb501;
            outline-offset: 2px;
        }
        .catalog-toolbar__chips--sort > button.catalog-toolbar__chip:nth-of-type(3).is-active {
            background-color: #fcb501;
            color: #fff;
            font-weight: 700;
        }
        .catalog-toolbar__chips--sort > button.catalog-toolbar__chip:nth-of-type(3).is-active:hover {
            color: #fff;
            filter: brightness(1.04);
        }
        .catalog-toolbar__chips--sort > button.catalog-toolbar__chip:nth-of-type(4) {
            background-color: rgba(57, 125, 234, 0.12);
            color: #397dea;
        }
        .catalog-toolbar__chips--sort > button.catalog-toolbar__chip:nth-of-type(4):hover {
            background-color: #397dea;
            color: #fff;
        }
        .catalog-toolbar__chips--sort > button.catalog-toolbar__chip:nth-of-type(4):focus-visible {
            outline: 2px solid #397dea;
            outline-offset: 2px;
        }
        .catalog-toolbar__chips--sort > button.catalog-toolbar__chip:nth-of-type(4).is-active {
            background-color: #397dea;
            color: #fff;
            font-weight: 700;
        }
        .catalog-toolbar__chips--sort > button.catalog-toolbar__chip:nth-of-type(4).is-active:hover {
            color: #fff;
            filter: brightness(1.06);
        }
        .catalog-toolbar__chips--sort > button.catalog-toolbar__chip:nth-of-type(5) {
            background-color: rgba(51, 152, 59, 0.12);
            color: #33983b;
        }
        .catalog-toolbar__chips--sort > button.catalog-toolbar__chip:nth-of-type(5):hover {
            background-color: #33983b;
            color: #fff;
        }
        .catalog-toolbar__chips--sort > button.catalog-toolbar__chip:nth-of-type(5):focus-visible {
            outline: 2px solid #33983b;
            outline-offset: 2px;
        }
        .catalog-toolbar__chips--sort > button.catalog-toolbar__chip:nth-of-type(5).is-active {
            background-color: #33983b;
            color: #fff;
            font-weight: 700;
        }
        .catalog-toolbar__chips--sort > button.catalog-toolbar__chip:nth-of-type(5).is-active:hover {
            color: #fff;
            filter: brightness(1.06);
        }
        .catalog-toolbar__chips--sort > button.catalog-toolbar__chip:nth-of-type(6) {
            background-color: rgba(238, 60, 43, 0.12);
            color: #ee3c2b;
        }
        .catalog-toolbar__chips--sort > button.catalog-toolbar__chip:nth-of-type(6):hover {
            background-color: #ee3c2b;
            color: #fff;
        }
        .catalog-toolbar__chips--sort > button.catalog-toolbar__chip:nth-of-type(6):focus-visible {
            outline: 2px solid #ee3c2b;
            outline-offset: 2px;
        }
        .catalog-toolbar__chips--sort > button.catalog-toolbar__chip:nth-of-type(6).is-active {
            background-color: #ee3c2b;
            color: #fff;
            font-weight: 700;
        }
        .catalog-toolbar__chips--sort > button.catalog-toolbar__chip:nth-of-type(6).is-active:hover {
            color: #fff;
            filter: brightness(1.06);
        }
        .catalog-toolbar__chips--sort > .catalog-toolbar__chip:active {
            transform: translateY(-2px) scale(0.97);
        }
        @media (max-width: 640px) {
            .catalog-toolbar__title {
                font-size: 1.2rem;
                line-height: 1.25;
            }
            .catalog-toolbar__meta {
                font-size: 0.875rem;
                line-height: 1.2;
            }
            .catalog-toolbar__chips-row {
                flex-direction: column;
                align-items: stretch;
                gap: 12px;
            }
            .catalog-toolbar__chips-row:not(:has(.catalog-toolbar__chips--active)) {
                flex-direction: row;
                justify-content: flex-end;
            }
            .catalog-toolbar__chips-row:has(.catalog-toolbar__chips--active) .catalog-toolbar__chips--sort {
                margin-left: 0;
                width: 100%;
            }
            .catalog-toolbar__chips--active {
                flex-wrap: wrap;
                overflow-x: visible;
            }
            .catalog-toolbar__chips--sort {
                flex-wrap: nowrap;
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
                touch-action: pan-x;
                overscroll-behavior-x: contain;
                padding-bottom: 4px;
                margin-bottom: -4px;
            }
            .catalog-toolbar__chip {
                flex: 0 0 auto;
                min-height: 44px;
                padding: 0 18px;
            }
            .catalog-toolbar__chips--sort > .catalog-toolbar__chip:hover {
                transform: translateY(-4px);
            }
            .catalog-toolbar__chips--active > a.catalog-toolbar__chip--filter:hover {
                transform: translateY(-4px);
            }
        }

        .catalog-search,
        .catalog-filters {
            transition: box-shadow var(--duration-hover) var(--ease-hover);
        }
        .catalog-search:hover,
        .catalog-filters:not(.catalog-filters--in-header):hover {
            box-shadow: var(--shadow-hover-lift);
        }
        .catalog-search {
            margin-bottom: 16px;
        }
        .catalog-search__form {
            display: flex;
            flex-wrap: wrap;
            align-items: flex-end;
            gap: 12px;
        }
        .catalog-search__field {
            flex: 1 1 220px;
            min-width: 0;
        }
        .catalog-search__actions {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        /* Головна: два окремі карткові блоки замість одного спільного .catalog-results */
        .catalog-results.catalog-results--home-panels {
            background: transparent;
            box-shadow: none;
            padding: 0;
            display: flex;
            flex-direction: column;
            gap: clamp(20px, 3.2vw, 32px);
        }
        .home-shop-panel {
            margin: 0;
            padding: clamp(16px, 2vw, 26px) clamp(16px, 2.4vw, 30px);
            border-radius: var(--shop-radius-catalog);
            background: #ffffff;
            border: 1px solid #e8eaed;
            box-shadow:
                0 12px 30px rgba(15, 23, 42, 0.1),
                0 2px 10px rgba(15, 23, 42, 0.05);
        }
        .home-shop-panel__title {
            margin: 0 0 clamp(14px, 2vw, 20px);
            padding-bottom: 12px;
            border-bottom: 1px solid #f1f3f4;
            font-size: 1.35rem;
            font-weight: 600;
            letter-spacing: -0.02em;
            color: #202124;
        }
        .home-shop-panel__empty {
            margin: 0;
            color: #5f6368;
            font-size: 0.92rem;
            line-height: 1.45;
        }
        /* Каталог: один картковий блок (як хіти/рекомендовані на головній) */
        .catalog-results.catalog-results--listing {
            background: transparent;
            box-shadow: none;
            padding: 0;
            max-width: min(80vw, 100%);
            margin-left: auto;
            margin-right: auto;
            margin-top: clamp(14px, 2.2vw, 22px);
            margin-bottom: 20px;
        }
        .catalog-listing-panel:not(.catalog-listing-panel--prompt) .catalog-toolbar {
            padding-top: 0;
            margin-bottom: 0;
        }
        .catalog-listing-panel .catalog-toolbar__head-row {
            padding-bottom: 12px;
            border-bottom: 1px solid #f1f3f4;
            margin-bottom: 2px;
        }
        .catalog-listing-panel .catalog-toolbar__chips-row {
            margin-top: 4px;
            margin-bottom: 14px;
        }
        /* Менший «підйом» чіпів у картці каталогу — не накладається візуально на ряд категорій у шапці */
        .catalog-results.catalog-results--listing .catalog-toolbar__chips--sort > .catalog-toolbar__chip:hover {
            transform: translateY(-3px);
        }
        .catalog-results.catalog-results--listing .catalog-toolbar__chips--active > a.catalog-toolbar__chip--filter:hover {
            transform: translateY(-2px);
        }
        .catalog-listing-panel .pagination-wrap {
            margin-top: 8px;
        }
        .catalog-listing-panel--prompt {
            margin: 0;
        }
        .catalog-results__empty-hint-text {
            margin: 0;
            color: #5f6368;
            font-size: 0.95rem;
            line-height: 1.45;
        }
        .catalog-results {
            transition: opacity 0.3s ease;
            margin: clamp(14px, 2.2vw, 22px) clamp(18px, 2.8vw, 36px) 20px;
            padding-top: clamp(16px, 2vw, 24px);
            padding-bottom: clamp(16px, 2vw, 24px);
            padding-left: clamp(16px, 2.4vw, 30px);
            padding-right: clamp(16px, 2.4vw, 30px);
            border-radius: var(--shop-radius-catalog);
            background: #ffffff;
            box-shadow:
                0 12px 30px rgba(15, 23, 42, 0.1),
                0 2px 10px rgba(15, 23, 42, 0.05);
        }
        .catalog-results.catalog-results--loading {
            opacity: 0.5;
        }
        /* Тулбар (чіпи сортування) лишається клікабельним; блокуємо лише сітку й пагінацію під час fetch. */
        .catalog-results.catalog-results--loading .catalog-results__grid,
        .catalog-results.catalog-results--loading .pagination-wrap {
            pointer-events: none;
        }
        .catalog-results.catalog-results--updated .catalog-results__grid {
            animation: catalogGridFade 0.38s ease;
        }
        /* Лише opacity — translateY дає зайві зсуви в метриках CLS */
        @keyframes catalogGridFade {
            from { opacity: 0.55; }
            to { opacity: 1; }
        }
        @media (prefers-reduced-motion: reduce) {
            .catalog-results.catalog-results--updated .catalog-results__grid { animation: none; }
        }

        @keyframes product-card-pulse {
            0% {
                transform: scale(1);
            }
            50% {
                transform: scale(1.14);
            }
            100% {
                transform: scale(1);
            }
        }
        .catalog-results__grid {
            --product-card-photo-ratio: 10 / 11;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            align-items: stretch;
            margin-top: clamp(14px, 2vw, 26px);
        }
        @media (max-width: 1380px) {
            .catalog-results__grid {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }
        }
        @media (max-width: 980px) {
            .catalog-results__grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }
        @media (max-width: 560px) {
            .catalog-results__grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: clamp(8px, 2.4vw, 12px);
            }
        }

        .product-card-shell {
            position: relative;
            width: 100%;
            min-width: 0;
            height: 100%;
            align-self: stretch;
        }
        .product-card {
            --product-card-accent: #7c3aed;
            --product-card-accent-soft: rgba(124, 58, 237, 0.24);
            --product-card-media-start: #c4b5fd;
            --product-card-media-end: #8b5cf6;
            position: relative;
            display: flex;
            flex-direction: column;
            width: 100%;
            min-width: 0;
            height: 100%;
            margin-bottom: 0;
            padding: 0;
            overflow: hidden;
            border-radius: var(--shop-radius-catalog);
            border: 1px solid rgba(255, 255, 255, 0.85);
            background: linear-gradient(180deg, rgba(255, 255, 255, 0.98) 0%, rgba(248, 250, 252, 0.98) 100%);
            box-shadow:
                0 12px 24px rgba(15, 23, 42, 0.08),
                0 4px 10px rgba(15, 23, 42, 0.04);
            isolation: isolate;
            backdrop-filter: blur(14px);
            -webkit-backdrop-filter: blur(14px);
            transition:
                transform 0.5s cubic-bezier(0.16, 1, 0.3, 1),
                box-shadow 0.5s cubic-bezier(0.16, 1, 0.3, 1);
        }
        .product-card::before {
            content: '';
            position: absolute;
            inset: 0;
            z-index: 0;
            pointer-events: none;
            background: linear-gradient(180deg, rgba(255, 255, 255, 0.24) 0%, rgba(255, 255, 255, 0) 42%);
        }
        .product-card:hover {
            transform: translateY(-8px);
            box-shadow:
                0 20px 36px rgba(15, 23, 42, 0.14),
                0 8px 18px rgba(15, 23, 42, 0.08);
        }
        @media (hover: none), (pointer: coarse) {
            .product-card:hover {
                transform: none;
                box-shadow:
                    0 12px 24px rgba(15, 23, 42, 0.08),
                    0 4px 10px rgba(15, 23, 42, 0.04);
            }
            .product-card:active {
                transform: translateY(-2px);
            }
            .product-card:hover .product-card__badge,
            .product-card:focus-within .product-card__badge {
                transform: scale(1);
            }
        }
        .product-card__link-overlay {
            position: absolute;
            inset: 0;
            z-index: 2;
            border-radius: inherit;
        }
        .product-card__link-overlay:focus-visible {
            outline: 2px solid var(--product-card-accent);
            outline-offset: 3px;
        }
        .product-card__badge {
            position: absolute;
            top: 14px;
            right: 14px;
            z-index: 5;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.38rem 0.72rem;
            border-radius: 999px;
            font-size: 0.7rem;
            font-weight: 700;
            letter-spacing: 0.04em;
            color: #fff;
            background: var(--product-card-accent);
            box-shadow:
                0 10px 20px rgba(15, 23, 42, 0.16),
                0 0 0 1px rgba(255, 255, 255, 0.2) inset;
            transform: scale(0.96);
            transform-origin: top right;
            transition: transform 0.35s ease, box-shadow 0.35s ease;
            pointer-events: none;
        }
        .product-card__badge--default {
            background: #0f766e;
        }
        .product-card__badge--sale {
            background: #ef4444;
        }
        .product-card__badge--bundle {
            background: #7c3aed;
        }
        .product-card__badge--unavailable {
            background: #64748b;
        }
        .product-card:hover .product-card__badge,
        .product-card:focus-within .product-card__badge {
            transform: scale(1);
            box-shadow:
                0 14px 28px rgba(15, 23, 42, 0.2),
                0 0 0 1px rgba(255, 255, 255, 0.24) inset;
        }
        .product-card__media {
            position: relative;
            z-index: 1;
            display: block;
            width: 100%;
            aspect-ratio: var(--product-card-photo-ratio);
            height: auto;
            min-height: 0;
            max-height: none;
            margin: 0;
            overflow: hidden;
            border-radius: var(--shop-radius-catalog) var(--shop-radius-catalog) 0 0;
            border: none;
            background: linear-gradient(180deg, var(--product-card-media-start), var(--product-card-media-end));
            box-shadow: none;
        }
        .product-card__media img {
            position: relative;
            z-index: 0;
            display: block;
            width: 100%;
            height: 100%;
            object-fit: cover;
            object-position: center;
        }
        .product-card__media--empty {
            display: flex;
            align-items: center;
            justify-content: center;
            color: rgba(15, 23, 42, 0.76);
            font-size: 0.84rem;
            font-weight: 600;
            letter-spacing: 0.02em;
            text-decoration: none;
        }
        .product-card__body {
            position: relative;
            z-index: 3;
            padding: 14px 16px 16px;
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 0.85rem;
            min-height: 0;
            /* Текст і ціна «прозорі» для кліків: перехід на товар через .product-card__link-overlay; кнопки нижче з pointer-events: auto */
            pointer-events: none;
        }
        .product-card__body .product-card__cart-react,
        .product-card__body .product-card__like-react,
        .product-card__body a.product-card__btn {
            pointer-events: auto;
        }
        .product-card__eyebrow {
            margin: 0;
            font-size: 0.7rem;
            font-weight: 700;
            letter-spacing: 0.14em;
            text-transform: uppercase;
            color: var(--product-card-accent);
        }
        .product-card__title {
            margin: 0;
            font-size: 1.03rem;
            font-weight: 700;
            line-height: 1.35;
            color: #0f172a;
            letter-spacing: -0.02em;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        .product-card__title-text {
            display: inline-block;
            color: inherit;
            transition: color 0.3s ease, transform 0.3s ease;
        }
        .product-card:hover .product-card__title-text {
            transform: translateX(2px);
        }
        .product-card__actions {
            display: flex;
            flex: 1;
            flex-direction: column;
            align-items: stretch;
            gap: 0.9rem;
            width: 100%;
            margin-top: auto;
        }
        .product-card__excerpt {
            margin: 0;
            width: 100%;
            font-size: 0.84rem;
            line-height: 1.5;
            color: #475569;
        }
        .product-card__footer {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-top: auto;
        }
        .product-card__price-inline {
            flex: 1 1 auto;
            min-width: 0;
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 6px 10px;
        }
        .product-card__prices {
            display: inline-flex;
            flex-wrap: wrap;
            align-items: baseline;
            gap: 6px 12px;
            max-width: 100%;
            font-variant-numeric: tabular-nums;
        }
        .product-card__prices--on-sale {
            flex-direction: column;
            align-items: flex-start;
            justify-content: center;
            align-content: flex-start;
            gap: 2px;
        }
        .product-card__price {
            margin: 0;
            display: inline-flex;
            flex-wrap: nowrap;
            align-items: baseline;
            gap: 0.2em;
            font-size: 1.22rem;
            font-weight: 800;
            color: #0f172a;
            letter-spacing: -0.035em;
            line-height: 1.2;
            white-space: nowrap;
        }
        .product-card__price-amount {
            font-variant-numeric: tabular-nums;
        }
        .product-card__price-currency {
            font-size: 0.68em;
            font-weight: 700;
            color: #64748b;
            letter-spacing: 0;
            line-height: 1;
        }
        .product-card__price--old {
            font-size: 0.8125rem;
            font-weight: 600;
            color: #94a3b8;
            text-decoration: line-through;
            text-decoration-thickness: 1px;
            letter-spacing: -0.02em;
            line-height: 1.25;
        }
        .product-card__price--old .product-card__price-currency {
            color: #b4bcc8;
            font-weight: 600;
        }
        .product-card__price--sale {
            font-size: 1.28rem;
            font-weight: 800;
            color: var(--product-card-accent);
            letter-spacing: -0.04em;
        }
        .product-card__price--sale .product-card__price-currency {
            color: #64748b;
            font-weight: 700;
        }
        .product-card__action-buttons {
            position: relative;
            z-index: 4;
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 10px;
            flex-shrink: 0;
        }
        .product-card__cart-react .product-card__cart-form {
            margin: 0;
            display: inline-flex;
            flex: 0 0 auto;
        }
        .product-card__btn {
            width: 42px;
            min-width: 42px;
            height: 42px;
            min-height: 42px;
            padding: 0;
            border-radius: var(--shop-radius-catalog);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-family: inherit;
            text-decoration: none;
            color: #fff !important;
            background: var(--product-card-accent);
            border: none;
            cursor: pointer;
            box-shadow:
                0 10px 18px rgba(15, 23, 42, 0.14),
                0 0 0 0 var(--product-card-accent-soft);
            transition:
                transform 0.25s ease,
                box-shadow 0.25s ease,
                background-color 0.25s ease,
                border-color 0.25s ease,
                color 0.25s ease;
        }
        .product-card__btn--icon svg {
            flex-shrink: 0;
        }
        .product-card__btn:hover:not(:disabled) {
            transform: translateY(-1px) scale(1.03);
            box-shadow:
                0 0 0 4px var(--product-card-accent-soft),
                0 16px 24px rgba(15, 23, 42, 0.16);
        }
        .product-card__btn:focus-visible {
            outline: 2px solid var(--product-card-accent);
            outline-offset: 3px;
        }
        /* Уподобання / кошик на картці: сіра плашка + іконка (як лайк) */
        .product-card__like-react,
        .product-card__cart-react {
            margin: 0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 5px;
            background-color: #f1f1f1;
            border-radius: var(--shop-radius-catalog);
            flex-shrink: 0;
        }
        .product-card__like-react .product-card__favorite {
            width: 35px;
            min-width: 35px;
            height: 35px;
            min-height: 35px;
            position: relative;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0;
            border: 0;
            border-radius: 0;
            outline: none;
            background: transparent;
            box-shadow: none;
            cursor: pointer;
            color: inherit;
            transition: transform 0.2s ease;
        }
        .product-card__like-react .product-card__favorite:after {
            content: '';
            width: 40px;
            height: 40px;
            position: absolute;
            left: -3px;
            top: -3px;
            background-color: #f5356e;
            border-radius: 50%;
            z-index: 0;
            transform: scale(0);
        }
        .product-card__like-react .product-card__favorite svg {
            position: relative;
            z-index: 1;
            flex-shrink: 0;
        }
        .product-card__like-react .product-card__favorite:hover:after {
            animation: product-card-like-ripple 0.6s ease-in-out forwards;
        }
        .product-card__like-react .product-card__favorite:hover .product-card__heart-icon,
        .product-card__like-react .product-card__favorite:hover .product-card__heart-icon path {
            fill: #f5356e;
            stroke: #f5356e;
        }
        .product-card__like-count {
            min-width: 1.25em;
            height: 35px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin: 0;
            padding: 0 8px 0 2px;
            font-size: 0.95rem;
            font-weight: 600;
            font-variant-numeric: tabular-nums;
            color: #707277;
            text-align: center;
        }
        .product-card__heart-icon path {
            transition: fill 0.2s ease, stroke 0.2s ease;
        }
        .product-card__like-react .product-card__favorite.is-favorite .product-card__heart-icon path {
            fill: #f5356e;
            stroke: #f5356e;
        }
        .product-card__like-react .product-card__favorite:focus-visible {
            outline: 2px solid var(--product-card-accent);
            outline-offset: 2px;
        }
        .product-card__like-react .product-card__favorite:active {
            transform: scale(0.96);
        }
        @keyframes product-card-like-ripple {
            0% {
                transform: scale(0);
                opacity: 0.6;
            }
            100% {
                transform: scale(2);
                opacity: 0;
            }
        }
        .product-card__cart-react .product-card__cart-add {
            width: 35px;
            min-width: 35px;
            height: 35px;
            min-height: 35px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0;
            border: 0;
            border-radius: 0;
            outline: none;
            background: transparent;
            box-shadow: none;
            cursor: pointer;
            color: #707277;
            transition:
                transform 0.2s ease,
                color 0.2s ease;
        }
        .product-card__cart-react .product-card__cart-add .product-card__cart-icon {
            flex-shrink: 0;
        }
        .product-card__cart-react .product-card__cart-add:hover:not(:disabled) {
            color: var(--product-card-accent);
        }
        .product-card__cart-react .product-card__cart-add:focus-visible {
            outline: 2px solid var(--product-card-accent);
            outline-offset: 2px;
        }
        .product-card__cart-react .product-card__cart-add:active:not(:disabled) {
            transform: scale(0.96);
        }
        .product-card__cart-react .product-card__cart-add:disabled {
            opacity: 0.45;
            cursor: not-allowed;
        }
        .product-card__btn:active:not(:disabled) {
            transform: scale(0.98);
        }
        .product-card__btn:disabled {
            opacity: 0.55;
            cursor: not-allowed;
            background: #cbd5e1;
            color: rgba(255, 255, 255, 0.82) !important;
            box-shadow: none;
        }
        .product-card:hover .product-card__btn--icon svg {
            animation: product-card-pulse 1.5s infinite;
        }
        @media (max-width: 640px) {
            .product-card__badge {
                top: 12px;
                right: 12px;
            }
            .product-card__media {
                width: 100%;
                margin: 0;
            }
            .product-card__body {
                padding: 12px 14px 14px;
            }
            .product-card__title {
                font-size: 0.96rem;
            }
            .product-card__price:not(.product-card__price--sale) {
                font-size: 1.1rem;
            }
            .product-card__price--sale {
                font-size: 1.18rem;
            }
            .product-card__price--old {
                font-size: 0.78rem;
            }
            .product-card:hover {
                transform: translateY(-4px);
            }
        }
        @media (prefers-reduced-motion: reduce) {
            .product-card,
            .product-card__title-text,
            .product-card__btn,
            .product-card__like-react .product-card__favorite,
            .product-card__cart-react .product-card__cart-add {
                transition-duration: 0.01ms;
                animation: none !important;
            }
            .product-card__like-react .product-card__favorite:hover:after {
                animation: none !important;
            }
            .product-card:hover .product-card__btn--icon svg {
                animation: none !important;
            }
            .product-card:hover {
                transform: none;
            }
            .catalog-toolbar__chips--sort > .catalog-toolbar__chip,
            .catalog-toolbar__chips--active > a.catalog-toolbar__chip--filter {
                transition-duration: 0.01ms;
            }
            .catalog-toolbar__chips--sort > .catalog-toolbar__chip:hover,
            .catalog-toolbar__chips--sort > .catalog-toolbar__chip:active,
            .catalog-toolbar__chips--active > a.catalog-toolbar__chip--filter:hover,
            .catalog-toolbar__chips--active > a.catalog-toolbar__chip--filter:active {
                transform: none;
            }
        }

        .catalog-results-more {
            margin-top: clamp(14px, 2vw, 22px);
            padding: 16px 18px;
            border-radius: var(--shop-radius-catalog);
            border: 1px solid var(--border);
            background: var(--surface);
            box-shadow: var(--shadow);
        }
        .catalog-results-more__hint {
            margin: 0 0 12px;
            font-size: 0.92rem;
            color: var(--muted);
            line-height: 1.45;
        }
        .catalog-results-more__actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: center;
        }
        .catalog-results-more__next {
            min-height: 44px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        .catalog-results-more__load {
            min-height: 44px;
        }
        .catalog-results-more__load:disabled {
            opacity: 0.55;
            cursor: not-allowed;
        }

        .pagination-wrap { margin-top: 8px; }
        .shop-pagination {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 14px;
            margin-top: 8px;
        }
        .shop-pagination__info { margin: 0; font-size: 0.9rem; color: var(--muted); }
        .shop-pagination__list {
            display: flex;
            flex-wrap: wrap;
            list-style: none;
            margin: 0;
            padding: 0;
            gap: 8px;
            justify-content: center;
            align-items: center;
        }
        .shop-pagination__link {
            display: inline-flex;
            min-width: 40px;
            height: 40px;
            align-items: center;
            justify-content: center;
            padding: 0 12px;
            border-radius: var(--shop-radius-catalog);
            border: 0;
            background-color: #f1f1f1;
            color: #707277;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.95rem;
            font-variant-numeric: tabular-nums;
            transition:
                background-color 0.2s ease,
                color 0.2s ease,
                transform 0.15s ease;
        }
        .shop-pagination__link:hover {
            color: #f5356e;
        }
        .shop-pagination__link:focus-visible {
            outline: 2px solid #f5356e;
            outline-offset: 2px;
        }
        .shop-pagination__link:active {
            transform: scale(0.97);
        }
        .shop-pagination__current {
            display: inline-flex;
            min-width: 40px;
            height: 40px;
            align-items: center;
            justify-content: center;
            padding: 0 12px;
            border-radius: var(--shop-radius-catalog);
            border: 0;
            background-color: #f5356e;
            color: #fff;
            font-weight: 700;
            font-size: 0.95rem;
            font-variant-numeric: tabular-nums;
        }
        .shop-pagination__disabled {
            display: inline-flex;
            min-width: 40px;
            height: 40px;
            align-items: center;
            justify-content: center;
            padding: 0 12px;
            border-radius: var(--shop-radius-catalog);
            border: 0;
            background-color: #f1f1f1;
            color: #a8adb4;
            cursor: not-allowed;
            opacity: 0.85;
        }
        .shop-pagination__dots { padding: 0 6px; color: var(--muted); }
        @media (prefers-reduced-motion: reduce) {
            .catalog-toolbar__chip:active,
            .shop-pagination__link:active {
                transform: none;
            }
        }

        /* Footer: прозорий фон; картки блоків з власним фоном */
        .site-footer {
            --footer-accent: #ef3829;
            --footer-card-ease: cubic-bezier(0.16, 1, 0.3, 1);
            --footer-card-shadow:
                0 12px 24px rgba(15, 23, 42, 0.08),
                0 4px 10px rgba(15, 23, 42, 0.04);
            --footer-card-shadow-hover:
                0 20px 36px rgba(15, 23, 42, 0.14),
                0 8px 18px rgba(15, 23, 42, 0.08);
            margin-top: auto;
            min-width: 0;
            font-family: var(--font, "Rubik", system-ui, sans-serif);
            font-size: 0.8125rem;
            font-weight: 500;
            color: #475467;
            border-top: none;
            background: transparent;
        }
        .site-footer__shell {
            background: transparent;
            padding: 14px 0 12px;
            overflow: visible;
        }
        .site-footer__triple {
            display: grid;
            gap: 14px 16px;
            align-items: start;
        }
        .site-footer__triple--brand-only {
            grid-template-columns: minmax(0, 1fr);
        }
        .site-footer__triple--with-nav {
            grid-template-columns: minmax(0, 1.45fr) minmax(0, 1.05fr);
        }
        .site-footer__panel {
            min-width: 0;
            text-align: center;
        }
        .site-footer__brand-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(min(100%, 200px), 1fr));
            gap: 14px;
            align-items: stretch;
            padding-bottom: 6px;
        }
        .site-footer__brand-row > .site-footer__block {
            display: flex;
            flex-direction: column;
            align-items: stretch;
            height: 100%;
            min-height: max(100%, clamp(10rem, 16svh, 13rem));
        }
        .site-footer__block {
            position: relative;
            min-width: 0;
            text-align: center;
            border-radius: var(--shop-radius-catalog);
            border: 1px solid rgba(255, 255, 255, 0.85);
            background: linear-gradient(180deg, rgba(255, 255, 255, 0.98) 0%, rgba(248, 250, 252, 0.98) 100%);
            box-shadow: var(--footer-card-shadow);
            isolation: isolate;
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            padding: 14px 14px 16px;
            transition:
                transform 0.5s var(--footer-card-ease),
                box-shadow 0.5s var(--footer-card-ease);
        }
        .site-footer__block:hover {
            transform: translateY(-8px);
            box-shadow: var(--footer-card-shadow-hover);
        }
        @media (hover: none), (pointer: coarse) {
            .site-footer__block:hover {
                transform: none;
                box-shadow: var(--footer-card-shadow);
            }
            .site-footer__block:active {
                transform: translateY(-2px);
                box-shadow: var(--footer-card-shadow-hover);
            }
        }
        .site-footer__block-title {
            display: block;
            font-size: 0.62rem;
            text-transform: uppercase;
            letter-spacing: 0.12em;
            color: var(--text, #367df1);
            font-weight: 800;
            margin: 0 0 12px;
            padding-bottom: 0;
            border-bottom: none;
            text-align: center;
        }
        .site-footer__block--brand {
            padding-top: 10px;
        }
        .site-footer__block--brand .site-footer__brand {
            flex: 1 1 auto;
            min-height: 0;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
        }
        .site-footer__block--brand .site-footer__brand-logo-wrap {
            margin-left: auto;
            margin-right: auto;
            margin-bottom: 10px;
        }
        .site-footer__block--brand .site-footer__brand-logo {
            max-width: min(260px, 100%);
            width: 100%;
            margin: 0 auto;
        }
        .site-footer__block--brand .site-footer__muted {
            max-width: min(32rem, 100%);
            margin-left: auto;
            margin-right: auto;
            text-align: center;
        }
        .site-footer__block--brand .site-footer__phone-line {
            width: 100%;
            text-align: center;
        }
        .site-footer__nav {
            display: grid;
            grid-template-columns: repeat(var(--footer-nav-cols, 3), minmax(0, 1fr));
            gap: 12px 14px;
            min-width: 0;
            align-items: stretch;
        }
        .site-footer__nav > .site-footer__col {
            position: relative;
            min-width: 0;
            text-align: center;
            padding: 14px 14px 16px;
            border-radius: var(--shop-radius-catalog);
            border: 1px solid rgba(255, 255, 255, 0.85);
            background: linear-gradient(180deg, rgba(255, 255, 255, 0.98) 0%, rgba(248, 250, 252, 0.98) 100%);
            box-shadow: var(--footer-card-shadow);
            isolation: isolate;
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            transition:
                transform 0.5s var(--footer-card-ease),
                box-shadow 0.5s var(--footer-card-ease);
        }
        .site-footer__nav > .site-footer__col:hover {
            transform: translateY(-8px);
            box-shadow: var(--footer-card-shadow-hover);
        }
        @media (hover: none), (pointer: coarse) {
            .site-footer__nav > .site-footer__col:hover {
                transform: none;
                box-shadow: var(--footer-card-shadow);
            }
            .site-footer__nav > .site-footer__col:active {
                transform: translateY(-2px);
                box-shadow: var(--footer-card-shadow-hover);
            }
        }
        @media (max-width: 1024px) {
            .site-footer__triple {
                grid-template-columns: 1fr;
            }
        }
        @media (max-width: 720px) {
            .site-footer__nav {
                grid-template-columns: 1fr;
                gap: 18px;
            }
        }
        @media (max-width: 480px) {
            .site-footer__shell {
                padding: 12px 0 10px;
            }
            .site-footer__bottom {
                padding: 10px max(12px, env(safe-area-inset-left, 0px)) 10px max(12px, env(safe-area-inset-right, 0px));
                font-size: 0.75rem;
            }
        }
        @media (min-width: 720px) {
            .site-footer__shell {
                padding: clamp(26px, 3.2vw, 44px) 0 clamp(22px, 2.6vw, 36px);
            }
            .site-footer__triple {
                gap: clamp(22px, 2.5vw, 32px) clamp(24px, 3vw, 40px);
            }
            .site-footer__brand-row {
                gap: clamp(20px, 2.5vw, 28px);
                padding-bottom: clamp(12px, 1.6vw, 18px);
            }
            .site-footer__block {
                padding: clamp(18px, 2vw, 26px) clamp(18px, 2.2vw, 28px) clamp(20px, 2.4vw, 30px);
            }
            .site-footer__block--brand {
                padding-top: clamp(14px, 1.8vw, 22px);
            }
            .site-footer__brand-row > .site-footer__block {
                min-height: max(100%, clamp(12.5rem, 22svh, 19rem));
            }
            .site-footer__nav {
                gap: clamp(16px, 2vw, 24px) clamp(18px, 2.2vw, 28px);
            }
            .site-footer__nav > .site-footer__col {
                padding: clamp(18px, 2vw, 26px) clamp(18px, 2.2vw, 28px) clamp(20px, 2.4vw, 30px);
            }
            .site-footer__bottom {
                padding: clamp(14px, 2vw, 22px) 0;
            }
        }
        @media (min-width: 1280px) {
            .site-footer__shell {
                padding: clamp(40px, 4.2vw, 72px) 0 clamp(34px, 3.8vw, 60px);
            }
            .site-footer__triple {
                gap: clamp(28px, 3vw, 44px) clamp(28px, 3.2vw, 48px);
            }
            .site-footer__brand-row {
                gap: clamp(24px, 2.8vw, 36px);
                padding-bottom: clamp(18px, 2vw, 28px);
            }
            .site-footer__block {
                padding: clamp(22px, 2.4vw, 34px) clamp(22px, 2.5vw, 36px) clamp(24px, 2.8vw, 40px);
            }
            .site-footer__block--brand {
                padding-top: clamp(18px, 2.2vw, 30px);
            }
            .site-footer__brand-row > .site-footer__block {
                min-height: max(100%, clamp(14.5rem, 28svh, 26rem));
            }
            .site-footer__nav {
                gap: clamp(20px, 2.4vw, 32px) clamp(22px, 2.5vw, 36px);
            }
            .site-footer__nav > .site-footer__col {
                padding: clamp(22px, 2.4vw, 34px) clamp(22px, 2.5vw, 36px) clamp(24px, 2.8vw, 40px);
            }
            .site-footer__bottom {
                padding: clamp(18px, 2.4vw, 30px) 0;
            }
        }
        /* Соц: малі картки в ряд; заголовки соц + інфо по центру */
        .site-footer__block--social .site-footer__block-title,
        .site-footer__block--info .site-footer__block-title {
            text-align: center;
            flex-shrink: 0;
            margin: 0 0 12px;
            padding: 0;
            border: 0;
            font-size: 0.6875rem;
            font-weight: 800;
            letter-spacing: 0.14em;
            text-transform: uppercase;
            color: #0f172a;
            background: none;
            -webkit-text-fill-color: currentColor;
        }
        .site-footer__contacts-wrap.site-footer__contacts-wrap--social-block {
            margin-top: 0;
            width: 100%;
            max-width: 100%;
            margin-left: auto;
            margin-right: auto;
            display: flex;
            flex-direction: column;
            flex: 1 1 auto;
            align-items: center;
            justify-content: center;
            min-height: 0;
        }
        .site-footer__contacts-wrap.site-footer__contacts-wrap--social-block .site-header__contacts-google {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 10px 12px;
            width: 100%;
            max-width: min(100%, 22rem);
            margin-left: auto;
            margin-right: auto;
            justify-items: stretch;
            align-items: stretch;
        }
        .site-footer__contacts-wrap.site-footer__contacts-wrap--social-block .site-header__contacts-google > a:only-child {
            grid-column: 1 / -1;
            max-width: 11rem;
            width: 100%;
            justify-self: center;
        }
        .site-footer__contacts-wrap.site-footer__contacts-wrap--social-block .site-header__contacts-google > a:last-child:nth-child(odd):not(:only-child) {
            grid-column: 1 / -1;
            max-width: 12rem;
            width: 100%;
            justify-self: center;
        }
        .site-footer__contacts-wrap.site-footer__contacts-wrap--social-block .site-header__contact-card {
            flex-direction: column;
            flex-wrap: nowrap;
            align-items: center;
            justify-content: center;
            gap: 8px;
            width: 100%;
            min-width: 0;
            max-width: none;
            min-height: 6.75rem;
            margin: 0;
            padding: 11px 10px 9px;
            border-radius: 12px;
            border: 1px solid var(--border, #e4e7ec);
            background: #fff;
            backdrop-filter: none;
            -webkit-backdrop-filter: none;
            box-shadow: var(--footer-card-shadow);
            text-align: center;
            text-decoration: none;
            transition:
                transform 0.45s var(--footer-card-ease),
                border-color 0.2s ease,
                box-shadow 0.45s var(--footer-card-ease),
                background-color 0.2s ease;
        }
        .site-footer__contacts-wrap.site-footer__contacts-wrap--social-block .site-header__contact-card:hover {
            transform: translateY(-6px);
            border-color: rgba(54, 125, 241, 0.35);
            box-shadow: var(--footer-card-shadow-hover);
            background: #fafbff;
        }
        @media (hover: none), (pointer: coarse) {
            .site-footer__contacts-wrap.site-footer__contacts-wrap--social-block .site-header__contact-card:hover {
                transform: none;
                box-shadow: var(--footer-card-shadow);
            }
            .site-footer__contacts-wrap.site-footer__contacts-wrap--social-block .site-header__contact-card:active {
                transform: translateY(-2px);
                box-shadow: var(--footer-card-shadow-hover);
            }
        }
        .site-footer__contacts-wrap.site-footer__contacts-wrap--social-block .site-header__contact-card:focus-visible {
            outline: 2px solid rgba(54, 125, 241, 0.5);
            outline-offset: 2px;
        }
        .site-footer__contacts-wrap.site-footer__contacts-wrap--social-block .site-header__contact-icon--multicolor.site-header__contact-icon--cdn {
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            box-sizing: border-box;
            width: 44px;
            height: 44px;
            padding: 0;
            border: none;
            border-radius: 12px;
            overflow: hidden;
            background: #f1f3f4;
            color: transparent;
            transition: background 0.18s ease;
        }
        .site-footer__contacts-wrap.site-footer__contacts-wrap--social-block .site-header__contact-icon--cdn.site-header__contact-icon--cdn-instagram,
        .site-footer__contacts-wrap.site-footer__contacts-wrap--social-block .site-header__contact-icon--cdn.site-header__contact-icon--cdn-whatsapp,
        .site-footer__contacts-wrap.site-footer__contacts-wrap--social-block .site-header__contact-icon--cdn.site-header__contact-icon--cdn-telegram,
        .site-footer__contacts-wrap.site-footer__contacts-wrap--social-block .site-header__contact-icon--cdn.site-header__contact-icon--cdn-email,
        .site-footer__contacts-wrap.site-footer__contacts-wrap--social-block .site-header__contact-icon--cdn.site-header__contact-icon--cdn-phone {
            background: #fff;
        }
        .site-footer__contacts-wrap.site-footer__contacts-wrap--social-block .site-header__contact-icon--cdn.site-header__contact-icon--cdn-viber {
            background: #665cac;
        }
        .site-footer__contacts-wrap.site-footer__contacts-wrap--social-block .site-header__contact-icon--cdn.site-header__contact-icon--cdn-viber img {
            filter: brightness(0) invert(1);
        }
        .site-footer__contacts-wrap.site-footer__contacts-wrap--social-block .site-header__contact-icon--cdn img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            display: block;
            transition: transform 0.18s ease;
        }
        .site-footer__contacts-wrap.site-footer__contacts-wrap--social-block .site-header__contact-card:hover .site-header__contact-icon--cdn.site-header__contact-icon--cdn-instagram,
        .site-footer__contacts-wrap.site-footer__contacts-wrap--social-block .site-header__contact-card:hover .site-header__contact-icon--cdn.site-header__contact-icon--cdn-whatsapp,
        .site-footer__contacts-wrap.site-footer__contacts-wrap--social-block .site-header__contact-card:hover .site-header__contact-icon--cdn.site-header__contact-icon--cdn-telegram,
        .site-footer__contacts-wrap.site-footer__contacts-wrap--social-block .site-header__contact-card:hover .site-header__contact-icon--cdn.site-header__contact-icon--cdn-email,
        .site-footer__contacts-wrap.site-footer__contacts-wrap--social-block .site-header__contact-card:hover .site-header__contact-icon--cdn.site-header__contact-icon--cdn-phone {
            background: #fff;
        }
        .site-footer__contacts-wrap.site-footer__contacts-wrap--social-block .site-header__contact-card:hover .site-header__contact-icon--cdn.site-header__contact-icon--cdn-viber {
            background: #5746a0;
        }
        .site-footer__contacts-wrap.site-footer__contacts-wrap--social-block .site-header__contact-card:hover .site-header__contact-icon--cdn img {
            transform: scale(1.04);
        }
        .site-footer__contacts-wrap.site-footer__contacts-wrap--social-block .site-header__contact-icon:not(.site-header__contact-icon--cdn) {
            flex-shrink: 0;
            width: 44px;
            height: 44px;
            padding: 0;
            border: none;
            border-radius: 0;
            background: transparent;
            color: var(--text, #367df1);
            transition: color 0.16s ease;
        }
        .site-footer__contacts-wrap.site-footer__contacts-wrap--social-block .site-header__contact-card:hover .site-header__contact-icon:not(.site-header__contact-icon--multicolor) {
            color: #1d4ed8;
        }
        .site-footer__contacts-wrap.site-footer__contacts-wrap--social-block .site-header__contact-value {
            flex: 0 1 auto;
            min-width: 0;
            width: 100%;
            font-size: 0.65rem;
            font-weight: 600;
            color: #64748b;
            line-height: 1.25;
            text-align: center;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            overflow-wrap: anywhere;
            word-break: break-word;
        }
        .site-footer__contacts-wrap.site-footer__contacts-wrap--social-block .site-header__contact-card:hover .site-header__contact-value {
            color: #334155;
        }
        .site-footer__info-wrap {
            width: 100%;
            max-width: 100%;
            margin-left: auto;
            margin-right: auto;
            display: flex;
            flex-direction: column;
            flex: 1 1 auto;
            align-items: center;
            justify-content: center;
            min-height: 0;
        }
        .site-footer__block--info .site-footer__info-links {
            margin: 0;
            padding: 0;
            list-style: none;
            width: 100%;
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            align-items: stretch;
            gap: 10px;
        }
        .site-footer__block--info .site-footer__info-item {
            margin: 0;
            flex: 0 0 auto;
            width: min(9.5rem, 100%);
            min-width: 0;
        }
        .site-footer__block--info a.site-footer__info-card,
        .site-footer__block--info .site-footer__info-card {
            display: flex;
            align-items: center;
            justify-content: center;
            box-sizing: border-box;
            width: 100%;
            min-height: 5.25rem;
            margin: 0;
            padding: 11px 10px 9px;
            border-radius: 12px;
            border: 1px solid var(--border, #e4e7ec);
            background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
            box-shadow: var(--footer-card-shadow);
            font-size: clamp(0.82rem, 2.1vw, 0.95rem);
            font-weight: 650;
            color: #64748b;
            text-decoration: none;
            line-height: 1.25;
            text-align: center;
            transition:
                transform 0.45s var(--footer-card-ease),
                border-color 0.2s ease,
                box-shadow 0.45s var(--footer-card-ease),
                background-color 0.2s ease,
                color 0.2s ease;
            overflow-wrap: anywhere;
            word-break: break-word;
        }
        .site-footer__block--info .site-footer__info-card:hover {
            transform: translateY(-6px);
            border-color: rgba(54, 125, 241, 0.35);
            box-shadow: var(--footer-card-shadow-hover);
            background: #fafbff;
            color: #334155;
        }
        @media (hover: none), (pointer: coarse) {
            .site-footer__block--info .site-footer__info-card:hover {
                transform: none;
                box-shadow: var(--footer-card-shadow);
            }
            .site-footer__block--info .site-footer__info-card:active {
                transform: translateY(-2px);
                box-shadow: var(--footer-card-shadow-hover);
            }
        }
        .site-footer__block--info .site-footer__info-card:focus-visible {
            outline: 2px solid rgba(54, 125, 241, 0.5);
            outline-offset: 2px;
        }
        @media (prefers-reduced-motion: reduce) {
            .site-footer__block,
            .site-footer__nav > .site-footer__col {
                transition-duration: 0.12s;
            }
            .site-footer__block:hover,
            .site-footer__nav > .site-footer__col:hover,
            .site-footer__block:active,
            .site-footer__nav > .site-footer__col:active {
                transform: none;
            }
            .site-footer__contacts-wrap.site-footer__contacts-wrap--social-block .site-header__contact-card {
                transition-duration: 0.12s;
            }
            .site-footer__contacts-wrap.site-footer__contacts-wrap--social-block .site-header__contact-card:hover,
            .site-footer__contacts-wrap.site-footer__contacts-wrap--social-block .site-header__contact-card:active {
                transform: none;
            }
            .site-footer__block--info .site-footer__info-card {
                transition-duration: 0.12s;
            }
            .site-footer__block--info .site-footer__info-card:hover,
            .site-footer__block--info .site-footer__info-card:active {
                transform: none;
            }
        }
        .site-footer__brand-logo-wrap {
            margin-bottom: 6px;
        }
        .site-footer__brand-logo {
            display: block;
            max-width: min(152px, 100%);
            height: auto;
            object-fit: contain;
        }
        .site-footer__brand strong,
        .site-footer__brand-name {
            font-size: 0.95rem;
            letter-spacing: 0.02em;
            color: var(--text, #367df1);
            font-weight: 800;
        }
        .site-footer__muted {
            color: var(--muted, #667085);
            margin: 6px auto 0;
            max-width: 26rem;
            line-height: 1.4;
            font-size: 0.78rem;
            font-weight: 500;
            text-align: center;
        }
        .site-footer__phone-line {
            margin: 8px 0 0;
            font-size: 0.82rem;
        }
        .site-footer__phone {
            color: var(--footer-accent);
            text-decoration: none;
            font-weight: 700;
        }
        .site-footer__phone:hover {
            text-decoration: underline;
            text-decoration-color: rgba(239, 56, 41, 0.45);
        }
        .site-footer__heading {
            display: block;
            font-size: 0.62rem;
            text-transform: uppercase;
            letter-spacing: 0.11em;
            color: var(--text, #367df1);
            margin: 0 0 6px;
            font-weight: 800;
            padding-bottom: 4px;
            border-bottom: 1px solid rgba(54, 125, 241, 0.22);
            text-align: center;
        }
        .site-footer__links { list-style: none; margin: 0; padding: 0; text-align: center; }
        .site-footer__links li {
            margin-bottom: 3px;
        }
        .site-footer__links a {
            color: #475467;
            text-decoration: none;
            font-size: 0.8125rem;
            font-weight: 600;
            transition: color var(--duration-hover) var(--ease-hover);
        }
        .site-footer__links a:hover {
            color: var(--text, #367df1);
        }

        .site-footer__find-heading {
            margin: 0 0 10px;
            font-size: 0.62rem;
            text-transform: uppercase;
            letter-spacing: 0.11em;
            color: var(--text, #367df1);
            font-weight: 800;
            padding-bottom: 4px;
            border-bottom: 1px solid rgba(54, 125, 241, 0.22);
            line-height: 1.35;
        }
        .site-footer__find-address {
            margin: 0 0 10px;
            font-size: 0.78rem;
            font-weight: 600;
            color: #475467;
            line-height: 1.45;
        }
        .site-footer__map-card {
            border-radius: var(--radius, 12px);
            border: 1px solid var(--border, #e4e7ec);
            background: var(--surface, #fff);
            box-shadow: var(--shadow, 0 1px 3px rgba(16, 24, 40, 0.06));
            overflow: hidden;
        }
        .site-footer__map-head {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 6px 10px;
            padding: 8px 12px;
            background: var(--surface, #fff);
            border-bottom: 1px solid var(--border, #e4e7ec);
        }
        .site-footer__map-head--cta-only {
            justify-content: flex-end;
        }
        .site-footer__map-title {
            margin: 0;
            font-size: 0.8125rem;
            font-weight: 800;
            letter-spacing: -0.01em;
            color: #344054;
        }
        .site-footer__map-cta {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 5px 11px;
            border-radius: 999px;
            font-size: 0.72rem;
            font-weight: 700;
            text-decoration: none;
            color: #fff;
            background: var(--text, #367df1);
            white-space: nowrap;
            transition: filter 0.15s ease, transform 0.15s ease;
        }
        .site-footer__map-cta:hover {
            filter: brightness(1.05);
            transform: translateY(-0.5px);
        }
        .site-footer__map-body {
            position: relative;
            background: #eef2f7;
        }
        .site-footer__map-frame {
            position: relative;
            width: 100%;
            aspect-ratio: 16 / 5.5;
            min-height: 112px;
            max-height: 168px;
        }
        .site-footer__map-frame iframe {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            border: 0;
        }
        .site-footer__panel--find .site-footer__map-frame {
            aspect-ratio: 4 / 3;
            min-height: 130px;
            max-height: 220px;
        }
        .site-footer__map-placeholder {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100px;
            padding: 14px 14px;
            text-align: center;
            background: #f8fafc;
        }
        .site-footer__map-placeholder__text {
            margin: 0;
            max-width: 32rem;
            font-size: 0.78rem;
            font-weight: 600;
            line-height: 1.45;
            color: var(--muted, #667085);
        }
        .site-footer__map-address {
            margin: 0;
            padding: 8px 12px 10px;
            font-size: 0.78rem;
            font-weight: 600;
            color: #475467;
            border-top: 1px solid var(--border, #e4e7ec);
            background: var(--surface, #fff);
        }

        .site-footer__bottom {
            border-top: none;
            padding: 10px 0;
            color: var(--muted, #667085);
            font-size: 0.78rem;
            font-weight: 600;
            background: transparent;
        }
        .site-footer__bottom-inner { display: flex; justify-content: center; text-align: center; }

        .photos { display: grid; grid-template-columns: repeat(auto-fill, minmax(120px, 1fr)); gap: 8px; }
        .photos img { width: 100%; height: 120px; object-fit: cover; border-radius: 8px; border: 1px solid var(--border); }

        /* Mobile UX polish: єдиний ритм для всіх основних сторінок магазину */
        @media (max-width: 768px) {
            :root {
                --logo-width: min(220px, 62vw);
                --product-card-col-min: 0px;
                --shop-header-margin-bottom: 8px;
                --shop-shell-pad-top: 10px;
            }

            html {
                scroll-padding-top: clamp(78px, var(--header-sticky-offset), 128px);
            }

            body {
                overflow-x: hidden;
                text-size-adjust: 100%;
                -webkit-text-size-adjust: 100%;
            }

            .container {
                padding-left: max(12px, env(safe-area-inset-left, 0px));
                padding-right: max(12px, env(safe-area-inset-right, 0px));
            }

            .site-header {
                --site-header-card-inset: 12px;
                padding-top: 6px;
            }

            .site-header__hero .site-header__row {
                display: grid;
                grid-template-columns: minmax(0, 1fr);
                grid-template-areas:
                    "actions"
                    "contacts"
                    "brand";
                min-height: auto;
                gap: 9px;
                align-items: stretch;
            }

            .site-header__hero .site-header__zone--left {
                grid-area: contacts;
                justify-self: stretch;
                width: 100%;
                max-width: 100%;
                overflow: hidden;
            }

            .site-header__hero .site-header__zone--right {
                grid-area: actions;
                justify-self: stretch;
                width: 100%;
            }

            .site-header__hero .site-header__zone--center {
                grid-area: brand;
            }

            .site-header__hero .site-header__top-actions {
                display: grid;
                grid-template-columns: auto minmax(0, auto) minmax(96px, auto);
                justify-content: end;
                align-items: center;
                gap: 7px;
            }

            .site-header__hero .site-logo {
                width: var(--logo-width);
                max-width: var(--logo-width);
                margin-top: 4px;
            }

            .site-header__hero .site-header__zone--center {
                gap: 10px;
                padding-top: 2px;
            }

            .site-header__contacts-google {
                display: flex;
                flex-direction: row;
                flex-wrap: nowrap;
                align-items: center;
                gap: 7px;
                width: 100%;
                max-width: 100%;
                overflow-x: auto;
                overflow-y: hidden;
                padding: 1px 1px 5px;
                scrollbar-width: none;
                -webkit-overflow-scrolling: touch;
                overscroll-behavior-x: contain;
            }

            .site-header__contacts-google::-webkit-scrollbar {
                display: none;
            }

            .site-header__contact-card {
                flex: 0 0 auto;
                gap: 6px;
                max-width: min(210px, 72vw);
                min-height: 34px;
                padding: 7px 10px;
                border: 1px solid rgba(226, 232, 240, 0.9);
                border-radius: 999px;
                background: rgba(255, 255, 255, 0.82);
                box-shadow: 0 4px 12px rgba(15, 23, 42, 0.06);
            }

            .site-header__contact-icon {
                width: 16px;
                height: 16px;
            }

            .site-header__contact-value {
                max-width: 100%;
                font-size: 0.72rem;
                line-height: 1.05;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
                word-break: normal;
                overflow-wrap: normal;
            }

            .site-header__top-actions {
                gap: 6px;
            }

            .site-header__account,
            .site-header__lang,
            .site-header__gt,
            .site-header__user-chip {
                min-height: 40px;
                height: 40px;
                border-radius: 20px;
            }

            .site-header__user-chip {
                max-width: min(130px, 38vw);
                padding: 3px 8px 3px 3px;
                gap: 6px;
            }

            .site-header__user-avatar,
            .site-header__user-avatar--img,
            .site-header__user-avatar--initials {
                width: 34px;
                height: 34px;
            }

            .site-header__user-name {
                max-width: 68px;
                font-size: 0.8rem;
            }

            .site-header__hero .site-nav__link--cart.cart-pill {
                min-width: 92px;
                width: auto;
                height: 40px;
                min-height: 40px;
                border-radius: 20px;
            }

            .site-header__hero .cart-pill__left {
                gap: 6px;
                padding: 0 8px 0 10px;
            }

            .site-header__hero .cart-pill__label {
                font-size: 0.84rem;
            }

            .site-header__hero .cart-pill__count {
                min-width: 30px;
                padding: 0 8px;
                font-size: 0.88rem;
            }

            .site-header__search .searchbar {
                height: 46px;
                border-radius: 999px;
                box-shadow: 0 8px 18px rgba(15, 23, 42, 0.1);
            }

            .site-header__bottom {
                width: 100%;
                max-width: 100%;
                margin: 8px 0 0;
            }

            .site-header__bottom-inner {
                max-width: 100%;
            }

            .site-shell {
                padding-bottom: 32px;
            }

            .card {
                border-radius: 16px;
                padding: 16px;
                margin-bottom: 12px;
            }

            .btn,
            .btn-buy,
            .btn.secondary,
            .cart-drawer__checkout-btn {
                min-height: 44px;
                border-radius: 12px;
            }

            .product-card__btn {
                min-height: 44px;
                border-radius: var(--shop-radius-catalog);
            }

            .layout-catalog {
                grid-template-columns: 1fr;
                gap: 14px;
            }

            .catalog-sidebar {
                position: static;
            }

            .catalog-results,
            .home-shop-panel {
                margin-left: 0;
                margin-right: 0;
                padding: 14px;
                border-radius: var(--shop-radius-catalog);
            }

            .catalog-results.catalog-results--home-panels {
                gap: 16px;
                padding: 0;
            }

            .home-shop-panel__title {
                margin-bottom: 12px;
                padding-bottom: 10px;
                font-size: 1.12rem;
            }

            .catalog-listing-panel .catalog-toolbar__head-row {
                gap: 10px;
                align-items: flex-start;
            }

            .catalog-results__grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 10px;
                margin-top: 12px;
            }

            .product-card {
                border-radius: var(--shop-radius-catalog);
                box-shadow: 0 8px 18px rgba(15, 23, 42, 0.08);
            }

            .product-card:hover {
                transform: none;
            }

            .product-card__body {
                padding: 11px 11px 12px;
                gap: 0.55rem;
            }

            .product-card__eyebrow,
            .product-card__excerpt {
                display: none;
            }

            .product-card__title {
                font-size: 0.9rem;
                line-height: 1.28;
            }

            .product-card__footer {
                flex-direction: column;
                align-items: stretch;
                gap: 8px;
            }

            .product-card__prices {
                gap: 4px 8px;
            }

            .product-card__price {
                font-size: 1.05rem;
            }

            .product-card__btn {
                width: 100%;
                padding: 0.72rem 0.8rem;
                font-size: 0.88rem;
            }

            .checkout-page {
                padding-left: 0;
                padding-right: 0;
                padding-bottom: max(28px, calc(env(safe-area-inset-bottom, 0px) + 18px));
            }

            .checkout-page__toolbar {
                margin-bottom: 18px;
                padding-top: 2px;
            }

            .checkout-page__title {
                font-size: 1.28rem;
                line-height: 1.18;
            }

            .checkout-page .card {
                padding: 16px;
                border-radius: 16px;
            }

            .checkout-page__form--blocks {
                gap: 12px;
            }

            .checkout-page .checkout-delivery-row {
                flex-direction: column;
                gap: 12px;
            }

            .checkout-page input,
            .checkout-page select,
            .checkout-page textarea {
                min-height: 46px;
                font-size: 16px;
                padding: 12px 14px;
            }

            .checkout-page__actions,
            .checkout-page__actions .btn {
                width: 100%;
            }

            .checkout-page__actions .btn {
                display: inline-flex;
                justify-content: center;
            }

            .checkout-page--success .checkout-page__status {
                margin-bottom: 10px;
                font-size: 0.72rem;
            }

            .cart-drawer__panel {
                width: min(100vw, 560px);
            }

            .cart-drawer__panel-header {
                padding-left: 18px;
                padding-right: 18px;
            }

            .cart-drawer__content {
                padding-left: 18px;
                padding-right: 18px;
            }

            .cart-drawer__line {
                grid-template-columns: 76px minmax(0, 1fr);
                gap: 10px;
                padding: 10px;
                border-radius: 14px;
            }

            .cart-drawer__line-media {
                width: 76px;
                height: 76px;
                border-radius: 12px;
            }

            .cart-drawer__line-main--checkout {
                flex-direction: column;
            }

            .cart-drawer__line-aside {
                align-items: flex-start;
                width: 100%;
            }

            .cart-drawer__line-pricing--checkout-aside {
                align-items: flex-start;
            }

            .shop-pagination {
                gap: 6px;
                padding-inline: 4px;
            }
        }

        @media (max-width: 340px) {
            .catalog-results__grid {
                grid-template-columns: 1fr;
                gap: 10px;
            }
        }

        @media (max-width: 768px) {
            html.shop-layout--catalog-one-col .catalog-results__grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 420px) {
            :root {
                --logo-width: min(200px, 58vw);
            }

            .site-header__contact-value {
                font-size: 0.72rem;
            }

            .site-header__hero .site-nav__link--cart.cart-pill {
                min-width: 84px;
                width: auto;
            }

            .product-card__media {
                aspect-ratio: 10 / 9;
            }
        }
    </style>
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
    @stack('scripts')
    <script>
    (function () {
        var drawer = document.querySelector('[data-cart-drawer]');
        if (!drawer) return;

        var panel = drawer.querySelector('.cart-drawer__panel');
        var content = drawer.querySelector('[data-cart-drawer-content]');
        var pageContent = document.querySelector('[data-cart-page-content]');
        var toastStack = document.querySelector('[data-cart-toast-stack]');
        var openButtons = Array.prototype.slice.call(document.querySelectorAll('[data-cart-open]'));
        var closeButtons = Array.prototype.slice.call(document.querySelectorAll('[data-cart-close]'));
        var panelCountEls = Array.prototype.slice.call(document.querySelectorAll('[data-cart-panel-count]'));
        var badgeEls = Array.prototype.slice.call(document.querySelectorAll('[data-cart-badge]'));
        var lastActiveElement = null;
        var closeTimer = null;
        var toastHideTimer = null;

        function getFocusableElements() {
            return Array.prototype.slice.call(
                drawer.querySelectorAll('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])')
            ).filter(function (el) {
                return !el.hasAttribute('disabled') && el.getAttribute('aria-hidden') !== 'true';
            });
        }

        function setExpanded(isOpen) {
            openButtons.forEach(function (btn) {
                btn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
            });
        }

        function syncSummary(summary) {
            var itemsCount = summary && typeof summary.items_count === 'number' ? summary.items_count : 0;

            var prevBadgeCount = null;
            if (badgeEls.length) {
                var parsed = parseInt(badgeEls[0].textContent || '0', 10);
                prevBadgeCount = isNaN(parsed) ? 0 : parsed;
            }

            panelCountEls.forEach(function (el) {
                el.textContent = String(itemsCount);
            });

            badgeEls.forEach(function (badge) {
                badge.textContent = String(itemsCount);
            });

            if (prevBadgeCount !== null && prevBadgeCount !== itemsCount) {
                badgeEls.forEach(function (badge) {
                    pulseNumberClass(badge, 'cart-pill__count-num--pulse');
                });
            }

            openButtons.forEach(function (btn) {
                btn.classList.toggle('cart-pill--has-items', itemsCount > 0);
            });
        }

        function currentRenderedSummaryTotal() {
            var totalEl = (content && content.querySelector('[data-cart-summary-total]'))
                || (pageContent && pageContent.querySelector('[data-cart-summary-total]'));
            if (!(totalEl instanceof HTMLElement)) {
                return null;
            }

            var raw = parseFloat(totalEl.getAttribute('data-cart-summary-total') || '');

            return isNaN(raw) ? null : raw;
        }

        function formatMoney(value) {
            return Number(value || 0).toLocaleString('uk-UA', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2,
            }) + ' ₴';
        }

        function animateNumericValue(from, to, duration, onFrame, onDone) {
            if (typeof onFrame !== 'function') return;

            var start = null;
            var diff = to - from;

            if (Math.abs(diff) < 0.009) {
                onFrame(to);
                if (typeof onDone === 'function') onDone();
                return;
            }

            function tick(ts) {
                if (start === null) start = ts;
                var progress = Math.min(1, (ts - start) / duration);
                var eased = 1 - Math.pow(1 - progress, 3);
                onFrame(from + diff * eased);

                if (progress < 1) {
                    requestAnimationFrame(tick);
                } else if (typeof onDone === 'function') {
                    onDone();
                }
            }

            requestAnimationFrame(tick);
        }

        function pulseNumberClass(el, className) {
            if (!(el instanceof HTMLElement)) return;
            el.classList.remove(className);
            void el.offsetWidth;
            el.classList.add(className);
        }

        function captureCartVisualState() {
            var state = {
                summaryTotal: currentRenderedSummaryTotal(),
                lines: {},
            };

            document.querySelectorAll('[data-cart-line-key]').forEach(function (lineEl) {
                if (!(lineEl instanceof HTMLElement)) return;
                var key = lineEl.getAttribute('data-cart-line-key');
                if (!key) return;

                var qtyInput = lineEl.querySelector('[data-cart-qty-input]');
                var totalEl = lineEl.querySelector('[data-cart-line-total]');
                var qty = qtyInput instanceof HTMLInputElement ? parseInt(qtyInput.value || '0', 10) : NaN;
                var total = totalEl instanceof HTMLElement ? parseFloat(totalEl.getAttribute('data-cart-line-total') || '') : NaN;

                state.lines[key] = {
                    qty: isNaN(qty) ? null : qty,
                    lineTotal: isNaN(total) ? null : total,
                };
            });

            return state;
        }

        function animateCartState(prevState, summary) {
            var nextSummary = summary && typeof summary.total === 'number' ? summary.total : null;
            if (prevState && prevState.summaryTotal !== null && nextSummary !== null) {
                document.querySelectorAll('[data-cart-summary-total]').forEach(function (el) {
                    if (!(el instanceof HTMLElement)) return;
                    animateNumericValue(prevState.summaryTotal, nextSummary, 420, function (value) {
                        el.textContent = formatMoney(value);
                    }, function () {
                        el.textContent = formatMoney(nextSummary);
                    });
                    pulseNumberClass(el, 'cart-drawer__summary-value--pulse');
                });
            }

            document.querySelectorAll('[data-cart-line-key]').forEach(function (lineEl) {
                if (!(lineEl instanceof HTMLElement)) return;
                var key = lineEl.getAttribute('data-cart-line-key');
                if (!key || !prevState || !prevState.lines[key]) return;

                var prevLine = prevState.lines[key];
                var qtyInput = lineEl.querySelector('[data-cart-qty-input]');
                if (qtyInput instanceof HTMLInputElement) {
                    var nextQty = parseInt(qtyInput.value || '0', 10);
                    if (prevLine.qty !== null && !isNaN(nextQty) && prevLine.qty !== nextQty) {
                        animateNumericValue(prevLine.qty, nextQty, 260, function (value) {
                            qtyInput.value = String(Math.round(value));
                        }, function () {
                            qtyInput.value = String(nextQty);
                        });
                        pulseNumberClass(qtyInput, 'cart-drawer__qty-input--pulse');
                    }
                }

                var totalEl = lineEl.querySelector('[data-cart-line-total]');
                var nextLineTotal = totalEl instanceof HTMLElement ? parseFloat(totalEl.getAttribute('data-cart-line-total') || '') : NaN;
                if (totalEl instanceof HTMLElement && prevLine.lineTotal !== null && !isNaN(nextLineTotal) && Math.abs(prevLine.lineTotal - nextLineTotal) > 0.009) {
                    animateNumericValue(prevLine.lineTotal, nextLineTotal, 420, function (value) {
                        totalEl.textContent = formatMoney(value);
                    }, function () {
                        totalEl.textContent = formatMoney(nextLineTotal);
                    });
                    pulseNumberClass(totalEl, 'cart-drawer__line-total--pulse');
                }
            });
        }

        function showCartToast(message, type) {
            if (!toastStack || typeof message !== 'string' || message.trim() === '') {
                return;
            }

            if (toastHideTimer) {
                window.clearTimeout(toastHideTimer);
                toastHideTimer = null;
            }

            toastStack.innerHTML = '';

            var toast = document.createElement('div');
            toast.className = 'cart-toast cart-toast--' + (type === 'error' ? 'error' : 'success');
            toast.setAttribute('role', type === 'error' ? 'alert' : 'status');
            toast.textContent = message;
            toastStack.appendChild(toast);

            requestAnimationFrame(function () {
                toast.classList.add('is-visible');
            });

            toastHideTimer = window.setTimeout(function () {
                toast.classList.remove('is-visible');
                window.setTimeout(function () {
                    if (toast.parentNode === toastStack) {
                        toast.remove();
                    }
                }, 400);
                toastHideTimer = null;
            }, 2200);
        }

        function replaceContent(html, summary) {
            var prevState = captureCartVisualState();

            if (typeof html === 'string') {
                if (content) {
                    content.innerHTML = html;
                }
                if (pageContent) {
                    pageContent.innerHTML = html;
                }
            }

            if (summary) {
                syncSummary(summary);
                requestAnimationFrame(function () {
                    animateCartState(prevState, summary);
                });
            }
        }

        function setFormBusy(form, isBusy) {
            form.dataset.cartBusy = isBusy ? '1' : '0';
            form.setAttribute('aria-busy', isBusy ? 'true' : 'false');

            Array.prototype.slice.call(form.querySelectorAll('button, input')).forEach(function (el) {
                if (el instanceof HTMLButtonElement || (el instanceof HTMLInputElement && el.type !== 'hidden')) {
                    el.disabled = isBusy;
                }
            });
        }

        function defaultErrorMessage(payload) {
            if (payload && typeof payload.statusMessage === 'string' && payload.statusMessage !== '') {
                return payload.statusMessage;
            }

            if (payload && typeof payload.message === 'string' && payload.message !== '') {
                return payload.message;
            }

            if (payload && payload.errors) {
                var firstField = Object.keys(payload.errors)[0];
                if (firstField && Array.isArray(payload.errors[firstField]) && payload.errors[firstField][0]) {
                    return String(payload.errors[firstField][0]);
                }
            }

            return 'Не вдалося оновити кошик.';
        }

        function applyPayload(payload) {
            if (!payload) return;
            replaceContent(payload.html || '', payload.summary || null);
            if (payload.statusMessage) {
                showCartToast(payload.statusMessage, payload.statusType || 'success');
            }
        }

        function submitCartForm(form) {
            if (!(form instanceof HTMLFormElement) || form.dataset.cartBusy === '1') {
                return Promise.resolve(null);
            }

            var formData = new FormData(form);
            setFormBusy(form, true);

            return fetch(form.action, {
                method: (form.getAttribute('method') || 'POST').toUpperCase(),
                body: formData,
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'X-Cart-Fragment': '1',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            })
                .then(function (response) {
                    return response.text().then(function (text) {
                        var payload = null;

                        try {
                            payload = text ? JSON.parse(text) : null;
                        } catch (error) {
                            payload = null;
                        }

                        if (!response.ok) {
                            throw payload || { message: response.statusText || 'Request failed.' };
                        }

                        return payload;
                    });
                })
                .finally(function () {
                    setFormBusy(form, false);
                });
        }

        function cleanupClose() {
            if (closeTimer) {
                window.clearTimeout(closeTimer);
                closeTimer = null;
            }
        }

        function openDrawer() {
            cleanupClose();
            lastActiveElement = document.activeElement instanceof HTMLElement ? document.activeElement : null;
            drawer.classList.remove('is-open');
            drawer.hidden = false;
            /* Два rAF: перший кадр з панеллю за межами екрана, другий — is-open, щоб спрацював CSS transition */
            window.requestAnimationFrame(function () {
                window.requestAnimationFrame(function () {
                    drawer.classList.add('is-open');
                    drawer.setAttribute('aria-hidden', 'false');
                    document.body.classList.add('cart-drawer-open');
                    setExpanded(true);
                    var focusTarget = drawer.querySelector('[data-cart-close]');
                    if (focusTarget instanceof HTMLElement) {
                        focusTarget.focus();
                    } else if (panel instanceof HTMLElement) {
                        panel.focus();
                    }
                });
            });
        }

        function closeDrawer() {
            cleanupClose();
            drawer.classList.remove('is-open');
            drawer.setAttribute('aria-hidden', 'true');
            document.body.classList.remove('cart-drawer-open');
            setExpanded(false);

            closeTimer = window.setTimeout(function () {
                drawer.hidden = true;
            }, 300);

            if (lastActiveElement instanceof HTMLElement) {
                lastActiveElement.focus();
            }
        }

        openButtons.forEach(function (btn) {
            btn.addEventListener('click', function () {
                openDrawer();
            });
        });

        closeButtons.forEach(function (btn) {
            btn.addEventListener('click', function () {
                closeDrawer();
            });
        });

        document.addEventListener('keydown', function (event) {
            if (!drawer.hidden && event.key === 'Tab') {
                var focusable = getFocusableElements();
                if (focusable.length) {
                    var first = focusable[0];
                    var last = focusable[focusable.length - 1];

                    if (event.shiftKey && document.activeElement === first) {
                        event.preventDefault();
                        last.focus();
                        return;
                    }

                    if (!event.shiftKey && document.activeElement === last) {
                        event.preventDefault();
                        first.focus();
                        return;
                    }
                }
            }

            if (event.key === 'Escape' && !drawer.hidden) {
                closeDrawer();
            }
        });

        document.addEventListener('submit', function (event) {
            var form = event.target instanceof Element
                ? event.target.closest('[data-cart-add-form], [data-cart-update-form], [data-cart-remove-form]')
                : null;
            if (!(form instanceof HTMLFormElement)) {
                return;
            }

            event.preventDefault();

            submitCartForm(form)
                .then(function (payload) {
                    if (!payload) return;
                    applyPayload(payload);
                })
                .catch(function (payload) {
                    if (payload && payload.html) {
                        applyPayload(payload);
                        if (payload.statusMessage) {
                            return;
                        }
                        if (!drawer.hidden) {
                            return;
                        }
                    }

                    window.alert(defaultErrorMessage(payload));
                });
        });

        document.addEventListener('click', function (event) {
            var stepBtn = event.target instanceof Element ? event.target.closest('[data-cart-qty-step]') : null;
            if (!(stepBtn instanceof HTMLButtonElement)) {
                return;
            }

            var form = stepBtn.closest('[data-cart-update-form]');
            var input = form ? form.querySelector('[data-cart-qty-input]') : null;
            if (!(form instanceof HTMLFormElement) || !(input instanceof HTMLInputElement)) {
                return;
            }

            event.preventDefault();

            var step = parseInt(stepBtn.getAttribute('data-cart-qty-step') || '0', 10);
            var current = parseInt(input.value || '1', 10);
            var next = isNaN(current) ? 1 : current + step;

            if (next < 1) next = 1;
            if (next > 999) next = 999;

            input.value = String(next);

            if (typeof form.requestSubmit === 'function') {
                form.requestSubmit();
            } else {
                form.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
            }
        });

        document.addEventListener('change', function (event) {
            var input = event.target instanceof Element ? event.target.closest('[data-cart-qty-input]') : null;
            if (!(input instanceof HTMLInputElement)) {
                return;
            }

            var form = input.closest('[data-cart-update-form]');
            if (!(form instanceof HTMLFormElement)) {
                return;
            }

            var next = parseInt(input.value || '1', 10);
            if (isNaN(next) || next < 1) next = 1;
            if (next > 999) next = 999;
            input.value = String(next);

            if (typeof form.requestSubmit === 'function') {
                form.requestSubmit();
            } else {
                form.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
            }
        });

        window.ZoogleCartDrawer = {
            open: openDrawer,
            close: closeDrawer,
            replaceContent: replaceContent,
            syncSummary: syncSummary,
            submitForm: submitCartForm,
        };
    })();
    </script>
    <script>
    (function () {
        var header = document.querySelector('.site-header');
        if (!header) return;
        var heroEl = header.querySelector('[data-site-header-hero]');
        var compactBarEl = header.querySelector('[data-site-header-compact-bar]');
        var forceCompact = document.body && document.body.dataset.forceHeaderCompact === '1';

        var COMPACT_STICKY_FALLBACK_PX = 92;
        var MAX_COMPACT_STICKY_BAR_PX = 160;
        var resizeObserverInst = null;
        var roStickyPending = false;
        var lastStickyOffsetPx = -1;
        var lastCompactStickyHeightPx = -1;

        var headerMbCache = null;
        function headerMarginBottom() {
            if (headerMbCache === null) {
                headerMbCache = parseFloat(getComputedStyle(header).marginBottom) || 0;
            }
            return headerMbCache;
        }

        function heroScrollEndY() {
            if (!heroEl) return 0;
            return heroEl.offsetTop + heroEl.offsetHeight;
        }

        function setCompactStickyHeight(px) {
            var next = Math.max(0, Math.ceil(px || 0));
            if (next === lastCompactStickyHeightPx) return;
            lastCompactStickyHeightPx = next;
            header.style.setProperty('--site-header-compact-sticky-height', next + 'px');
        }

        function updateCompactBarFromScroll() {
            if (forceCompact) {
                header.classList.add('site-header--compact-visible');
                if (compactBarEl) compactBarEl.setAttribute('aria-hidden', 'false');
                return;
            }
            var y = window.scrollY || 0;
            var end = heroScrollEndY();
            var show = y >= end - 1;
            if (show) {
                if (!header.classList.contains('site-header--compact-visible')) {
                    header.classList.add('site-header--compact-visible');
                    if (compactBarEl) compactBarEl.setAttribute('aria-hidden', 'false');
                }
            } else if (header.classList.contains('site-header--compact-visible')) {
                header.classList.remove('site-header--compact-visible');
                if (compactBarEl) compactBarEl.setAttribute('aria-hidden', 'true');
            }
        }

        function applyStickyOffsetFromHeader() {
            var compactBarOn = forceCompact || header.classList.contains('site-header--compact-visible');
            var headerRect = header.getBoundingClientRect();
            var mb = compactBarOn ? 0 : headerMarginBottom();

            if (forceCompact && compactBarEl) {
                var sr = compactBarEl.getBoundingClientRect();
                var ch = Math.ceil(sr.height);
                if (ch > 0) {
                    if (ch <= MAX_COMPACT_STICKY_BAR_PX) {
                        setCompactStickyHeight(ch);
                    } else {
                        setCompactStickyHeight(
                            lastCompactStickyHeightPx > 0 && lastCompactStickyHeightPx <= MAX_COMPACT_STICKY_BAR_PX
                                ? lastCompactStickyHeightPx
                                : COMPACT_STICKY_FALLBACK_PX
                        );
                    }
                }
            } else if (!forceCompact) {
                header.style.removeProperty('--site-header-compact-sticky-height');
                lastCompactStickyHeightPx = -1;
            }

            var px = Math.ceil(headerRect.height + mb);
            if (px > 0) {
                if (!compactBarOn && px < 180) px = 200;
                if (px === lastStickyOffsetPx) return;
                lastStickyOffsetPx = px;
                document.documentElement.style.setProperty('--header-sticky-offset', px + 'px');
            }
        }

        function scheduleStickyFromResizeOrRo() {
            if (roStickyPending) return;
            roStickyPending = true;
            requestAnimationFrame(function () {
                roStickyPending = false;
                applyStickyOffsetFromHeader();
            });
        }

        function syncStickyOffsetNow() {
            applyStickyOffsetFromHeader();
            requestAnimationFrame(applyStickyOffsetFromHeader);
        }

        function onScrollHeaderAll() {
            if (!forceCompact) {
                updateCompactBarFromScroll();
            }
            applyStickyOffsetFromHeader();
        }

        if (forceCompact) {
            header.classList.add('site-header--compact-visible');
            if (compactBarEl) compactBarEl.setAttribute('aria-hidden', 'false');
        } else {
            updateCompactBarFromScroll();
        }
        syncStickyOffsetNow();

        if (typeof ResizeObserver !== 'undefined') {
            resizeObserverInst = new ResizeObserver(scheduleStickyFromResizeOrRo);
            resizeObserverInst.observe(header);
        } else {
            window.addEventListener('scroll', scheduleStickyFromResizeOrRo, { passive: true });
        }
        window.addEventListener('resize', function () {
            headerMbCache = null;
            scheduleStickyFromResizeOrRo();
        }, { passive: true });
        if (window.visualViewport) {
            window.visualViewport.addEventListener('resize', function () {
                headerMbCache = null;
                scheduleStickyFromResizeOrRo();
            }, { passive: true });
        }
        window.addEventListener('scroll', onScrollHeaderAll, { passive: true });
    })();
    </script>
</body>
</html>
