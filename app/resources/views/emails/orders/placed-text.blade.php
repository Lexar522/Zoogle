Дякуємо за замовлення
@if (trim((string) $order->customer_name) !== '')
{{ trim((string) $order->customer_name) }}, починаємо готувати ваше замовлення.
@else
Починаємо готувати ваше замовлення.
@endif

Номер: {{ $order->number }}
@if (trim((string) $order->customer_name) !== '')
Ім’я: {{ trim((string) $order->customer_name) }}
@endif
@if (trim((string) $order->customer_phone) !== '')
Телефон: {{ trim((string) $order->customer_phone) }}
@endif
@if (trim((string) $order->customer_email) !== '')
Email: {{ trim((string) $order->customer_email) }}
@endif
Сума: {{ \App\Support\ShopMailBranding::money($order->total) }}
Статус: {{ $order->statusLabel() }}
Оплата: {{ $order->paymentStatusLabel() }}

@php($dLabel = \App\Models\Order::deliveryTypeLabels()[$order->delivery_type] ?? $order->delivery_type)
Доставка: {{ $dLabel }}
@php($dSum = $order->deliverySummaryText())
@if ($dSum !== '')
{{ $dSum }}
@endif
@if ($order->delivery_type === \App\Models\Order::DELIVERY_PICKUP && ($pickup = $order->pickupShopAddressLine()))
Самовивіз: {{ $pickup }}
@endif

Склад замовлення:
@foreach ($order->items as $item)
- {{ $item->title_snapshot }} × {{ $item->qty }} — {{ \App\Support\ShopMailBranding::money($item->line_total) }}
@endforeach

Переглянути замовлення: {{ route('account.orders.show', ['order' => $order, 'token' => $order->success_token]) }}

ZOOGLE
