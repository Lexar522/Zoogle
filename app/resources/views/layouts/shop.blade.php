<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'ZOOGLE')</title>
    @stack('meta')
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
            --shadow: 0 1px 3px rgba(16, 24, 40, 0.08);
            --shadow-hover-lift: 0 10px 32px rgba(16, 24, 40, 0.14);
            --font: system-ui, -apple-system, 'Segoe UI', Roboto, Arial, sans-serif;
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
                --header-sticky-offset-transition-duration: 0.01ms;
            }
        }
        *, *::before, *::after { box-sizing: border-box; }
        /* Щоб скрол до якорів / фокус не ховав блоки під розгорнутим хедером */
        html {
            scroll-padding-top: var(--header-sticky-offset);
        }
        body {
            margin: 0;
            min-height: 100vh;
            min-width: 0;
            display: flex;
            flex-direction: column;
            font-family: var(--font);
            background: var(--bg);
            color: var(--text);
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
            /* Стартовий резерв під compact-bar, щоб уникнути стрибка до першого JS-виміру */
            --site-header-compact-sticky-height: 72px;
            /* Ізоляція перерахунків layout усередині хедера (менше ripple у решту сторінки) */
            contain: layout;
            background: var(--bg);
            color: var(--header-text);
            border-bottom-width: 0;
            border-bottom-style: solid;
            border-bottom-color: transparent;
            box-shadow: none;
            /* Липка лише .site-header__sticky; сам header лишається в потоці, але вище за контент каталогу */
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
        .site-header:not(.site-header--compact) {
            --site-header-card-inset: clamp(18px, 2.8vw, 36px);
            padding-top: clamp(12px, 1.8vw, 22px);
        }
        .site-header.site-header--compact {
            padding-top: 0;
        }
        .site-header__stack {
            display: block;
            padding: 0;
            transition:
                border-radius var(--header-motion-duration) var(--header-motion-ease),
                background-color var(--header-motion-duration) var(--header-motion-ease),
                box-shadow var(--header-motion-duration) var(--header-motion-ease);
        }
        .site-header:not(.site-header--compact) .site-header__zone--center {
            gap: 22px;
            padding-top: clamp(18px, 2.8vw, 34px);
        }
        .site-header:not(.site-header--compact) .site-logo {
            margin-top: 52px;
        }
        .site-header:not(.site-header--compact) .site-header__search,
        .site-header:not(.site-header--compact) .site-header__search--center {
            margin-top: clamp(14px, 2.2vw, 28px);
        }
        .site-header:not(.site-header--compact) .site-nav__link--cart.cart-pill {
            width: clamp(128px, 12vw, 176px);
            min-width: 128px;
            max-width: 100%;
        }
        .site-header:not(.site-header--compact) .site-header__stack {
            /* 100% ширини + горизонтальні margin дають overflow; calc лишає симетричні «вікна» зліва/справа */
            box-sizing: border-box;
            width: calc(100% - 2 * var(--site-header-card-inset));
            max-width: calc(100% - 2 * var(--site-header-card-inset));
            margin-left: var(--site-header-card-inset);
            margin-right: var(--site-header-card-inset);
            padding:
                clamp(14px, 2vw, 22px)
                clamp(16px, 2.4vw, 28px)
                clamp(16px, 2.2vw, 26px);
            border-radius: 30px;
            background: #ffffff;
            /* visible: вкладена «картка» категорій + її тінь не обрізаються */
            overflow: visible;
            box-shadow:
                0 12px 30px rgba(15, 23, 42, 0.1),
                0 2px 10px rgba(15, 23, 42, 0.05);
        }
        .site-header__row {
            display: grid;
            grid-template-columns: minmax(0, 1fr) minmax(0, auto) minmax(0, 1fr);
            align-items: start;
            gap: 12px;
            width: 100%;
            max-width: 100%;
        }
        .site-header:not(.site-header--compact) .site-header__row {
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
            transition: margin-bottom 0.22s cubic-bezier(0.32, 0.94, 0.34, 1);
        }
        .site-header__bottom-inner {
            min-height: 0;
            overflow: visible;
        }
        .site-header:not(.site-header--compact) .site-header__bottom {
            box-sizing: border-box;
            width: calc(100% - 2 * var(--site-header-card-inset));
            max-width: calc(100% - 2 * var(--site-header-card-inset));
            margin-left: var(--site-header-card-inset);
            margin-right: var(--site-header-card-inset);
            margin-top: clamp(8px, 1.2vw, 14px);
            margin-bottom: 0;
        }
        .site-header.site-header--compact .site-header__bottom {
            box-sizing: border-box;
            width: calc(100% - 2 * var(--site-header-card-inset));
            max-width: calc(100% - 2 * var(--site-header-card-inset));
            margin-left: var(--site-header-card-inset);
            margin-right: var(--site-header-card-inset);
            margin-top: clamp(8px, 1.2vw, 14px);
            margin-bottom: 0;
            opacity: 1;
            pointer-events: auto;
            overflow: visible;
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
        }
        .site-header__zone--right {
            justify-self: end;
            min-width: 0;
        }
        .site-header__contacts-google {
            display: flex;
            flex-direction: column;
            gap: 12px;
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
            color: #2563eb;
            flex-shrink: 0;
            width: 21px;
            height: 21px;
            transition: color var(--header-motion-duration) var(--header-motion-ease);
        }
        .site-header__contact-value {
            font-size: 1.2rem;
            font-weight: 500;
            color: #2563eb;
            line-height: 1.2;
            overflow-wrap: anywhere;
            word-break: break-word;
            transition: color var(--header-motion-duration) var(--header-motion-ease);
        }
        .site-header__contact-card:hover .site-header__contact-value {
            color: #1d4ed8;
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
        }
        .site-header__top-actions {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            align-self: start;
            justify-self: end;
            transform: none;
        }
        .site-header__account,
        .site-header__lang {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 42px;
            height: 42px;
            min-height: 42px;
            padding: 0;
            border-radius: 999px;
            border: 1px solid var(--border);
            background: #fff;
            color: #2563eb;
            text-decoration: none;
            font: inherit;
            cursor: pointer;
            transition:
                background-color var(--header-motion-duration) var(--header-motion-ease),
                border-color var(--header-motion-duration) var(--header-motion-ease),
                color var(--header-motion-duration) var(--header-motion-ease);
        }
        .site-header__user-chip {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            max-width: min(240px, 38vw);
            padding: 4px 12px 4px 4px;
            min-height: 42px;
            border-radius: 999px;
            border: 1px solid var(--border);
            background: #fff;
            color: var(--text);
            text-decoration: none;
            cursor: pointer;
            transition:
                background-color var(--header-motion-duration) var(--header-motion-ease),
                border-color var(--header-motion-duration) var(--header-motion-ease),
                box-shadow var(--header-motion-duration) var(--header-motion-ease);
        }
        .site-header__user-chip:hover {
            border-color: rgba(54, 125, 241, 0.35);
            box-shadow: 0 2px 10px rgba(54, 125, 241, 0.12);
        }
        .site-header__user-avatar {
            flex-shrink: 0;
            width: 40px;
            height: 40px;
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
            font-size: 0.85rem;
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
        }
        .site-header__search .searchbar {
            font-size: 14px;
            font-family: Arial, sans-serif;
            color: #202124;
            display: flex;
            z-index: 3;
            height: 52px;
            background: #fff;
            border: 1px solid #dfe1e5;
            box-shadow: none;
            border-radius: 28px;
            margin: 0 auto;
            width: 100%;
            max-width: 100%;
            transition:
                border-color var(--header-motion-duration) var(--header-motion-ease),
                box-shadow var(--header-motion-duration) var(--header-motion-ease),
                border-radius var(--header-motion-duration) var(--header-motion-ease);
        }
        .site-header__search:hover .searchbar,
        .site-header__search:focus-within .searchbar {
            box-shadow: 0 1px 6px rgba(32, 33, 36, 0.28);
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
            max-width: 100%;
            width: 100%;
        }
        .site-header__search .searchbar-input::placeholder {
            color: #70757a;
        }
        .site-header:not(.site-header--compact) .site-header__search .searchbar {
            height: 56px;
            border-radius: 30px;
        }
        .site-header:not(.site-header--compact) .site-header__search .searchbar-input-spacer {
            height: 36px;
        }
        .site-header:not(.site-header--compact) .site-header__search .searchbar-input {
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
            border-radius: 999px;
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
        }
        .site-header:hover .site-logo__img {
            transform: none;
        }
        @media (prefers-reduced-motion: reduce) {
            .site-logo__img { transition: none; }
            .site-header:hover .site-logo__img { transform: none; }
        }

        /* Компактний хедер: липкий лише .site-header__sticky; категорії під смугою, не sticky */
        .site-header.site-header--compact {
            position: relative;
            /* layout containment гальмує перерахунок позиції категорій під час анімації висоти смуги */
            contain: none;
            --site-header-card-inset: clamp(18px, 2.8vw, 36px);
            padding-top: var(--site-header-compact-sticky-height);
            background: var(--bg);
            color: var(--header-text);
            box-shadow: none;
            border-bottom-width: 0;
            border-bottom-color: transparent;
            margin-bottom: var(--shop-header-margin-bottom);
        }
        .site-header.site-header--compact .site-header__sticky {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 50;
            flex-shrink: 0;
            min-width: 0;
            background: #ffffff;
            color: #202124;
            box-shadow: 0 1px 6px rgba(60, 64, 67, 0.12);
            border-bottom: 1px solid #dadce0;
            transition:
                background-color var(--header-motion-duration) var(--header-motion-ease),
                box-shadow var(--header-motion-duration) var(--header-motion-ease),
                color var(--header-motion-duration) var(--header-motion-ease),
                border-color var(--header-motion-duration) var(--header-motion-ease);
        }
        .site-header.site-header--compact .site-header__stack {
            width: auto;
            max-width: none;
            margin-left: 0;
            margin-right: 0;
            border-radius: 0;
            background: transparent;
            box-shadow: none;
            overflow: visible;
            padding-top: 10px;
            padding-bottom: 10px;
        }
        /* лого | контакти | пошук | дії — DOM: left, center(logo,search), right → order */
        .site-header.site-header--compact .site-header__row {
            grid-template-columns: auto auto minmax(0, 1fr) auto;
            align-items: center;
            gap: clamp(10px, 1.4vw, 16px);
        }
        .site-header.site-header--compact .site-header__zone--left {
            display: flex;
            flex-direction: column;
            justify-content: center;
            gap: 0;
            min-width: 0;
            max-width: min(220px, 30vw);
            order: 2;
            justify-self: start;
            align-self: center;
            margin-left: 0;
            opacity: 1;
            transform: none;
        }
        .site-header.site-header--compact .site-header__zone--center {
            display: contents;
        }
        .site-header.site-header--compact .site-logo {
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
        .site-header.site-header--compact .site-logo__img {
            width: 100%;
            max-width: 100%;
        }
        .site-header.site-header--compact .site-header__search,
        .site-header.site-header--compact .site-header__search--center {
            width: min(520px, 100%);
            max-width: min(520px, 42vw);
            min-width: 0;
            justify-self: end;
            align-self: center;
            margin-top: 0;
            order: 3;
        }
        .site-header.site-header--compact .site-header__zone--right {
            order: 4;
            justify-self: end;
            align-self: center;
        }
        .site-header.site-header--compact .site-header__contacts-google {
            gap: 2px;
        }
        .site-header.site-header--compact .site-header__contact-card {
            gap: 5px;
        }
        .site-header.site-header--compact .site-header__contact-icon {
            width: 12px;
            height: 12px;
            color: #1a73e8;
        }
        .site-header.site-header--compact .site-header__contact-value {
            font-size: 0.72rem;
            font-weight: 500;
            color: #5f6368;
            line-height: 1.2;
        }
        .site-header.site-header--compact .site-header__contact-card:hover .site-header__contact-value {
            color: #202124;
        }
        .site-header.site-header--compact .site-header__search .searchbar {
            height: 40px;
            border-radius: 24px;
            background: #fff;
            border-color: #dfe1e5;
            box-shadow: 0 1px 6px rgba(60, 64, 67, 0.12);
        }
        .site-header.site-header--compact .site-header__search .searchbar-wrapper {
            padding: 3px 6px 0 12px;
        }
        .site-header.site-header--compact .site-header__search .searchbar-left,
        .site-header.site-header--compact .site-header__search .searchbar-right {
            margin-top: -3px;
        }
        .site-header.site-header--compact .site-header__search .searchbar-input-spacer {
            height: 28px;
            font-size: 0.9rem;
        }
        .site-header.site-header--compact .site-header__search .searchbar-input {
            height: 28px;
            margin-top: -31px;
            font-size: 0.9rem;
        }
        .site-header.site-header--compact .site-header__search-btn {
            width: 2.4em;
            min-width: 2.4em;
            padding: 0 6px;
        }
        .site-header.site-header--compact .site-header__top-actions {
            align-self: center;
            gap: 8px;
        }
        .site-header.site-header--compact .site-header__account,
        .site-header.site-header--compact .site-header__lang {
            width: 40px;
            height: 40px;
            min-height: 40px;
            background: #fff;
            border-color: #dadce0;
            color: #2563eb;
        }
        .site-header.site-header--compact .site-header__user-chip {
            max-width: min(210px, 50vw);
            min-height: 40px;
            padding: 3px 10px 3px 3px;
            gap: 8px;
        }
        .site-header.site-header--compact .site-header__user-avatar,
        .site-header.site-header--compact .site-header__user-avatar--img,
        .site-header.site-header--compact .site-header__user-avatar--initials {
            width: 34px;
            height: 34px;
        }
        .site-header.site-header--compact .site-header__user-avatar--initials {
            font-size: 0.75rem;
        }
        .site-header.site-header--compact .site-header__user-name {
            font-size: 0.82rem;
        }
        .site-header.site-header--compact .site-nav__link--cart.cart-pill {
            min-width: 84px;
            min-height: 40px;
            height: 40px;
            border-radius: 24px;
        }
        .site-header.site-header--compact .cart-pill__icon {
            width: 19px;
            height: 19px;
        }
        .site-header.site-header--compact .cart-pill__label {
            font-size: 0.76rem;
        }
        .site-header.site-header--compact .cart-pill__count {
            font-size: 0.8rem;
        }
        .site-header.site-header--compact .cart-pill__left {
            gap: 7px;
            padding: 0 8px 0 10px;
        }
        @media (max-width: 900px) {
            .site-header.site-header--compact .site-header__zone--left {
                display: none;
            }
            .site-header.site-header--compact .site-header__row {
                grid-template-columns: 1fr;
                justify-items: stretch;
            }
            .site-header.site-header--compact .site-header__zone--center {
                display: flex;
                flex-direction: column;
                align-items: stretch;
                gap: 10px;
            }
            .site-header.site-header--compact .site-logo {
                order: 0;
                max-width: min(200px, 70vw);
            }
            .site-header.site-header--compact .site-logo__img {
                width: min(200px, 100%);
                max-width: 100%;
            }
            .site-header.site-header--compact .site-header__search,
            .site-header.site-header--compact .site-header__search--center {
                max-width: 100%;
                justify-self: stretch;
                order: 0;
            }
            .site-header.site-header--compact .site-header__zone--right {
                order: 0;
                justify-self: stretch;
            }
        }
        @media (prefers-reduced-motion: reduce) {
            .site-header,
            .site-header__stack,
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
            border-radius: 8px;
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
        /* Кошик: висота та заокруглення як у .searchbar у цьому ж хедері */
        .site-nav__link--cart.cart-pill {
            display: inline-flex;
            align-items: stretch;
            flex-direction: row;
            position: relative;
            width: auto;
            min-width: 76px;
            max-width: 100%;
            height: 46px;
            min-height: 46px;
            padding: 0;
            border: none;
            border-radius: 24px;
            background-color: var(--color-buy);
            color: #fff !important;
            font-weight: 600;
            cursor: pointer;
            overflow: hidden;
            box-shadow: var(--shadow);
            transition:
                background-color var(--duration-hover) var(--ease-hover),
                box-shadow var(--duration-hover) var(--ease-hover),
                border-radius var(--header-motion-duration) var(--header-motion-ease);
        }
        .site-nav__link--cart.cart-pill:hover {
            color: #fff !important;
            background-color: var(--color-buy-hover);
            box-shadow: var(--shadow-hover-lift);
        }
        .site-nav__link--cart.cart-pill:focus-visible {
            outline: 2px solid #fff;
            outline-offset: 2px;
        }
        .site-nav__link--cart.cart-pill.is-active {
            color: #fff !important;
            background-color: var(--color-buy-hover);
            box-shadow: 0 0 0 2px rgba(var(--color-buy-rgb), 0.45);
        }
        .site-nav__link--cart.cart-pill.is-active:hover {
            background-color: #267a2a;
        }
        .cart-pill__left {
            flex: 1 1 70%;
            min-width: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 0 10px 0 12px;
        }
        .cart-pill__icon {
            flex-shrink: 0;
            width: 21px;
            height: 21px;
            fill: rgba(255, 255, 255, 0.88);
            transition: fill var(--duration-hover) var(--ease-hover), transform 0.2s ease;
        }
        .cart-pill__label {
            color: #fff;
            font-size: 0.82rem;
            font-weight: 600;
            font-family: inherit;
            white-space: nowrap;
        }
        .cart-pill__count {
            flex: 0 0 30%;
            display: flex;
            align-items: center;
            justify-content: center;
            min-width: 2.1em;
            color: rgba(255, 255, 255, 0.72);
            font-size: 0.875rem;
            font-variant-numeric: tabular-nums;
            border-left: 2px solid rgba(255, 255, 255, 0.35);
            transition: color var(--duration-hover) var(--ease-hover);
        }
        .cart-pill--has-items .cart-pill__count {
            color: #fff;
        }
        @keyframes cart-pill-icon-pop {
            0% {
                transform: scale(0.65);
            }
            100% {
                transform: scale(1.08);
            }
        }
        .cart-pill--has-items .cart-pill__icon {
            fill: #fff;
            animation: cart-pill-icon-pop 0.22s ease-out 1;
        }
        @media (prefers-reduced-motion: reduce) {
            .cart-pill--has-items .cart-pill__icon {
                animation: none;
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
        }
        .cart-drawer__scrim {
            position: absolute;
            inset: 0;
            border: 0;
            background: rgba(16, 24, 40, 0.46);
            opacity: 0;
            cursor: pointer;
            transition: opacity 0.22s ease;
        }
        .cart-drawer__panel {
            position: absolute;
            top: 0;
            right: 0;
            height: 100%;
            width: min(430px, 100vw);
            display: flex;
            flex-direction: column;
            background: var(--surface);
            box-shadow: -8px 0 32px rgba(16, 24, 40, 0.18);
            transform: translateX(100%);
            transition: transform 0.22s ease;
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
            padding: 18px 18px 14px;
            border-bottom: 1px solid var(--border);
        }
        .cart-drawer__panel-title {
            margin: 0;
            color: #202124;
            font-size: 1.2rem;
            line-height: 1.2;
        }
        .cart-drawer__panel-meta {
            margin: 4px 0 0;
            color: var(--muted);
            font-size: 0.9rem;
        }
        .cart-drawer__close-btn {
            width: 40px;
            height: 40px;
            border-radius: 999px;
            border: 1px solid var(--border);
            background: #fff;
            color: #344054;
            font-size: 26px;
            line-height: 1;
            cursor: pointer;
        }
        .cart-drawer__content {
            flex: 1 1 auto;
            overflow-y: auto;
            padding: 16px 16px max(20px, env(safe-area-inset-bottom, 0px));
        }
        .cart-toast-stack {
            position: fixed;
            left: 50%;
            top: max(16px, env(safe-area-inset-top, 0px));
            z-index: 260;
            display: flex;
            flex-direction: column;
            gap: 10px;
            width: min(calc(100vw - 24px), 420px);
            transform: translateX(-50%);
            pointer-events: none;
        }
        .cart-toast {
            padding: 12px 14px;
            border-radius: 14px;
            font-size: 0.92rem;
            line-height: 1.45;
            box-shadow: 0 14px 34px rgba(15, 23, 42, 0.18);
            opacity: 0;
            transform: translateY(-10px) scale(0.985);
            transition:
                opacity 0.24s ease,
                transform 0.28s cubic-bezier(0.22, 1, 0.36, 1);
            pointer-events: auto;
            backdrop-filter: blur(8px);
        }
        .cart-toast.is-visible {
            opacity: 1;
            transform: translateY(0) scale(1);
        }
        .cart-toast--success {
            background: #ecfdf3;
            color: #067647;
            border: 1px solid #abefc6;
        }
        .cart-toast--error {
            background: #fef3f2;
            color: #b42318;
            border: 1px solid #fecdca;
        }
        .cart-drawer__empty {
            padding: 18px 6px;
            text-align: center;
        }
        .cart-drawer__empty-title {
            margin: 0 0 8px;
            color: #202124;
            font-size: 1.1rem;
        }
        .cart-drawer__empty-text {
            margin: 0 0 14px;
            color: var(--muted);
            line-height: 1.5;
        }
        .cart-drawer__empty-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 11px 16px;
            border-radius: 10px;
            background: #1a73e8;
            color: #fff !important;
            text-decoration: none;
            font-weight: 600;
        }
        .cart-drawer__lines {
            display: flex;
            flex-direction: column;
            gap: 14px;
        }
        .cart-drawer__line {
            display: grid;
            grid-template-columns: 92px minmax(0, 1fr);
            gap: 12px;
            padding: 12px;
            border: 1px solid var(--border);
            border-radius: 16px;
            background: #fff;
        }
        .cart-drawer__line-media {
            width: 92px;
            height: 92px;
            border-radius: 12px;
            overflow: hidden;
            background: #f2f4f7;
            border: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .cart-drawer__line-media img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            object-position: center;
        }
        .cart-drawer__line-media-empty {
            padding: 10px;
            font-size: 12px;
            text-align: center;
            color: var(--muted);
        }
        .cart-drawer__line-main {
            min-width: 0;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        .cart-drawer__line-header {
            display: flex;
            align-items: flex-start;
            gap: 8px;
        }
        .cart-drawer__line-title {
            flex: 1 1 auto;
            min-width: 0;
            color: #202124;
            font-weight: 600;
            line-height: 1.4;
            text-decoration: none;
        }
        .cart-drawer__line-title:hover {
            color: #174ea6;
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
            font-size: 12px;
            font-weight: 700;
            line-height: 1.35;
        }
        .cart-drawer__bundle-meta-text {
            color: var(--muted);
            font-size: 12px;
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
            color: var(--muted);
            font-size: 0.9rem;
        }
        .cart-drawer__unit-price--old {
            text-decoration: line-through;
            color: #98a2b3;
        }
        .cart-drawer__line-total {
            color: #202124;
            font-size: 1rem;
            font-weight: 700;
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
        }
        .cart-drawer__qty-input {
            width: 64px;
            padding: 0 10px;
            text-align: center;
            color: #202124;
        }
        .cart-drawer__footer {
            margin-top: 16px;
            padding-top: 16px;
            border-top: 1px solid var(--border);
            display: flex;
            flex-direction: column;
            gap: 14px;
        }
        .cart-drawer__summary {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 10px;
        }
        .cart-drawer__summary-label {
            display: block;
            margin-bottom: 4px;
            color: var(--muted);
            font-size: 0.78rem;
        }
        .cart-drawer__summary-value {
            color: #202124;
            font-size: 0.98rem;
            font-weight: 700;
        }
        .cart-drawer__summary-value--pulse,
        .cart-drawer__line-total--pulse,
        .cart-drawer__qty-input--pulse {
            animation: cartDrawerNumberPulse 0.42s cubic-bezier(0.22, 1, 0.36, 1);
            transform-origin: right center;
            will-change: transform, color, filter;
        }
        .cart-drawer__qty-input--pulse {
            transform-origin: center center;
        }
        @keyframes cartDrawerNumberPulse {
            0% {
                transform: translateY(0) scale(1);
                color: #202124;
                filter: brightness(1);
            }
            35% {
                transform: translateY(-1px) scale(1.045);
                color: #0f766e;
                filter: brightness(1.04);
            }
            100% {
                transform: translateY(0) scale(1);
                color: #202124;
                filter: brightness(1);
            }
        }
        .cart-drawer__checkout-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            min-height: 48px;
            border-radius: 12px;
            background: var(--color-cta);
            color: #fff !important;
            text-decoration: none;
            font-size: 1rem;
            font-weight: 700;
            box-shadow: 0 4px 14px rgba(239, 56, 41, 0.35);
        }
        .cart-drawer__checkout-btn:hover {
            background: var(--color-cta-hover);
        }
        @media (max-width: 480px) {
            .cart-drawer__panel {
                width: 100vw;
            }
            .cart-drawer__content {
                padding-left: 12px;
                padding-right: 12px;
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
            .site-header__row {
                grid-template-columns: 1fr;
                justify-items: center;
            }
            .site-header:not(.site-header--compact) .site-header__row {
                min-height: 0;
                align-items: start;
            }
            .site-header:not(.site-header--compact) .site-header__stack {
                padding:
                    clamp(12px, 2vw, 18px)
                    clamp(14px, 2.4vw, 22px)
                    clamp(12px, 2.2vw, 20px);
            }
            .site-header__contacts-google {
                width: 100%;
                max-width: 360px;
            }
            .site-header__contact-card {
                min-width: 0;
            }
            .site-header__zone--right { justify-self: center; }
            .site-header__zone--center {
                width: 100%;
                max-width: 560px;
            }
            .site-header__search,
            .site-header__search--center {
                width: 100%;
                max-width: min(700px, 100%);
            }
            .site-header:not(.site-header--compact) .site-logo {
                margin-top: 40px;
            }
            .site-header:not(.site-header--compact) .site-header__zone--center {
                gap: 18px;
                padding-top: clamp(12px, 2.2vw, 22px);
            }
        }
        @media (max-width: 600px) {
            .site-header:not(.site-header--compact) {
                --site-header-card-inset: 14px;
            }
            .site-header.site-header--compact {
                --site-header-card-inset: 14px;
            }
            .site-header:not(.site-header--compact) .site-header__stack {
                border-radius: 24px;
            }
            .site-header__contact-value {
                font-size: 0.875rem;
            }
            .site-header:not(.site-header--compact) .site-header__search .searchbar {
                height: 48px;
            }
            .site-header:not(.site-header--compact) .site-header__search .searchbar-wrapper {
                padding: 3px 6px 0 12px;
            }
            .site-header:not(.site-header--compact) .site-header__search .searchbar-input-spacer {
                height: 32px;
            }
            .site-header:not(.site-header--compact) .site-header__search .searchbar-input {
                height: 32px;
                margin-top: -35px;
            }
            .site-header:not(.site-header--compact) .site-header__top-actions {
                gap: 8px;
            }
            .site-header:not(.site-header--compact) .site-nav__link--cart.cart-pill {
                height: 40px;
                min-height: 40px;
                border-radius: 22px;
                min-width: 104px;
                width: clamp(104px, 42vw, 158px);
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
            transition: background-color var(--duration-hover) var(--ease-hover);
        }
        .btn--header:hover { background-color: var(--color-cta-hover); }

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
            font-size: 1rem;
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
            border-radius: 8px;
            text-decoration: none;
            color: #2563eb;
            font-size: 0.95rem;
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
        .catalog-toolbar {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding-top: 18px;
            margin-bottom: 16px;
        }
        .catalog-toolbar__title {
            margin: 0;
            font-size: 1.35rem;
            font-weight: 800;
            color: var(--text);
        }
        .catalog-toolbar__meta { font-size: 0.9rem; color: var(--muted); }
        @media (max-width: 640px) {
            .catalog-toolbar {
                flex-direction: column;
                align-items: flex-start;
                gap: 8px;
            }
            .catalog-toolbar__title {
                font-size: 1.2rem;
                line-height: 1.25;
            }
            .catalog-toolbar__meta {
                font-size: 0.85rem;
                line-height: 1.4;
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

        .catalog-results {
            transition: opacity 0.3s ease;
            margin: clamp(14px, 2.2vw, 22px) clamp(18px, 2.8vw, 36px) 20px;
            padding-top: clamp(16px, 2vw, 24px);
            padding-bottom: clamp(16px, 2vw, 24px);
            padding-left: clamp(16px, 2.4vw, 30px);
            padding-right: clamp(16px, 2.4vw, 30px);
            border-radius: 30px;
            background: #ffffff;
            box-shadow:
                0 12px 30px rgba(15, 23, 42, 0.1),
                0 2px 10px rgba(15, 23, 42, 0.05);
        }
        .catalog-results.catalog-results--loading {
            opacity: 0.5;
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
                grid-template-columns: 1fr;
            }
        }
        .product-card-shell {
            position: relative;
            width: 100%;
            min-width: 0;
            height: 100%;
            align-self: stretch;
        }
        .product-card-shell--expandable > .product-card {
            height: 100%;
        }
        .product-card-shell--expandable.product-card-shell--minh-anim {
            transition: min-height 0.85s cubic-bezier(0.22, 1, 0.36, 1);
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
            border-radius: 22px;
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
                box-shadow 0.5s cubic-bezier(0.16, 1, 0.3, 1),
                border-color 0.5s cubic-bezier(0.16, 1, 0.3, 1);
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
            border-color: var(--product-card-accent-soft);
            box-shadow:
                0 24px 42px rgba(15, 23, 42, 0.16),
                0 10px 28px var(--product-card-accent-soft);
        }
        .product-card-shell--expandable .product-card:hover {
            transform: none;
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
            width: calc(100% - 28px);
            aspect-ratio: var(--product-card-photo-ratio);
            height: auto;
            min-height: 0;
            max-height: none;
            margin: 14px 14px 0;
            overflow: hidden;
            border-radius: 16px;
            border: 1px solid rgba(255, 255, 255, 0.75);
            background:
                linear-gradient(135deg, rgba(255, 255, 255, 0.18), rgba(255, 255, 255, 0)),
                linear-gradient(135deg, var(--product-card-media-start), var(--product-card-media-end));
            box-shadow:
                inset 0 1px 0 rgba(255, 255, 255, 0.7),
                0 2px 6px rgba(15, 23, 42, 0.06);
            transition:
                transform 0.5s cubic-bezier(0.16, 1, 0.3, 1),
                box-shadow 0.5s cubic-bezier(0.16, 1, 0.3, 1);
        }
        .product-card__media img {
            position: relative;
            z-index: 0;
            display: block;
            width: 100%;
            height: 100%;
            object-fit: cover;
            object-position: center;
            transition: transform 0.5s cubic-bezier(0.16, 1, 0.3, 1);
        }
        .product-card:hover .product-card__media {
            transform: translateY(-4px) scale(1.02);
            box-shadow:
                0 18px 30px rgba(15, 23, 42, 0.12),
                0 10px 24px var(--product-card-accent-soft);
        }
        .product-card:hover .product-card__media img {
            transform: scale(1.06);
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
            color: var(--product-card-accent);
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
            display: block;
            max-height: 3em;
            overflow: hidden;
            position: relative;
            transition: max-height 0.85s cubic-bezier(0.22, 1, 0.36, 1);
        }
        .product-card__excerpt::after {
            content: '';
            position: absolute;
            left: 0;
            right: 0;
            bottom: 0;
            height: 58%;
            min-height: 0.55em;
            max-height: 2.15em;
            pointer-events: none;
            opacity: 1;
            background: linear-gradient(
                to bottom,
                rgba(248, 250, 252, 0) 0%,
                rgba(248, 250, 252, 0.08) 18%,
                rgba(248, 250, 252, 0.22) 38%,
                rgba(248, 250, 252, 0.58) 58%,
                rgba(248, 250, 252, 0.9) 78%,
                rgba(248, 250, 252, 0.98) 100%
            );
            transition: opacity 0.85s cubic-bezier(0.22, 1, 0.36, 1);
        }
        .product-card__excerpt:not(.product-card__excerpt--truncated)::after {
            opacity: 0;
        }
        .product-card__footer {
            display: flex;
            align-items: flex-end;
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
            display: flex;
            flex-wrap: wrap;
            align-items: baseline;
            gap: 6px 10px;
        }
        .product-card__price {
            margin: 0;
            font-size: 1.2rem;
            font-weight: 800;
            color: #0f172a;
            letter-spacing: -0.03em;
            line-height: 1.15;
        }
        .product-card__price-currency {
            font-size: 0.72em;
            font-weight: 700;
            color: #64748b;
            margin-left: 2px;
        }
        .product-card__price--old {
            margin: 0;
            font-size: 0.88rem;
            font-weight: 600;
            color: #94a3b8;
            text-decoration: line-through;
            letter-spacing: -0.02em;
        }
        .product-card__price--sale {
            color: var(--product-card-accent);
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
        .product-card__cart-form {
            margin: 0;
            flex: 0 0 auto;
        }
        .product-card__btn,
        .product-card__favorite {
            width: 42px;
            min-width: 42px;
            height: 42px;
            min-height: 42px;
            padding: 0;
            border-radius: 999px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition:
                transform 0.25s ease,
                box-shadow 0.25s ease,
                background-color 0.25s ease,
                border-color 0.25s ease,
                color 0.25s ease;
        }
        .product-card__btn {
            font-family: inherit;
            text-decoration: none;
            color: #fff !important;
            background: var(--product-card-accent);
            border: none;
            cursor: pointer;
            box-shadow:
                0 10px 18px rgba(15, 23, 42, 0.14),
                0 0 0 0 var(--product-card-accent-soft);
        }
        .product-card__btn--icon svg,
        .product-card__favorite svg {
            flex-shrink: 0;
        }
        .product-card__favorite {
            border: 1px solid rgba(148, 163, 184, 0.34);
            background: rgba(255, 255, 255, 0.92);
            cursor: pointer;
            box-shadow:
                0 10px 18px rgba(15, 23, 42, 0.08),
                0 0 0 0 var(--product-card-accent-soft);
            color: #334155;
        }
        .product-card__favorite:hover,
        .product-card__btn:hover:not(:disabled) {
            transform: translateY(-1px) scale(1.03);
            box-shadow:
                0 0 0 4px var(--product-card-accent-soft),
                0 16px 24px rgba(15, 23, 42, 0.16);
        }
        .product-card__favorite:focus-visible,
        .product-card__btn:focus-visible {
            outline: 2px solid var(--product-card-accent);
            outline-offset: 3px;
        }
        .product-card__heart-icon {
            fill: none;
            stroke: currentColor;
            stroke-width: 2;
            transition: fill 0.2s ease, stroke 0.2s ease;
        }
        .product-card__favorite.is-favorite {
            color: #fff;
            border-color: transparent;
            background: var(--product-card-accent);
        }
        .product-card__favorite.is-favorite .product-card__heart-icon {
            fill: currentColor;
            stroke: currentColor;
        }
        .product-card__btn:active:not(:disabled),
        .product-card__favorite:active {
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
        @media (hover: hover) and (pointer: fine) {
            .product-card-shell--expandable.product-card-shell--hover {
                z-index: 30;
            }
            .product-card-shell--expandable.product-card-shell--hover .product-card {
                position: absolute;
                left: 0;
                right: 0;
                top: 0;
                width: auto;
                height: auto;
                min-height: 100%;
                overflow: visible;
                box-shadow:
                    0 24px 42px rgba(15, 23, 42, 0.16),
                    0 10px 28px var(--product-card-accent-soft);
            }
            .product-card-shell--expandable.product-card-shell--hover .product-card__excerpt {
                max-height: min(70vh, 42rem);
            }
            .product-card-shell--expandable.product-card-shell--hover .product-card__excerpt::after {
                opacity: 0;
            }
        }
        @media (hover: hover) and (pointer: fine) and (prefers-reduced-motion: reduce) {
            .product-card__excerpt {
                transition-duration: 0.01ms;
            }
            .product-card__excerpt::after {
                transition-duration: 0.01ms;
            }
            .product-card-shell--expandable.product-card-shell--minh-anim {
                transition-duration: 0.01ms;
            }
        }
        @media (max-width: 640px) {
            .product-card {
                border-radius: 18px;
            }
            .product-card__badge {
                top: 12px;
                right: 12px;
            }
            .product-card__media {
                width: calc(100% - 24px);
                margin: 12px 12px 0;
                border-radius: 14px;
            }
            .product-card__body {
                padding: 12px 14px 14px;
            }
            .product-card__title {
                font-size: 0.96rem;
            }
            .product-card__price {
                font-size: 1.08rem;
            }
            .product-card:hover {
                transform: translateY(-4px);
            }
        }
        @media (prefers-reduced-motion: reduce) {
            .product-card,
            .product-card__media,
            .product-card__title-text,
            .product-card__btn,
            .product-card__favorite {
                transition-duration: 0.01ms;
                animation: none !important;
            }
            .product-card:hover,
            .product-card:hover .product-card__media,
            .product-card:hover .product-card__media img {
                transform: none;
            }
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
            gap: 6px;
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
            border-radius: 10px;
            border: 1px solid var(--border);
            background-color: var(--surface);
            color: var(--text);
            text-decoration: none;
            font-weight: 600;
            font-size: 0.95rem;
            transition:
                border-color var(--duration-hover) var(--ease-hover),
                color var(--duration-hover) var(--ease-hover),
                background-color var(--duration-hover) var(--ease-hover),
                box-shadow var(--duration-hover) var(--ease-hover);
        }
        .shop-pagination__link:hover {
            border-color: var(--text);
            color: var(--text);
        }
        .shop-pagination__current {
            display: inline-flex;
            min-width: 40px;
            height: 40px;
            align-items: center;
            justify-content: center;
            padding: 0 12px;
            border-radius: 10px;
            background: var(--color-accent);
            color: #1c1917;
            font-weight: 700;
            font-size: 0.95rem;
        }
        .shop-pagination__disabled {
            display: inline-flex;
            min-width: 40px;
            height: 40px;
            align-items: center;
            justify-content: center;
            padding: 0 12px;
            border-radius: 10px;
            border: 1px solid var(--border);
            background: #f2f4f7;
            color: #98a2b3;
            cursor: not-allowed;
        }
        .shop-pagination__dots { padding: 0 6px; color: var(--muted); }

        /* Footer */
        .site-footer {
            margin-top: auto;
            min-width: 0;
            background: #1d2939;
            color: #e4e7ec;
            font-size: 0.9rem;
        }
        .site-footer__grid {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr;
            gap: 32px;
            padding: 40px 20px 32px;
        }
        @media (max-width: 768px) {
            .site-footer__grid {
                grid-template-columns: 1fr;
                gap: 24px;
                padding: 32px 16px 28px;
            }
        }
        @media (max-width: 480px) {
            .site-footer__grid {
                gap: 20px;
                padding: 28px 12px 24px;
            }
            .site-footer__bottom {
                padding: 14px max(12px, env(safe-area-inset-left, 0px)) 14px max(12px, env(safe-area-inset-right, 0px));
                font-size: 0.8rem;
            }
        }
        .site-footer__brand strong { font-size: 1.1rem; letter-spacing: 0.06em; }
        .site-footer__muted { color: #98a2b3; margin: 8px 0 0; max-width: 320px; line-height: 1.5; }
        .site-footer__heading {
            display: block;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: #98a2b3;
            margin-bottom: 12px;
            font-weight: 700;
        }
        .site-footer__links { list-style: none; margin: 0; padding: 0; }
        .site-footer__links li { margin-bottom: 8px; }
        .site-footer__links a {
            color: #e4e7ec;
            text-decoration: none;
            text-decoration-color: transparent;
            transition:
                color var(--duration-hover) var(--ease-hover),
                text-decoration-color var(--duration-hover) var(--ease-hover);
        }
        .site-footer__links a:hover {
            color: #fff;
            text-decoration: underline;
            text-decoration-color: rgba(255, 255, 255, 0.75);
        }
        .site-footer__bottom {
            border-top: 1px solid #344054;
            padding: 16px 0;
            color: #98a2b3;
            font-size: 0.85rem;
        }
        .site-footer__bottom-inner { display: flex; justify-content: center; text-align: center; }

        .photos { display: grid; grid-template-columns: repeat(auto-fill, minmax(120px, 1fr)); gap: 8px; }
        .photos img { width: 100%; height: 120px; object-fit: cover; border-radius: 8px; border: 1px solid var(--border); }
    </style>
    @stack('styles')
</head>
<body @if (request()->routeIs('catalog.show')) data-force-header-compact="1" @endif>
    <div class="cart-toast-stack" data-cart-toast-stack aria-live="polite" aria-atomic="true"></div>
    @include('partials.shop-header')
    {{-- Одразу після хедера: sticky-фільтри/сайдбар використовують --header-sticky-offset --}}
    <script>
    (function () {
        var h = document.querySelector('.site-header');
        if (!h) return;
        var compact = h.classList.contains('site-header--compact');
        var sticky = h.querySelector('.site-header__sticky');
        var el = (compact && sticky) ? sticky : h;
        var r = el.getBoundingClientRect();
        var mb = compact ? 0 : (parseFloat(getComputedStyle(h).marginBottom) || 0);
        h.style.setProperty('--site-header-compact-sticky-height', compact ? (Math.ceil(r.height) + 'px') : '0px');
        var px = Math.ceil(r.height + mb);
        if (!compact && px < 180) px = 200;
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

            panelCountEls.forEach(function (el) {
                el.textContent = String(itemsCount);
            });

            badgeEls.forEach(function (badge) {
                badge.textContent = String(itemsCount);
            });

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
                }, 260);
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
            drawer.hidden = false;
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
        }

        function closeDrawer() {
            cleanupClose();
            drawer.classList.remove('is-open');
            drawer.setAttribute('aria-hidden', 'true');
            document.body.classList.remove('cart-drawer-open');
            setExpanded(false);

            closeTimer = window.setTimeout(function () {
                drawer.hidden = true;
            }, 240);

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
        var stickyEl = header.querySelector('.site-header__sticky');
        var forceCompact = document.body && document.body.dataset.forceHeaderCompact === '1';

        /* Коротший lock, щоб header не “тягнувся” майже секунду */
        var ANIM_LOCK_MS = 420;
        var COMPACT_STICKY_FALLBACK_PX = 72;
        var animLockUntil = 0;
        var compactEnterAt = 96;
        var headerExpandTopThreshold = 0;
        var lastScrollY = window.scrollY || 0;
        var lastDir = null;

        /* Коротке вікно синхронізації sticky-offset без довгого rAF-циклу. */
        var layoutSyncUntil = 0;
        var layoutRafId = 0;
        var resizeObserverInst = null;
        var roStickyPending = false;
        var lastStickyOffsetPx = -1;
        var lastCompactStickyHeightPx = -1;
        var headerExpandPendingAtTop = false;
        var headerExpandReadyAt = 0;
        var headerExpandTouchReleased = false;
        var headerExpandWheelReleased = false;
        var headerExpandWheelReleaseTimer = 0;
        var touchStartY = null;

        function isAtPageTop() {
            return (window.scrollY || window.pageYOffset || 0) <= headerExpandTopThreshold;
        }

        function clearHeaderExpandWheelReleaseTimer() {
            if (!headerExpandWheelReleaseTimer) return;
            window.clearTimeout(headerExpandWheelReleaseTimer);
            headerExpandWheelReleaseTimer = 0;
        }

        function resetHeaderExpandAtTopState() {
            clearHeaderExpandWheelReleaseTimer();
            headerExpandPendingAtTop = false;
            headerExpandReadyAt = 0;
            headerExpandTouchReleased = false;
            headerExpandWheelReleased = false;
        }

        function armHeaderExpandAtTop() {
            if (headerExpandPendingAtTop) return;
            headerExpandPendingAtTop = true;
            headerExpandReadyAt = Date.now() + 140;
            headerExpandTouchReleased = false;
            headerExpandWheelReleased = false;
            clearHeaderExpandWheelReleaseTimer();
            headerExpandWheelReleaseTimer = window.setTimeout(function () {
                headerExpandWheelReleaseTimer = 0;
                if (!canPrepareHeaderExpandFromTop()) return;
                headerExpandWheelReleased = true;
            }, 190);
        }

        function canPrepareHeaderExpandFromTop() {
            if (forceCompact) return false;
            if (Date.now() < animLockUntil) return false;
            if (!header.classList.contains('site-header--compact')) return false;
            if (!isAtPageTop()) return false;
            if (!headerExpandPendingAtTop) return false;

            return Date.now() >= headerExpandReadyAt;
        }

        function canExpandHeaderFromTop() {
            if (!canPrepareHeaderExpandFromTop()) return false;

            return headerExpandTouchReleased || headerExpandWheelReleased;
        }

        function tryExpandHeaderFromTopIntent() {
            if (!canExpandHeaderFromTop()) return false;
            setCompact(false);
            resetHeaderExpandAtTopState();
            return true;
        }

        var headerMbCache = null;
        function headerMarginBottom() {
            if (headerMbCache === null) {
                headerMbCache = parseFloat(getComputedStyle(header).marginBottom) || 0;
            }
            return headerMbCache;
        }
        function headerOuterHeight() {
            return header.getBoundingClientRect().height + headerMarginBottom();
        }

        function setCompactStickyHeight(px) {
            var next = Math.max(0, Math.ceil(px || 0));
            if (next === lastCompactStickyHeightPx) return;
            lastCompactStickyHeightPx = next;
            header.style.setProperty('--site-header-compact-sticky-height', next + 'px');
        }

        function updateHeaderCategoriesCollapsed() {
            return;
        }

        function applyStickyOffsetFromHeader() {
            updateHeaderCategoriesCollapsed();
            var compact = header.classList.contains('site-header--compact');
            var measureEl = (compact && stickyEl) ? stickyEl : header;
            var rect = measureEl.getBoundingClientRect();
            var mb = compact ? 0 : headerMarginBottom();
            if (compact) {
                setCompactStickyHeight(rect.height);
            } else if (Date.now() >= layoutSyncUntil) {
                setCompactStickyHeight(0);
            }
            var px = Math.ceil(rect.height + mb);
            if (px > 0) {
                if (!compact && px < 180) px = 200;
                if (px === lastStickyOffsetPx) return;
                lastStickyOffsetPx = px;
                document.documentElement.style.setProperty('--header-sticky-offset', px + 'px');
            }
        }

        function scheduleStickyFromResizeOrRo() {
            if (Date.now() < layoutSyncUntil) return;
            if (roStickyPending) return;
            roStickyPending = true;
            requestAnimationFrame(function () {
                roStickyPending = false;
                if (Date.now() < layoutSyncUntil) return;
                applyStickyOffsetFromHeader();
            });
        }

        function syncStickyOffsetNow() {
            applyStickyOffsetFromHeader();
            requestAnimationFrame(applyStickyOffsetFromHeader);
        }

        function startHeaderLayoutSyncWindow(ms) {
            if (layoutRafId) {
                cancelAnimationFrame(layoutRafId);
                layoutRafId = 0;
            }
            if (resizeObserverInst) {
                try {
                    resizeObserverInst.unobserve(header);
                } catch (e) { /* */ }
            }
            layoutSyncUntil = Date.now() + ms;
            layoutRafId = requestAnimationFrame(function () {
                layoutRafId = 0;
                applyStickyOffsetFromHeader();
                requestAnimationFrame(function () {
                    applyStickyOffsetFromHeader();
                    if (resizeObserverInst) {
                        try {
                            resizeObserverInst.observe(header);
                        } catch (e) { /* already observing */ }
                    }
                });
            });
        }

        function setCompact(want) {
            var has = header.classList.contains('site-header--compact');
            if (want === has) return;

            animLockUntil = Date.now() + ANIM_LOCK_MS;
            resetHeaderExpandAtTopState();

            if (want) {
                /* Не беремо висоту розгорнутого sticky-блоку: це і є головне джерело CLS. */
                setCompactStickyHeight(
                    lastCompactStickyHeightPx > 0 ? lastCompactStickyHeightPx : COMPACT_STICKY_FALLBACK_PX
                );
            }

            header.classList.toggle('site-header--compact', want);

            startHeaderLayoutSyncWindow(260);
        }

        function onScrollHeaderCompact() {
            if (forceCompact) {
                return;
            }
            var now = Date.now();
            var y = window.scrollY || 0;
            if (now < animLockUntil) {
                lastScrollY = y;
                return;
            }

            var down = y > lastScrollY + 2;
            var up = y < lastScrollY - 2;
            if (down) lastDir = 'down';
            else if (up) lastDir = 'up';

            var compact = header.classList.contains('site-header--compact');
            if (!compact && y >= compactEnterAt && lastDir === 'down') {
                setCompact(true);
            } else if (compact) {
                if (isAtPageTop()) {
                    armHeaderExpandAtTop();
                } else if (down) {
                    resetHeaderExpandAtTopState();
                }
            }
            lastScrollY = y;
        }

        if (forceCompact) {
            header.classList.add('site-header--compact');
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
        function onScrollHeaderAll() {
            if (!forceCompact) {
                onScrollHeaderCompact();
            }
            if (Date.now() < layoutSyncUntil) return;
            applyStickyOffsetFromHeader();
        }
        window.addEventListener('scroll', onScrollHeaderAll, { passive: true });
        window.addEventListener('wheel', function (event) {
            if (event.deltaY >= -2) return;
            tryExpandHeaderFromTopIntent();
        }, { passive: true });
        window.addEventListener('touchstart', function (event) {
            if (!event.touches || event.touches.length !== 1) return;
            touchStartY = event.touches[0].clientY;
        }, { passive: true });
        window.addEventListener('touchmove', function (event) {
            if (!event.touches || event.touches.length !== 1) return;
            var currentY = event.touches[0].clientY;
            if (touchStartY === null) {
                touchStartY = currentY;
                return;
            }
            var movedDown = currentY > touchStartY + 12;
            if (movedDown && headerExpandTouchReleased) {
                tryExpandHeaderFromTopIntent();
            }
            touchStartY = currentY;
        }, { passive: true });
        window.addEventListener('touchend', function () {
            touchStartY = null;
            if (canPrepareHeaderExpandFromTop()) {
                headerExpandTouchReleased = true;
            }
        }, { passive: true });
    })();
    </script>
</body>
</html>
