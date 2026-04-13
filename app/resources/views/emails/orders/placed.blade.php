<x-mail::message>
# Дякуємо за замовлення

**Номер:** {{ $order->number }}

**Сума:** {{ number_format((float) $order->total, 2) }} UAH

**Статус замовлення:** {{ $order->status }}

**Оплата:** {{ $order->payment_status }}

@php($dLabel = \App\Models\Order::deliveryTypeLabels()[$order->delivery_type] ?? $order->delivery_type)
**Доставка:** {{ $dLabel }}

@if ($order->delivery_type === \App\Models\Order::DELIVERY_NOVA_POSHTA)
{{ $order->delivery_city }} — {{ $order->delivery_branch }}
@elseif ($order->delivery_type === \App\Models\Order::DELIVERY_COURIER && $order->delivery_address)
{{ $order->delivery_address }}
@endif

@if ($order->customer_notes)
**Примітки:** {{ $order->customer_notes }}
@endif

## Позиції

@foreach ($order->items as $item)
- {{ $item->title_snapshot }} × {{ $item->qty }} — {{ number_format((float) $item->line_total, 2) }} UAH
@endforeach

<x-mail::button :url="route('orders.track', ['order' => $order, 'token' => $order->success_token])">
Переглянути замовлення
</x-mail::button>

З повагою,<br>
{{ config('app.name') }}
</x-mail::message>
