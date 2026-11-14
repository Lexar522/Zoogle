@php
    $defAmt = (float) $order->effectiveDeferredSubtotal();
    $payUrl = route('checkout.payment', ['order' => $order->id, 'token' => $order->success_token, 'leg' => 'deferred'], absolute: true);
    $preheader = 'Замовлення '.$order->number.': можна оплатити онлайн';
@endphp
<x-emails.shop-layout :preheader="$preheader" :subject="'Онлайн-оплата дозволена: замовлення '.$order->number" :message="$message ?? null">
    <h1 style="margin:8px 0 6px;font-size:24px;line-height:1.25;color:#121417;font-weight:700;">Можна оплатити онлайн</h1>
    <p style="margin:0 0 18px;font-size:15px;line-height:1.5;color:#5f6368;">
        @if ($defAmt > 0)
            Ваше замовлення <strong>{{ $order->number }}</strong>: можна оплатити онлайн
            <strong>{{ \App\Support\ShopMailBranding::money($defAmt) }}</strong> за частину після узгодження товарів.
        @else
            Ваше замовлення <strong>{{ $order->number }}</strong>
            (сума <strong>{{ \App\Support\ShopMailBranding::money($order->total) }}</strong>):
            менеджер дозволив оплату карткою.
        @endif
    </p>
    <p style="margin:0 0 20px;font-size:14px;line-height:1.5;color:#3c4043;">
        Якщо ви вже в акаунті, відкрийте замовлення в особистому кабінеті та натисніть «Оплатити карткою».
    </p>
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
        <tr>
            <td align="center">
                <a href="{{ $payUrl }}" style="display:inline-block;background:#367df1;color:#ffffff;text-decoration:none;font-size:15px;font-weight:700;padding:13px 22px;border-radius:10px;">
                    Перейти до оплати
                </a>
            </td>
        </tr>
    </table>
</x-emails.shop-layout>
