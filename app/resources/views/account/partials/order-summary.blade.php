@php
    /** @var \App\Models\Order $order */
    $liqPayConfigured = (bool) ($liqPayConfigured ?? $onlinePaymentConfigured ?? app(\App\Support\OnlinePaymentSettings::class)->isConfigured());
    $canPayOnline = $order->canPayDeferredLiqPay() && $liqPayConfigured;
    $statusTone = match ((string) $order->status) {
        \App\Models\Order::STATUS_PAID,
        \App\Models\Order::STATUS_PROCESSING,
        \App\Models\Order::STATUS_SHIPPED,
        \App\Models\Order::STATUS_COMPLETED => 'success',
        \App\Models\Order::STATUS_CANCELLED => 'danger',
        default => 'pending',
    };
    $paymentTone = match ((string) $order->payment_status) {
        'paid', 'partial' => 'success',
        'failed' => 'danger',
        default => 'pending',
    };
    $dLabel = \App\Models\Order::deliveryTypeLabels()[$order->delivery_type] ?? $order->delivery_type;
    $dSum = $order->deliverySummaryText();
@endphp

<header class="account-order-detail__hero">
    <div class="account-order-detail__hero-inner">
        <div>
            <p class="account-order-detail__eyebrow">Деталі замовлення</p>
            <h1 id="order-summary-title" class="account-order-detail__title">{{ $order->number }}</h1>
            <p class="account-order-detail__hint">Актуальний стан, оплата, доставка та склад замовлення.</p>
        </div>

        @if ($canPayOnline)
            <div class="account-order-detail__hero-actions">
                <a class="btn btn-buy" href="{{ route('account.orders.payment', $order) }}">Оплатити карткою онлайн</a>
            </div>
        @endif
    </div>
</header>

<section class="account-order-detail__panel" aria-labelledby="order-summary-title">
    <div class="account-order-detail__grid">
        <div class="account-order-detail__cell">
            <span class="account-order-detail__label">Статус</span>
            <span class="account-order-detail__badge account-order-detail__badge--{{ $statusTone }}">{{ $order->statusLabel() }}</span>
        </div>
        <div class="account-order-detail__cell">
            <span class="account-order-detail__label">Оплата</span>
            <span class="account-order-detail__badge account-order-detail__badge--{{ $paymentTone }}">{{ $order->paymentStatusLabel() }}</span>
        </div>
        <div class="account-order-detail__cell">
            <span class="account-order-detail__label">Сума</span>
            <span class="account-order-detail__value account-order-detail__value--price">{{ number_format((float) $order->total, 2, ',', ' ') }} UAH</span>
        </div>
    </div>

    <div class="account-order-detail__body">
        <div class="account-order-detail__delivery">
            <span class="account-order-detail__label">Доставка</span>
            <span class="account-order-detail__value">{{ $dLabel }}</span>

            @if ($dSum !== '')
                <p>{{ $dSum }}</p>
            @endif

            @if ($order->delivery_type === \App\Models\Order::DELIVERY_PICKUP && ($pickupLine = $order->pickupShopAddressLine()))
                <p>Адреса самовивозу: {{ $pickupLine }}</p>
            @endif

            @if (\App\Models\Order::isNovaPoshtaDelivery($order->delivery_type) && filled(trim((string) ($order->nova_poshta_ttn ?? ''))))
                <p>ТТН Нова Пошта: <strong>{{ trim((string) $order->nova_poshta_ttn) }}</strong></p>
            @endif
        </div>
    </div>
</section>

<section class="account-order-detail__panel" aria-labelledby="order-items-title">
    <div class="account-order-detail__items-head">
        <h2 id="order-items-title" class="account-order-detail__items-title">Позиції</h2>
        <span class="account-order-detail__label" style="margin:0;">{{ $order->items->count() }} шт.</span>
    </div>

    <ul class="account-order-items">
        @foreach ($order->items as $item)
            @php
                $product = $item->relationLoaded('product') ? $item->product : null;
                $bundle = $item->relationLoaded('bundle') ? $item->bundle : null;
                $photoUrl = $item->adminCatalogPhotoUrl();
                $itemUrl = null;

                if ($bundle && filled($bundle->slug)) {
                    $itemUrl = route('bundles.show', $bundle->slug);
                } elseif ($product && filled($product->slug)) {
                    $itemUrl = route('catalog.show', $product->slug);
                }
            @endphp
            <li class="account-order-item">
                @if ($itemUrl)
                    <a class="account-order-item__media" href="{{ $itemUrl }}" aria-label="Переглянути {{ $item->title_snapshot }}">
                        @if ($photoUrl)
                            <img src="{{ $photoUrl }}" alt="{{ $item->title_snapshot }}" loading="lazy" decoding="async">
                        @else
                            <span class="account-order-item__empty">Немає фото</span>
                        @endif
                    </a>
                @else
                    <div class="account-order-item__media" aria-hidden="true">
                        @if ($photoUrl)
                            <img src="{{ $photoUrl }}" alt="">
                        @else
                            <span class="account-order-item__empty">Немає фото</span>
                        @endif
                    </div>
                @endif
                <div>
                    <p class="account-order-item__title">
                        @if ($itemUrl)
                            <a href="{{ $itemUrl }}">{{ $item->title_snapshot }}</a>
                        @else
                            {{ $item->title_snapshot }}
                        @endif
                    </p>
                    <p class="account-order-item__meta">Кількість: {{ $item->qty }} × {{ number_format((float) $item->price, 2, ',', ' ') }} UAH</p>
                </div>
                <span class="account-order-item__price">{{ number_format((float) $item->line_total, 2, ',', ' ') }} UAH</span>
            </li>
        @endforeach
    </ul>
</section>
