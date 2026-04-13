@extends('account.layout')

@section('title', 'Мій акаунт — ZOOGLE')

@section('account_content')
    @php
        $nameWords = preg_split('/\s+/u', trim((string) $user->name), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $initials = '';
        foreach (array_slice($nameWords, 0, 2) as $w) {
            $initials .= mb_strtoupper(mb_substr($w, 0, 1));
        }
        if ($initials === '') {
            $initials = '?';
        }
    @endphp

    <header class="account-hero">
        <div class="account-hero__inner">
            @if (filled($user->avatar))
                <img class="account-hero__avatar" src="{{ $user->avatar }}" alt="" width="68" height="68">
            @else
                <div class="account-hero__avatar account-hero__avatar--initials" aria-hidden="true">{{ $initials }}</div>
            @endif
            <div class="account-hero__text">
                <h1>Вітаємо, {{ $user->name }}</h1>
                @if ($user->email)
                    <p>{{ $user->email }}</p>
                @endif
            </div>
        </div>
    </header>

    <div class="account-tiles">
        <a class="account-tile account-tile--orders" href="{{ route('account.orders.index') }}">
            <span class="account-tile__label">Замовлення</span>
            <span class="account-tile__value">{{ $ordersTotal }}</span>
            <span class="account-tile__hint">Переглянути історію покупок</span>
        </a>
        <a class="account-tile account-tile--fav" href="{{ route('account.favorites') }}">
            <span class="account-tile__label">Обране</span>
            <span class="account-tile__value">{{ $favoritesCount }}</span>
            <span class="account-tile__hint">Товари з сердечком у каталозі</span>
        </a>
    </div>

    <section class="account-card">
        <div class="account-card__head">
            <h2>Останні замовлення</h2>
            @unless ($recentOrders->isEmpty())
                <a href="{{ route('account.orders.index') }}">Усі замовлення</a>
            @endunless
        </div>
        @if ($recentOrders->isEmpty())
            <p class="account-empty" style="margin:0;">Ще немає замовлень у цьому акаунті.</p>
        @else
            <ul class="account-order-list">
                @foreach ($recentOrders as $order)
                    <li>
                        <a class="account-order-row" href="{{ route('account.orders.show', $order) }}">
                            <div>
                                <div class="account-order-row__num">{{ $order->number }}</div>
                                <div class="account-order-row__meta">
                                    {{ $order->placed_at?->format('d.m.Y H:i') ?? '—' }}
                                </div>
                            </div>
                            <div class="account-order-row__side">
                                <div>
                                    <span class="account-order-row__sum">{{ number_format((float) $order->total, 2) }} UAH</span>
                                    <span class="account-order-row__chev" aria-hidden="true"> ›</span>
                                </div>
                                <span class="account-order-row__status">{{ $order->status }}</span>
                            </div>
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
@endsection
