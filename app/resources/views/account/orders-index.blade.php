@extends('account.layout')

@section('title', 'Мої замовлення — ZOOGLE')

@section('account_content')
    @php
        $payFilter = $ordersPaymentFilter ?? 'all';
        $tabs = [
            'all' => 'Усі',
            'deferred_pending' => 'Очікують дозволу',
            'deferred_ready' => 'Можна оплатити',
        ];
    @endphp
    <section class="account-card account-orders-index">
        <div class="account-orders-index__head">
            <h1 class="account-orders-index__title">Мої замовлення</h1>
            <nav class="account-orders-tabs" aria-label="Фільтр за оплатою">
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
                        <div class="account-order-card__date">{{ $order->placed_at?->format('d.m.Y H:i') ?? 'Дата не вказана' }}</div>
                    </div>

                    <div class="account-order-card__amount">
                        <span class="account-order-card__label">Сума</span>
                        <span class="account-order-card__sum">{{ number_format((float) $order->total, 2, ',', ' ') }} UAH</span>
                    </div>

                    <div class="account-order-card__state">
                        <span class="account-order-card__label">Стан</span>
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
                            <a class="btn btn-buy" href="{{ route('account.orders.payment', $order) }}">Оплатити</a>
                        @endif
                        <a class="account-order-card__open" href="{{ route('account.orders.show', $order) }}">Деталі</a>
                    </div>
                </article>
            @empty
                <p class="account-empty" style="margin:0;">Поки що порожньо.</p>
            @endforelse
        </div>

        @if ($orders->hasPages())
            <div style="margin-top:1.1rem;">{{ $orders->links() }}</div>
        @endif
    </section>
@endsection
