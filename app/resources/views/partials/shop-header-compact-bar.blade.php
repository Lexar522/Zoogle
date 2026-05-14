{{-- Окрема fixed-смуга; з’являється після прокрутки великого блоку (не морф того ж DOM) --}}
<div class="site-header__compact-bar" data-site-header-compact-bar aria-hidden="true">
    <div class="container">
        <div class="site-header__row">
            <div class="site-header__zone site-header__zone--left">
                @if (! empty($hasShopHeaderContacts))
                    <div class="site-header__contacts-google">
                        @include('partials.shop-header-contacts')
                    </div>
                @endif
            </div>

            <div class="site-header__zone site-header__zone--center">
                <a href="{{ route('home') }}" class="site-logo" aria-label="ZOOGLE — на головну">
                    <img
                        src="{{ asset('images/zoogle-logo-new.png') }}"
                        alt=""
                        class="site-logo__img"
                        width="1277"
                        height="320"
                        sizes="(max-width: 640px) min(70vw, 506px), (max-width: 900px) 420px, 506px"
                        decoding="async"
                        fetchpriority="low"
                    >
                    <span class="visually-hidden">ZOOGLE</span>
                </a>
                <form method="GET" action="{{ route('catalog.index') }}" class="site-header__search site-header__search--center catalog-search__form catalog-search__form--compact">
                    @if (request()->filled('category'))
                        <input type="hidden" name="category" value="{{ request('category') }}">
                    @endif
                    @if (request()->boolean('on_sale'))
                        <input type="hidden" name="on_sale" value="1">
                    @endif
                    @if (request()->routeIs('catalog.index') && request()->filled('sort'))
                        <input type="hidden" name="sort" value="{{ request('sort') }}">
                    @endif
                    @if (request()->routeIs('catalog.index') && request()->filled('per_page'))
                        <input type="hidden" name="per_page" value="{{ request('per_page') }}">
                    @endif
                    <div class="searchbar">
                        <div class="searchbar-wrapper">
                            <div class="searchbar-left">
                                <div class="search-icon-wrapper">
                                    <span class="search-icon searchbar-icon" aria-hidden="true">
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" focusable="false">
                                            <path d="M15.5 14h-.79l-.28-.27A6.471 6.471 0 0 0 16 9.5 6.5 6.5 0 1 0 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"></path>
                                        </svg>
                                    </span>
                                </div>
                            </div>

                            <div class="searchbar-center">
                                <div class="searchbar-input-spacer" aria-hidden="true"></div>
                                <input
                                    type="search"
                                    class="searchbar-input"
                                    maxlength="2048"
                                    name="q"
                                    id="header-q-compact"
                                    value="{{ request('q', '') }}"
                                    autocapitalize="off"
                                    autocomplete="off"
                                    placeholder="Пошук товарів"
                                    aria-label="Пошук по сайту"
                                >
                            </div>

                            <div class="searchbar-right">
                                <button type="submit" class="site-header__search-btn voice-search" aria-label="Шукати">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" focusable="false" aria-hidden="true">
                                        <path fill="#4285f4" d="m12 15c1.66 0 3-1.31 3-2.97v-7.02c0-1.66-1.34-3.01-3-3.01s-3 1.34-3 3.01v7.02c0 1.66 1.34 2.97 3 2.97z"></path>
                                        <path fill="#34a853" d="m11 18.08h2v3.92h-2z"></path>
                                        <path fill="#fbbc05" d="m7.05 16.87c-1.27-1.33-2.05-2.83-2.05-4.87h2c0 1.45 0.56 2.42 1.47 3.38v0.32l-1.15 1.18z"></path>
                                        <path fill="#ea4335" d="m12 16.93a4.97 5.25 0 0 1 -3.54 -1.55l-1.41 1.49c1.26 1.34 3.02 2.13 4.95 2.13 3.87 0 6.99-2.92 6.99-7h-1.99c0 2.92-2.24 4.93-5 4.93z"></path>
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>

            <div class="site-header__zone site-header__zone--right site-header__top-actions">
                <button type="button" class="site-header__lang">UA</button>
                @auth
                    @php
                        $headerUser = Auth::user();
                        $headerNameWords = preg_split('/\s+/u', trim((string) $headerUser->name), -1, PREG_SPLIT_NO_EMPTY) ?: [];
                        $headerInitials = '';
                        foreach (array_slice($headerNameWords, 0, 2) as $hw) {
                            $headerInitials .= mb_strtoupper(mb_substr($hw, 0, 1));
                        }
                        if ($headerInitials === '') {
                            $headerInitials = '?';
                        }
                    @endphp
                    <a
                        href="{{ route('account.index') }}"
                        class="site-header__user-chip"
                        aria-label="Мій акаунт, {{ $headerUser->name }}"
                    >
                        @if (filled($headerUser->avatar))
                            <img
                                class="site-header__user-avatar site-header__user-avatar--img"
                                src="{{ $headerUser->avatar }}"
                                alt=""
                                width="40"
                                height="40"
                                loading="lazy"
                                decoding="async"
                            >
                        @else
                            <span class="site-header__user-avatar site-header__user-avatar--initials" aria-hidden="true">{{ $headerInitials }}</span>
                        @endif
                        <span class="site-header__user-text">
                            <span class="site-header__user-greeting">Вітаємо</span>
                            <span class="site-header__user-name">{{ $headerUser->name }}</span>
                        </span>
                    </a>
                @elseif (Route::has('login'))
                    <a href="{{ route('login') }}" class="site-header__account" aria-label="Увійти через Google">👤</a>
                @else
                    <button type="button" class="site-header__account" aria-label="Акаунт">👤</button>
                @endif
                <button
                    type="button"
                    class="cart-pill site-nav__link site-nav__link--cart @if ($cartItemsCount > 0) cart-pill--has-items @endif"
                    aria-label="Відкрити кошик"
                    aria-controls="cart-drawer"
                    aria-expanded="false"
                    data-cart-open
                >
                    <span class="cart-pill__left" aria-hidden="true">
                        <svg
                            class="cart-pill__icon"
                            viewBox="0 0 24 24"
                            xmlns="http://www.w3.org/2000/svg"
                            fill-rule="nonzero"
                            focusable="false"
                            aria-hidden="true"
                        >
                            <path d="M7 18c-1.1 0-1.99.9-1.99 2S5.9 22 7 22s2-.9 2-2-.9-2-2-2zM1 2v2h2l3.6 7.59-1.35 2.45c-.16.28-.25.61-.25.96 0 1.1.9 2 2 2h12v-2H7.42c-.14 0-.25-.11-.25-.25l.03-.12.9-1.63h7.45c.75 0 1.41-.41 1.75-1.03l3.58-6.49c.08-.14.12-.31.12-.48 0-.55-.45-1-1-1H5.21l-.94-2H1zm16 16c-1.1 0-1.99.9-1.99 2s.89 2 1.99 2 2-.9 2-2-.9-2-2-2z"></path>
                        </svg>
                        <span class="cart-pill__label">Кошик</span>
                    </span>
                    <span class="cart-pill__count">
                        <span class="cart-pill__count-num" data-cart-badge>{{ $cartItemsCount }}</span>
                    </span>
                </button>
            </div>
        </div>
    </div>
</div>
