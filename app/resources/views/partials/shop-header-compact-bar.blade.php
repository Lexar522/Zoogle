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
                <a href="{{ route('home') }}" class="site-logo" aria-label="{{ __('shop.logo_aria') }}">
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
            </div>

            <div class="site-header__zone site-header__zone--right site-header__top-actions">
                @include('partials.language-switcher', ['langSwitcherSuffix' => '-compact'])
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
                        aria-label="{{ __('shop.account_aria', ['name' => $headerUser->name]) }}"
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
                            <span class="site-header__user-greeting">{{ __('shop.account_welcome') }}</span>
                            <span class="site-header__user-name">{{ $headerUser->name }}</span>
                        </span>
                    </a>
                @elseif (Route::has('login'))
                    <a href="{{ route('login') }}" class="site-header__account" aria-label="{{ __('shop.account_google') }}">👤</a>
                @else
                    <button type="button" class="site-header__account" aria-label="{{ __('shop.account_generic') }}">👤</button>
                @endif
                <button
                    type="button"
                    class="cart-pill site-nav__link site-nav__link--cart @if ($cartItemsCount > 0) cart-pill--has-items @endif"
                    aria-label="{{ __('shop.cart_open') }}"
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
                        <span class="cart-pill__label">{{ __('shop.cart_label') }}</span>
                    </span>
                    <span class="cart-pill__count">
                        <span class="cart-pill__count-num" data-cart-badge>{{ $cartItemsCount }}</span>
                    </span>
                </button>
            </div>
        </div>
    </div>
</div>
