<nav class="account-nav" aria-label="{{ __('shop.account_nav_aria') }}">
    <div class="account-nav__brand">{{ __('shop.account_nav_brand') }}</div>
    <div class="account-nav__links">
        <a
            class="account-nav__link {{ request()->routeIs('account.index') ? 'is-active' : '' }}"
            href="{{ route('account.index') }}"
        >
            <span class="account-nav__dot" aria-hidden="true"></span>
            {{ __('shop.account_nav_overview') }}
        </a>
        <a
            class="account-nav__link {{ request()->routeIs('account.orders.*') ? 'is-active' : '' }}"
            href="{{ route('account.orders.index') }}"
        >
            <span class="account-nav__dot" aria-hidden="true"></span>
            {{ __('shop.account_nav_orders') }}
        </a>
        <a
            class="account-nav__link {{ request()->routeIs('account.favorites') ? 'is-active' : '' }}"
            href="{{ route('account.favorites') }}"
        >
            <span class="account-nav__dot" aria-hidden="true"></span>
            {{ __('shop.account_nav_favorites') }}
        </a>
    </div>
    <div class="account-nav__logout">
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="btn secondary" style="width:100%;">{{ __('shop.account_nav_logout') }}</button>
        </form>
    </div>
</nav>
