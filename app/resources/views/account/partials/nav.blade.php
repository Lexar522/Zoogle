@php
    $navUser = auth()->user();
    $navInitials = '';
    if ($navUser) {
        foreach (array_slice(preg_split('/\s+/u', trim((string) $navUser->name), -1, PREG_SPLIT_NO_EMPTY) ?: [], 0, 2) as $navWord) {
            $navInitials .= mb_strtoupper(mb_substr($navWord, 0, 1));
        }
    }
    $navItems = array_values(array_filter([
        $navUser ? ['route' => 'account.index', 'active' => 'account.index', 'label' => __('shop.account_nav_overview'), 'tone' => 'blue', 'icon' => 'M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm0 2c-4.4 0-8 2.2-8 5v1h16v-1c0-2.8-3.6-5-8-5Z'] : null,
        ['route' => 'account.orders.index', 'active' => 'account.orders.*', 'label' => __('shop.account_nav_orders'), 'tone' => 'orange', 'icon' => 'M6 7V6a6 6 0 0 1 12 0v1h3l-1.5 14h-15L3 7h3Zm2 0h8V6a4 4 0 0 0-8 0v1Z'],
        $navUser ? ['route' => 'account.favorites', 'active' => 'account.favorites', 'label' => __('shop.account_nav_favorites'), 'tone' => 'pink', 'icon' => 'M12 21s-7.5-4.6-9.5-9.3C1.1 8.4 3.3 5 6.8 5c2 0 3.6 1.1 4.4 2.6l.8 1.3.8-1.3C13.6 6.1 15.2 5 17.2 5c3.5 0 5.7 3.4 4.3 6.7C19.5 16.4 12 21 12 21Z'] : null,
    ]));
@endphp
<nav class="account-nav" aria-label="{{ __('shop.account_nav_aria') }}">
    @if ($navUser)
        <a class="account-nav__me" href="{{ route('account.index') }}">
            @if (filled($navUser->avatar))
                <img class="account-nav__avatar" src="{{ $navUser->avatar }}" alt="" width="44" height="44">
            @else
                <span class="account-nav__avatar account-nav__avatar--initials" aria-hidden="true">{{ $navInitials ?: '?' }}</span>
            @endif
            <span class="account-nav__me-text">
                <span class="account-nav__me-name">{{ $navUser->name }}</span>
                @if (filled($navUser->email))
                    <span class="account-nav__me-email">{{ $navUser->email }}</span>
                @endif
            </span>
        </a>
    @else
        <div class="account-nav__brand">{{ __('shop.account_nav_brand') }}</div>
    @endif

    <div class="account-nav__links">
        @foreach ($navItems as $navItem)
            @php($isActive = request()->routeIs($navItem['active']))
            <a class="account-nav__link {{ $isActive ? 'is-active' : '' }}" href="{{ route($navItem['route']) }}" @if ($isActive) aria-current="page" @endif>
                <span class="account-nav__icon account-nav__icon--{{ $navItem['tone'] }}" aria-hidden="true">
                    <svg viewBox="0 0 24 24" width="16" height="16" focusable="false"><path fill="currentColor" d="{{ $navItem['icon'] }}"/></svg>
                </span>
                <span class="account-nav__label">{{ $navItem['label'] }}</span>
                <svg class="account-nav__chevron" viewBox="0 0 24 24" width="14" height="14" aria-hidden="true" focusable="false"><path fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" d="m9 6 6 6-6 6"/></svg>
            </a>
        @endforeach
    </div>

    <div class="account-nav__logout">
        @auth
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="account-nav__logout-btn">{{ __('shop.account_nav_logout') }}</button>
            </form>
        @else
            <form method="POST" action="{{ route('account.orders.guest.leave') }}">
                @csrf
                <button type="submit" class="account-nav__logout-btn">{{ __('shop.account_guest_leave') }}</button>
            </form>
        @endauth
    </div>
</nav>
