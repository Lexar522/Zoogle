@php
    $mobileMenu = \App\Models\ShopMobileMenuSetting::resolved();
    $menuUser = auth()->user();
    $menuLink = function (string $url, string $label, string $color): string {
        $current = rtrim(url()->current(), '/') === rtrim(strtok($url, '?'), '/');

        return '<a class="mobile-menu__link'.($color !== 'default' ? ' mobile-menu__link--'.$color : '').'" href="'.e($url).'"'.($current ? ' aria-current="page"' : '').'>'.e($label).'</a>';
    };
@endphp
<div class="mobile-menu" data-mobile-menu hidden>
    <div class="mobile-menu__backdrop" data-mobile-menu-close></div>
    <nav id="mobile-menu" class="mobile-menu__panel" aria-label="{{ __('shop.mobile_menu_title') }}" tabindex="-1">
        <div class="mobile-menu__head">
            <span class="mobile-menu__title">{{ __('shop.mobile_menu_title') }}</span>
            <button type="button" class="mobile-menu__close" aria-label="{{ __('shop.mobile_menu_close') }}" data-mobile-menu-close>
                <svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true" focusable="false"><path fill="currentColor" d="M18.3 5.71 12 12.01l-6.3-6.3-1.41 1.41 6.3 6.3-6.3 6.3 1.41 1.41 6.3-6.3 6.3 6.3 1.41-1.41-6.3-6.3 6.3-6.3z"/></svg>
            </button>
        </div>

        @if ($menuUser)
            <a class="mobile-menu__user" href="{{ route('account.index') }}">
                <span class="mobile-menu__user-avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr(trim((string) $menuUser->name) ?: '?', 0, 1)) }}</span>
                <span class="mobile-menu__user-text">
                    <span class="mobile-menu__user-name">{{ $menuUser->name }}</span>
                    <span class="mobile-menu__user-hint">{{ __('shop.mobile_menu_account') }}</span>
                </span>
            </a>
        @endif

        @if ($mobileMenu['main'] !== [])
            <ul class="mobile-menu__list" role="list">
                @foreach ($mobileMenu['main'] as $menuItem)
                    <li>{!! $menuLink($menuItem['url'], $menuItem['label'], $menuItem['color']) !!}</li>
                @endforeach
            </ul>
        @endif

        @if ($mobileMenu['info'] !== [])
            <p class="mobile-menu__section">{{ __('shop.mobile_menu_info') }}</p>
            <ul class="mobile-menu__list mobile-menu__list--secondary" role="list">
                @foreach ($mobileMenu['info'] as $menuItem)
                    <li>{!! $menuLink($menuItem['url'], $menuItem['label'], $menuItem['color']) !!}</li>
                @endforeach
            </ul>
        @endif

        @if ($mobileMenu['show_contacts'] && ! empty($hasShopHeaderContacts))
            <p class="mobile-menu__section">{{ __('shop.footer_contacts') }}</p>
            <div class="mobile-menu__contacts">
                @include('partials.shop-header-contacts', ['contactIconsColored' => true])
            </div>
        @endif

        @guest
            @if ($mobileMenu['show_login'] && Route::has('login'))
                <a class="btn btn-buy mobile-menu__login" href="{{ route('login') }}">{{ __('shop.mobile_menu_login') }}</a>
            @endif
        @endguest
    </nav>
</div>
