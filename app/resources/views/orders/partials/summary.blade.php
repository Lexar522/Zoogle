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
    <p class="order-track__eyebrow">{{ __('shop.order_track_eyebrow') }}</p>
    <h1 class="order-track__title">{{ $order->number }}</h1>
    <p class="order-track__meta">{{ __('shop.order_track_hero_meta') }}</p>
</header>

<section class="order-track__panel" aria-label="{{ __('shop.order_track_summary_aria') }}">
    <div class="order-track__summary-grid">
        <div class="order-track__summary-item">
            <span class="order-track__label">{{ __('shop.order_track_label_status') }}</span>
            <span class="order-track__badge order-track__badge--{{ $statusTone }}">{{ $order->statusLabel() }}</span>
        </div>
        <div class="order-track__summary-item">
            <span class="order-track__label">{{ __('shop.order_track_label_payment') }}</span>
            <span class="order-track__badge order-track__badge--{{ $paymentTone }}">{{ $order->paymentStatusLabel() }}</span>
        </div>
        <div class="order-track__summary-item">
            <span class="order-track__label">{{ __('shop.order_track_label_total') }}</span>
            <span class="order-track__value order-track__value--price">{{ number_format((float) $order->total, 2, ',', ' ') }} UAH</span>
        </div>
    </div>

    <div class="order-track__details">
        <div class="order-track__detail-card">
            <span class="order-track__label">{{ __('shop.order_track_label_delivery') }}</span>
            <span class="order-track__value">{{ $dLabel }}</span>

            @if ($dSum !== '')
                <p>{{ $dSum }}</p>
            @endif

            @if ($order->delivery_type === \App\Models\Order::DELIVERY_PICKUP && ($pickupLine = $order->pickupShopAddressLine()))
                <p>{{ __('shop.order_track_pickup_line', ['address' => $pickupLine]) }}</p>
            @endif

            @if (\App\Models\Order::isNovaPoshtaDelivery($order->delivery_type) && filled(trim((string) ($order->nova_poshta_ttn ?? ''))))
                <p><strong>{{ __('shop.checkout_success_np_ttn') }}</strong> {{ trim((string) $order->nova_poshta_ttn) }}</p>
            @endif
        </div>
    </div>
</section>

<section class="order-track__panel" aria-labelledby="order-track-items-title">
    <div class="order-track__section-head">
        <h2 id="order-track-items-title" class="order-track__section-title">{{ __('shop.order_track_items_title') }}</h2>
        <span class="order-track__label" style="margin:0;">{{ __('shop.order_track_pieces', ['count' => $order->items->count()]) }}</span>
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
                    <a class="order-track__item-media" href="{{ $itemUrl }}" aria-label="{{ __('shop.order_track_view_item', ['title' => $item->title_snapshot]) }}">
                        @if ($photoUrl)
                            <img src="{{ $photoUrl }}" alt="{{ $item->title_snapshot }}" loading="lazy" decoding="async">
                        @else
                            <span class="order-track__item-media-empty">{{ __('shop.order_track_no_photo') }}</span>
                        @endif
                    </a>
                @else
                    <div class="order-track__item-media" aria-hidden="true">
                        @if ($photoUrl)
                            <img src="{{ $photoUrl }}" alt="">
                        @else
                            <span class="order-track__item-media-empty">{{ __('shop.order_track_no_photo') }}</span>
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
                    <p class="order-track__item-meta">{{ __('shop.order_track_qty_price', ['qty' => $item->qty, 'price' => number_format((float) $item->price, 2, ',', ' ')]) }}</p>
                </div>
                <span class="order-track__item-price">{{ number_format((float) $item->line_total, 2, ',', ' ') }} UAH</span>
            </li>
        @endforeach
    </ul>
</section>
