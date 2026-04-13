@extends('layouts.shop')

@section('title', 'Замовлення створено — ZOOGLE')

@section('content')
    <div class="card">
        <h1>Замовлення створено</h1>
        <p>Дякуємо! Номер замовлення: <strong>{{ $order->number }}</strong></p>
        <p>Статус: {{ $order->status }}, оплата: {{ $order->payment_status }}</p>
        @php($dLabel = \App\Models\Order::deliveryTypeLabels()[$order->delivery_type] ?? $order->delivery_type)
        <p><strong>Доставка:</strong> {{ $dLabel }}</p>
        @if ($order->delivery_type === \App\Models\Order::DELIVERY_NOVA_POSHTA)
            <p>{{ $order->delivery_city }} — {{ $order->delivery_branch }}</p>
        @elseif ($order->delivery_type === \App\Models\Order::DELIVERY_COURIER && $order->delivery_address)
            <p>{{ $order->delivery_address }}</p>
        @endif
        @if ($order->customer_notes)
            <p class="muted">Примітки: {{ $order->customer_notes }}</p>
        @endif
        <p><a class="btn" href="{{ route('orders.track', ['order' => $order, 'token' => $order->success_token]) }}">Сторінка замовлення</a></p>
        <a class="btn" href="{{ route('catalog.index') }}">Повернутися в каталог</a>
    </div>
@endsection
