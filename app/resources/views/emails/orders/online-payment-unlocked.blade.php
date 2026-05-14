@component('mail::message')
# Можна оплатити онлайн

@php
    $defAmt = (float) $order->effectiveDeferredSubtotal();
@endphp
@if ($defAmt > 0)
Ваше замовлення **{{ $order->number }}**: можна оплатити онлайн **{{ number_format($defAmt, 2) }}** UAH за частину після узгодження товарів.
@else
Ваше замовлення **{{ $order->number }}** (сума **{{ number_format((float) $order->total, 2) }}** UAH): менеджер дозволив оплату карткою.
@endif

Якщо ви вже в акаунті, відкрийте замовлення в особистому кабінеті та натисніть **«Оплатити карткою (LiqPay)»**.

@component('mail::button', ['url' => route('checkout.payment', ['order' => $order->id, 'token' => $order->success_token, 'leg' => 'deferred'], absolute: true)])
Перейти до оплати
@endcomponent

Сторінка LiqPay відкриється автоматично. Якщо у вас є акаунт, оплату можна також зробити в розділі «Мої замовлення».

@endcomponent
