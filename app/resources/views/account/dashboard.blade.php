@extends('account.layout')

@section('title', __('shop.account_page_title_dashboard'))

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
                <h1>{{ __('shop.account_dashboard_welcome', ['name' => $user->checkoutDisplayName()]) }}</h1>
                @if ($user->email)
                    <p>{{ $user->email }}</p>
                @endif
            </div>
        </div>
    </header>

    @if (session('status') === 'profile-saved')
        <p class="alert success" style="margin:0 0 1rem;font-size:0.95rem;" role="status">{{ __('shop.account_profile_saved_flash') }}</p>
    @endif

    <section class="account-card" id="profile">
        <div class="account-card__head">
            <h2>{{ __('shop.account_profile_section_title') }}</h2>
        </div>
        <p class="muted" style="margin:0 0 1rem;font-size:0.92rem;line-height:1.5;">
            {{ __('shop.account_profile_section_lead') }}
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
                    <label for="profile_first_name">{{ __('shop.account_profile_first_name') }}</label>
                    <input id="profile_first_name" name="first_name" type="text" value="{{ old('first_name', $user->first_name) }}" maxlength="120" autocomplete="given-name">
                </div>
                <div>
                    <label for="profile_last_name">{{ __('shop.account_profile_last_name') }}</label>
                    <input id="profile_last_name" name="last_name" type="text" value="{{ old('last_name', $user->last_name) }}" maxlength="120" autocomplete="family-name">
                </div>
            </div>
            <div style="margin-bottom:1rem;">
                <label for="profile_phone">{{ __('shop.account_profile_phone') }}</label>
                <input id="profile_phone" name="phone" type="tel" value="{{ old('phone', $user->phone) }}" maxlength="50" placeholder="+380971234567" autocomplete="tel">
            </div>
            <button type="submit" class="btn secondary">{{ __('shop.account_profile_save') }}</button>
        </form>
    </section>

    <div class="account-tiles">
        <a class="account-tile account-tile--orders" href="{{ route('account.orders.index') }}">
            <span class="account-tile__label">{{ __('shop.account_tile_orders') }}</span>
            <span class="account-tile__value">{{ $ordersTotal }}</span>
            <span class="account-tile__hint">{{ __('shop.account_tile_orders_hint') }}</span>
        </a>
        <a class="account-tile account-tile--fav" href="{{ route('account.favorites') }}">
            <span class="account-tile__label">{{ __('shop.account_tile_favorites') }}</span>
            <span class="account-tile__value">{{ $favoritesCount }}</span>
            <span class="account-tile__hint">{{ __('shop.account_tile_favorites_hint') }}</span>
        </a>
    </div>

    <section class="account-card">
        <div class="account-card__head">
            <h2>{{ __('shop.account_recent_orders_title') }}</h2>
            @unless ($recentOrders->isEmpty())
                <a href="{{ route('account.orders.index') }}">{{ __('shop.account_recent_orders_all') }}</a>
            @endunless
        </div>
        @if ($recentOrders->isEmpty())
            <p class="account-empty" style="margin:0;">{{ __('shop.account_recent_orders_empty') }}</p>
        @else
            <ul class="account-order-list">
                @foreach ($recentOrders as $order)
                    <li>
                        <a class="account-order-row" href="{{ route('account.orders.show', $order) }}">
                            <div>
                                <div class="account-order-row__num">{{ $order->number }}</div>
                                <div class="account-order-row__meta">
                                    {{ $order->placed_at?->format('d.m.Y H:i') ?? __('shop.account_order_row_date_dash') }}
                                </div>
                            </div>
                            <div class="account-order-row__side">
                                <div>
                                    <span class="account-order-row__sum">{{ number_format((float) $order->total, 2) }} UAH</span>
                                    <span class="account-order-row__chev" aria-hidden="true"> ›</span>
                                </div>
                                <span class="account-order-row__status">{{ $order->statusLabel() }}</span>
                            </div>
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
@endsection
