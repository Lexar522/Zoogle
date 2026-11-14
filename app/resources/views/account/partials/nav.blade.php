<nav class="account-nav" aria-label="{{ __('shop.account_nav_aria') }}">
    <div class="account-nav__brand">{{ __('shop.account_nav_brand') }}</div>
    <div class="account-nav__links">
        @auth
            <a
                class="account-nav__link {{ request()->routeIs('account.index') ? 'is-active' : '' }}"
                href="{{ route('account.index') }}"
            >
                <span class="account-nav__dot" aria-hidden="true"></span>
                {{ __('shop.account_nav_overview') }}
            </a>
        @endauth
        <a
            class="account-nav__link {{ request()->routeIs('account.orders.*') ? 'is-active' : '' }}"
            href="{{ route('account.orders.index') }}"
        >
            <span class="account-nav__dot" aria-hidden="true"></span>
            {{ __('shop.account_nav_orders') }}
        </a>
        @auth
            <a
                class="account-nav__link {{ request()->routeIs('account.favorites') ? 'is-active' : '' }}"
                href="{{ route('account.favorites') }}"
            >
                <span class="account-nav__dot" aria-hidden="true"></span>
                {{ __('shop.account_nav_favorites') }}
            </a>
        @endauth
    </div>
    <div class="account-nav__logout">
        @auth
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="btn secondary" style="width:100%;">{{ __('shop.account_nav_logout') }}</button>
            </form>
        @else
            <form method="POST" action="{{ route('account.orders.guest.leave') }}">
                @csrf
                <button type="submit" class="btn secondary" style="width:100%;">{{ __('shop.account_guest_leave') }}</button>
            </form>
        @endauth
    </div>
</nav>
