@php
    /** @var \App\Models\Order $order */
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

<header class="order-track__hero">
    <p class="order-track__eyebrow">Замовлення оформлено</p>
    <h1 class="order-track__title">{{ $order->number }}</h1>
    <p class="order-track__meta">Дякуємо за покупку. Нижче актуальний стан замовлення та оплати.</p>
</header>

<section class="order-track__panel" aria-label="Підсумок замовлення">
    <div class="order-track__summary-grid">
        <div class="order-track__summary-item">
            <span class="order-track__label">Статус</span>
            <span class="order-track__badge order-track__badge--{{ $statusTone }}">{{ $order->statusLabel() }}</span>
        </div>
        <div class="order-track__summary-item">
            <span class="order-track__label">Оплата</span>
            <span class="order-track__badge order-track__badge--{{ $paymentTone }}">{{ $order->paymentStatusLabel() }}</span>
        </div>
        <div class="order-track__summary-item">
            <span class="order-track__label">Сума</span>
            <span class="order-track__value order-track__value--price">{{ number_format((float) $order->total, 2, ',', ' ') }} UAH</span>
        </div>
    </div>

    <div class="order-track__details">
        <div class="order-track__detail-card">
            <span class="order-track__label">Доставка</span>
            <span class="order-track__value">{{ $dLabel }}</span>

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

<section class="order-track__panel" aria-labelledby="order-track-items-title">
    <div class="order-track__section-head">
        <h2 id="order-track-items-title" class="order-track__section-title">Позиції</h2>
        <span class="order-track__label" style="margin:0;">{{ $order->items->count() }} шт.</span>
    </div>

    <ul class="order-track__items">
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
            <li class="order-track__item">
                @if ($itemUrl)
                    <a class="order-track__item-media" href="{{ $itemUrl }}" aria-label="Переглянути {{ $item->title_snapshot }}">
                        @if ($photoUrl)
                            <img src="{{ $photoUrl }}" alt="{{ $item->title_snapshot }}" loading="lazy" decoding="async">
                        @else
                            <span class="order-track__item-media-empty">Немає фото</span>
                        @endif
                    </a>
                @else
                    <div class="order-track__item-media" aria-hidden="true">
                        @if ($photoUrl)
                            <img src="{{ $photoUrl }}" alt="">
                        @else
                            <span class="order-track__item-media-empty">Немає фото</span>
                        @endif
                    </div>
                @endif
                <div>
                    <p class="order-track__item-title">
                        @if ($itemUrl)
                            <a href="{{ $itemUrl }}">{{ $item->title_snapshot }}</a>
                        @else
                            {{ $item->title_snapshot }}
                        @endif
                    </p>
                    <p class="order-track__item-meta">Кількість: {{ $item->qty }} × {{ number_format((float) $item->price, 2, ',', ' ') }} UAH</p>
                </div>
                <span class="order-track__item-price">{{ number_format((float) $item->line_total, 2, ',', ' ') }} UAH</span>
            </li>
        @endforeach
    </ul>
</section>
