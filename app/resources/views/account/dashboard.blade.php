@extends('account.layout')

@section('title', 'Мій акаунт — ZOOGLE')

@section('account_content')
    @php
        $first = trim((string) ($user->first_name ?? ''));
        $last = trim((string) ($user->last_name ?? ''));
        $initials = '';
        if ($first !== '') {
            $initials .= mb_strtoupper(mb_substr($first, 0, 1));
        }
        if ($last !== '') {
            $initials .= mb_strtoupper(mb_substr($last, 0, 1));
        }
        if ($initials === '') {
            $nameWords = preg_split('/\s+/u', trim((string) $user->name), -1, PREG_SPLIT_NO_EMPTY) ?: [];
            foreach (array_slice($nameWords, 0, 2) as $w) {
                $initials .= mb_strtoupper(mb_substr($w, 0, 1));
            }
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
                <h1>Вітаємо, {{ $user->checkoutDisplayName() }}</h1>
                @if ($user->email)
                    <p>{{ $user->email }}</p>
                @endif
            </div>
        </div>
    </header>

    @if (session('status') === 'profile-saved')
        <p class="alert success" style="margin:0 0 1rem;font-size:0.95rem;" role="status">Дані збережено. На сторінці оформлення замовлення вони підставляться автоматично.</p>
    @endif

    <section class="account-card" id="profile">
        <div class="account-card__head">
            <h2>Контактні дані для замовлень</h2>
        </div>
        <p class="muted" style="margin:0 0 1rem;font-size:0.92rem;line-height:1.5;">
            Ім’я, прізвище та телефон зʼявляться у формі замовлення на чекауті (якщо ви увійшли в акаунт).
        </p>
        <form method="POST" action="{{ route('account.profile.update') }}" class="account-profile-form">
            @csrf
            @method('PATCH')
            <div class="row-2" style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:0.75rem 1rem;margin-bottom:0.75rem;">
                @if ($errors->any())
                    <div style="grid-column:1/-1;">
                        @foreach ($errors->all() as $err)
                            <p class="alert error" style="margin:0 0 0.5rem;">{{ $err }}</p>
                        @endforeach
                    </div>
                @endif
                <div>
                    <label for="profile_first_name">Ім’я</label>
                    <input id="profile_first_name" name="first_name" type="text" value="{{ old('first_name', $user->first_name) }}" maxlength="120" autocomplete="given-name">
                </div>
                <div>
                    <label for="profile_last_name">Прізвище</label>
                    <input id="profile_last_name" name="last_name" type="text" value="{{ old('last_name', $user->last_name) }}" maxlength="120" autocomplete="family-name">
                </div>
            </div>
            <div style="margin-bottom:1rem;">
                <label for="profile_phone">Телефон</label>
                <input id="profile_phone" name="phone" type="tel" value="{{ old('phone', $user->phone) }}" maxlength="50" placeholder="+380971234567" autocomplete="tel">
            </div>
            <button type="submit" class="btn secondary">Зберегти</button>
        </form>
    </section>

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
