@php
    /** @var \App\Models\Order $order */
@endphp
<div class="card">
    <h1>Замовлення {{ $order->number }}</h1>
    <p><strong>Статус:</strong> {{ $order->status }}</p>
    <p><strong>Оплата:</strong> {{ $order->payment_status }}</p>
    <p><strong>Сума:</strong> {{ number_format((float) $order->total, 2) }} UAH</p>
    @php($dLabel = \App\Models\Order::deliveryTypeLabels()[$order->delivery_type] ?? $order->delivery_type)
    <p><strong>Доставка:</strong> {{ $dLabel }}</p>
    @if ($order->delivery_type === \App\Models\Order::DELIVERY_NOVA_POSHTA)
        <p>{{ $order->delivery_city }} — {{ $order->delivery_branch }}</p>
    @elseif ($order->delivery_type === \App\Models\Order::DELIVERY_COURIER && $order->delivery_address)
        <p>{{ $order->delivery_address }}</p>
    @endif
</div>
<div class="card">
    <h3>Позиції</h3>
    @foreach ($order->items as $item)
        <p>{{ $item->title_snapshot }} × {{ $item->qty }} — {{ number_format((float) $item->line_total, 2) }} UAH</p>
    @endforeach
</div>
