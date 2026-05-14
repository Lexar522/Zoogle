@extends('layouts.shop')

@section('title', 'Замовлення створено — ZOOGLE')
@section('robots', 'noindex,follow')

@section('content')
    @php($isPaid = (string) $order->payment_status === 'paid')

    <div class="checkout-page checkout-page--success">
        <header class="checkout-page__toolbar">
            <p class="checkout-page__status" role="status">{{ $isPaid ? 'Оплату отримано' : 'Замовлення прийнято' }}</p>
            <h1 class="checkout-page__title">{{ $isPaid ? 'Дякуємо, оплата пройшла успішно' : 'Дякуємо за покупку' }}</h1>
            <p class="checkout-page__lead">Номер замовлення: <strong class="checkout-page__order-number">{{ $order->number }}</strong></p>
        </header>

        @if (session('error'))
            <p class="alert error" role="alert">{{ session('error') }}</p>
        @endif

        @if ($order->deferred_online_payment && ! $isPaid)
            <section class="card" style="margin-bottom:1rem;">
                <p class="muted" style="margin:0;line-height:1.55;">
                    Для цього замовлення онлайн-оплата карткою буде доступна після узгодження з менеджером.
                    Ми зв’яжемося з вами; після підтвердження ви зможете оплатити в особистому кабінеті або за посиланням з листа.
                </p>
            </section>
        @endif

        <section class="card">
            <p class="muted">Статус: <strong>{{ $order->statusLabel() }}</strong>, оплата: <strong>{{ $order->paymentStatusLabel() }}</strong></p>
            @php($dLabel = \App\Models\Order::deliveryTypeLabels()[$order->delivery_type] ?? $order->delivery_type)
            <p><strong>Доставка:</strong> {{ $dLabel }}</p>
            @php($dSum = $order->deliverySummaryText())
            @if ($dSum !== '')
                <p>{{ $dSum }}</p>
            @endif
            @if (\App\Models\Order::isNovaPoshtaDelivery($order->delivery_type) && filled(trim((string) ($order->nova_poshta_ttn ?? ''))))
                <p><strong>ТТН Нова Пошта:</strong> {{ trim((string) $order->nova_poshta_ttn) }}</p>
            @endif
            @if ($order->delivery_type === \App\Models\Order::DELIVERY_PICKUP && ($pickupLine = $order->pickupShopAddressLine()))
                <p><strong>Адреса самовивозу:</strong> {{ $pickupLine }}</p>
            @endif
            @if ($order->delivery_type === \App\Models\Order::DELIVERY_PICKUP && ($pickupPos = $order->pickupShopMapPosition()))
                <p class="muted" style="margin-top:0.5rem;">
                    <a href="https://www.openstreetmap.org/?mlat={{ $pickupPos['lat'] }}&mlon={{ $pickupPos['lng'] }}#map=16/{{ $pickupPos['lat'] }}/{{ $pickupPos['lng'] }}" target="_blank" rel="noopener">Відкрити адресу на карті</a>
                </p>
            @endif
            @if (($courierPos = $order->courierDeliveryMapPosition()) !== null)
                <p class="muted" style="margin-top:0.5rem;">
                    <a href="https://www.openstreetmap.org/?mlat={{ $courierPos['lat'] }}&mlon={{ $courierPos['lng'] }}#map=17/{{ $courierPos['lat'] }}/{{ $courierPos['lng'] }}" target="_blank" rel="noopener">Точка доставки на карті</a>
                </p>
            @endif
            @if ($order->customer_notes)
                <p class="muted">Примітки: {{ $order->customer_notes }}</p>
            @endif
        </section>

        <div class="checkout-page__actions">
            <a class="btn btn-buy" href="{{ route('orders.track', ['order' => $order, 'token' => $order->success_token]) }}">Сторінка замовлення</a>
            <a class="btn secondary" href="{{ route('catalog.index') }}">Повернутися в каталог</a>
        </div>
    </div>
@endsection
