@extends('account.layout')

@section('title', __('shop.account_page_title_orders'))

@section('account_content')
    @php
        $payFilter = $ordersPaymentFilter ?? 'all';
        $tabs = [
            'all' => __('shop.account_orders_tab_all'),
            'deferred_pending' => __('shop.account_orders_tab_deferred_pending'),
            'deferred_ready' => __('shop.account_orders_tab_deferred_ready'),
        ];
    @endphp
    <section class="account-card account-orders-index">
        <div class="account-orders-index__head">
            <h1 class="account-orders-index__title">{{ __('shop.account_orders_h1') }}</h1>
            <nav class="account-orders-tabs" aria-label="{{ __('shop.account_orders_tabs_aria') }}">
                @foreach ($tabs as $key => $label)
                    @php
                        $isActive = $payFilter === $key;
                        $ariaCurrent = $isActive ? 'page' : 'false';
                    @endphp
                    <a
                        href="{{ route('account.orders.index', ['payment' => $key]) }}"
                        class="account-orders-tabs__link {{ $isActive ? 'is-active' : '' }}"
                        aria-current="{{ $ariaCurrent }}"
                    >{{ $label }}</a>
                @endforeach
            </nav>
        </div>
        <div class="account-orders-list">
            @forelse ($orders as $order)
                @php
                    $canPay = $order->canPayDeferredLiqPay() && ($onlinePaymentConfigured ?? false);
                    $statusTone = match ((string) $order->status) {
                        \App\Models\Order::STATUS_PAID,
                        \App\Models\Order::STATUS_PROCESSING,
                        \App\Models\Order::STATUS_SHIPPED,
                        \App\Models\Order::STATUS_COMPLETED => 'success',
                        \App\Models\Order::STATUS_CANCELLED => 'danger',
                        \App\Models\Order::STATUS_NEW => 'info',
                        default => 'pending',
                    };
                    $paymentTone = match ((string) $order->payment_status) {
                        'paid', 'partial' => 'success',
                        'failed' => 'danger',
                        default => 'pending',
                    };
                @endphp

                <article class="account-order-card {{ $canPay ? 'account-order-card--payable' : '' }}">
                    <div class="account-order-card__main">
                        <a class="account-order-card__number" href="{{ route('account.orders.show', $order) }}">{{ $order->number }}</a>
                        <div class="account-order-card__date">{{ $order->placed_at?->format('d.m.Y H:i') ?? __('shop.account_order_date_missing') }}</div>
                    </div>

                    <div class="account-order-card__amount">
                        <span class="account-order-card__label">{{ __('shop.account_order_card_total') }}</span>
                        <span class="account-order-card__sum">{{ number_format((float) $order->total, 2, ',', ' ') }} UAH</span>
                    </div>

                    <div class="account-order-card__state">
                        <span class="account-order-card__label">{{ __('shop.account_order_card_state') }}</span>
                        <div class="account-order-card__badges">
                            <span class="account-order-badge account-order-badge--{{ $statusTone }}">{{ $order->statusLabel() }}</span>
                            <span class="account-order-badge account-order-badge--{{ $paymentTone }}">{{ $order->paymentStatusLabel() }}</span>
                        </div>
                        @php
                            $paymentNote = $order->accountDeferredPaymentLabel();
                        @endphp
                        @if ($paymentNote)
                            <p class="account-order-card__payment-note">{{ $paymentNote }}</p>
                        @endif
                    </div>

                    <div class="account-order-card__actions">
                        @if ($canPay)
                            <a class="btn btn-buy" href="{{ route('account.orders.payment', $order) }}">{{ __('shop.account_order_pay') }}</a>
                        @endif
                        <a class="account-order-card__open" href="{{ route('account.orders.show', $order) }}">{{ __('shop.account_order_open') }}</a>
                    </div>
                </article>
            @empty
                <p class="account-empty" style="margin:0;">{{ __('shop.account_orders_empty') }}</p>
            @endforelse
        </div>

        @if ($orders->hasPages())
            <div style="margin-top:1.1rem;">{{ $orders->links() }}</div>
        @endif
    </section>
@endsection
