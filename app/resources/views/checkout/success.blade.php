@extends('layouts.shop')

@section('title', __('shop.checkout_success_page_title'))
@section('robots', 'noindex,follow')

@section('content')
    @php($isPaid = (string) $order->payment_status === 'paid')

    <div class="checkout-page checkout-page--success">
        <header class="checkout-page__toolbar">
            <p class="checkout-page__status" role="status">{{ $isPaid ? __('shop.checkout_success_status_paid') : __('shop.checkout_success_status_pending') }}</p>
            <h1 class="checkout-page__title">{{ $isPaid ? __('shop.checkout_success_title_paid') : __('shop.checkout_success_title_pending') }}</h1>
            <p class="checkout-page__lead">{{ __('shop.checkout_success_order_number') }} <strong class="checkout-page__order-number">{{ $order->number }}</strong></p>
        </header>

        @if (session('error'))
            <p class="alert error" role="alert">{{ session('error') }}</p>
        @endif

        @if ($order->deferred_online_payment && ! $isPaid)
            <section class="card" style="margin-bottom:1rem;">
                <p class="muted" style="margin:0;line-height:1.55;">
                    {{ __('shop.checkout_success_defer_body') }}
                </p>
            </section>
        @endif

        <section class="card">
            <p class="muted">{{ __('shop.checkout_success_status_payment_line', ['status' => $order->statusLabel(), 'payment' => $order->paymentStatusLabel()]) }}</p>
            @php($dLabel = \App\Models\Order::deliveryTypeLabels()[$order->delivery_type] ?? $order->delivery_type)
            <p><strong>{{ __('shop.checkout_success_delivery_label') }}</strong> {{ $dLabel }}</p>
            @php($dSum = $order->deliverySummaryText())
            @if ($dSum !== '')
                <p>{{ $dSum }}</p>
            @endif
            @if (\App\Models\Order::isNovaPoshtaDelivery($order->delivery_type) && filled(trim((string) ($order->nova_poshta_ttn ?? ''))))
                <p><strong>{{ __('shop.checkout_success_np_ttn') }}</strong> {{ trim((string) $order->nova_poshta_ttn) }}</p>
            @endif
            @if ($order->delivery_type === \App\Models\Order::DELIVERY_PICKUP && ($pickupLine = $order->pickupShopAddressLine()))
                <p><strong>{{ __('shop.checkout_success_pickup_address') }}</strong> {{ $pickupLine }}</p>
            @endif
            @if ($order->delivery_type === \App\Models\Order::DELIVERY_PICKUP && ($pickupPos = $order->pickupShopMapPosition()))
                <p class="muted" style="margin-top:0.5rem;">
                    <a href="https://www.openstreetmap.org/?mlat={{ $pickupPos['lat'] }}&mlon={{ $pickupPos['lng'] }}#map=16/{{ $pickupPos['lat'] }}/{{ $pickupPos['lng'] }}" target="_blank" rel="noopener">{{ __('shop.checkout_success_map_pickup') }}</a>
                </p>
            @endif
            @if (($courierPos = $order->courierDeliveryMapPosition()) !== null)
                <p class="muted" style="margin-top:0.5rem;">
                    <a href="https://www.openstreetmap.org/?mlat={{ $courierPos['lat'] }}&mlon={{ $courierPos['lng'] }}#map=17/{{ $courierPos['lat'] }}/{{ $courierPos['lng'] }}" target="_blank" rel="noopener">{{ __('shop.checkout_success_map_courier') }}</a>
                </p>
            @endif
            @if ($order->customer_notes)
                <p class="muted">{{ __('shop.checkout_success_notes') }} {{ $order->customer_notes }}</p>
            @endif
        </section>

        <div class="checkout-page__actions">
            <a class="btn btn-buy" href="{{ route('orders.track', ['order' => $order, 'token' => $order->success_token]) }}">{{ __('shop.checkout_success_btn_track') }}</a>
            <a class="btn secondary" href="{{ route('catalog.index') }}">{{ __('shop.checkout_success_btn_catalog') }}</a>
        </div>
    </div>
@endsection
