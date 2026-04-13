<nav class="account-nav" aria-label="Кабінет покупця">
    <div class="account-nav__brand">Кабінет</div>
    <div class="account-nav__links">
        <a
            class="account-nav__link {{ request()->routeIs('account.index') ? 'is-active' : '' }}"
            href="{{ route('account.index') }}"
        >
            <span class="account-nav__dot" aria-hidden="true"></span>
            Огляд
        </a>
        <a
            class="account-nav__link {{ request()->routeIs('account.orders.*') ? 'is-active' : '' }}"
            href="{{ route('account.orders.index') }}"
        >
            <span class="account-nav__dot" aria-hidden="true"></span>
            Замовлення
        </a>
        <a
            class="account-nav__link {{ request()->routeIs('account.favorites') ? 'is-active' : '' }}"
            href="{{ route('account.favorites') }}"
        >
            <span class="account-nav__dot" aria-hidden="true"></span>
            Обране
        </a>
    </div>
    <div class="account-nav__logout">
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="btn secondary" style="width:100%;">Вийти</button>
        </form>
    </div>
</nav>
