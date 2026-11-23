{{-- Телефон: вхід (лише для гостя), кошик і бургер-меню справа. На комп'ютері приховано. --}}
<div class="site-header__mobile-actions" data-mobile-actions>
    @guest
        @if (app(\App\Support\GuestOrderAccess::class)->hasOrders())
            <a href="{{ route('account.orders.index') }}" class="site-header__mobile-icon" aria-label="{{ __('shop.account_nav_orders') }}">@include('partials.shop-header-account-icon')</a>
        @elseif (Route::has('login'))
            <a href="{{ route('login') }}" class="site-header__mobile-icon" aria-label="{{ __('shop.mobile_menu_login') }}">@include('partials.shop-header-account-icon')</a>
        @endif
    @endguest
    <button type="button" class="site-header__mobile-cart @if ($cartItemsCount > 0) cart-pill--has-items @endif" aria-label="{{ __('shop.cart_open') }}" aria-controls="cart-drawer" aria-expanded="false" data-cart-open>
        <svg class="site-header__mobile-cart-icon" viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" focusable="false"><path fill="currentColor" d="M7 18c-1.1 0-1.99.9-1.99 2S5.9 22 7 22s2-.9 2-2-.9-2-2-2zM1 2v2h2l3.6 7.59-1.35 2.45c-.16.28-.25.61-.25.96 0 1.1.9 2 2 2h12v-2H7.42c-.14 0-.25-.11-.25-.25l.03-.12.9-1.63h7.45c.75 0 1.41-.41 1.75-1.03l3.58-6.49c.08-.14.12-.31.12-.48 0-.55-.45-1-1-1H5.21l-.94-2H1zm16 16c-1.1 0-1.99.9-1.99 2s.89 2 1.99 2 2-.9 2-2-.9-2-2-2z"/></svg>
        <span class="site-header__mobile-cart-count" data-cart-badge>{{ $cartItemsCount }}</span>
    </button>
    <button type="button" class="site-header__burger" aria-label="{{ __('shop.mobile_menu_open') }}" aria-controls="mobile-menu" aria-expanded="false" data-mobile-menu-open>
        <span class="site-header__burger-lines" aria-hidden="true"><span></span><span></span><span></span></span>
    </button>
</div>
