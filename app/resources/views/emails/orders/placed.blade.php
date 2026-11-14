@php
    $dLabel = \App\Models\Order::deliveryTypeLabels()[$order->delivery_type] ?? $order->delivery_type;
    $dSum = $order->deliverySummaryText();
    $pickupLine = $order->delivery_type === \App\Models\Order::DELIVERY_PICKUP
        ? $order->pickupShopAddressLine()
        : null;
    $trackUrl = route('account.orders.show', ['order' => $order, 'token' => $order->success_token]);
    $name = trim((string) $order->customer_name);
    $phone = trim((string) $order->customer_phone);
    $email = trim((string) $order->customer_email);
    $notes = trim((string) ($order->customer_notes ?? ''));
    $preheader = 'Замовлення '.$order->number.' на '.\App\Support\ShopMailBranding::money($order->total);
    $paymentMethod = match ((string) $order->checkout_payment_method) {
        'online' => __('shop.checkout_pay_card'),
        'cod' => __('shop.checkout_pay_cod'),
        default => '',
    };
    $paymentLine = trim(($paymentMethod !== '' ? $paymentMethod.' · ' : '').$order->paymentStatusLabel());
    $addressLines = array_values(array_filter([
        $dSum !== '' ? $dSum : null,
        $pickupLine,
    ]));
    $rows = array_values(array_filter([
        $name !== '' ? ['Ім’я', $name] : null,
        $phone !== '' ? ['Телефон', $phone] : null,
        $email !== '' ? ['Email', $email] : null,
        ['Статус', $order->statusLabel()],
        ['Оплата', $paymentLine],
    ]));
    $labelStyle = 'padding:11px 12px 11px 22px;font-size:14px;line-height:1.35;color:#6b7280;vertical-align:top;width:120px;';
    $valueStyle = 'padding:11px 22px 11px 0;font-size:15px;line-height:1.35;color:#1f2328;font-weight:600;text-align:right;vertical-align:top;';
@endphp
<x-emails.shop-layout :preheader="$preheader" :subject="'Замовлення '.$order->number.' — ZOOGLE'" :message="$message ?? null" full-bleed>
    <tr>
        <td width="600" align="center" style="width:600px;padding:8px 22px 16px;text-align:center;">
            <h1 style="margin:0;font-size:24px;line-height:1.25;color:#121417;font-weight:700;text-align:center;">Дякуємо за замовлення</h1>
            <p style="margin:6px 0 0;font-size:15px;line-height:1.45;color:#5f6368;text-align:center;">
                @if ($name !== '')
                    {{ $name }}, починаємо готувати<br />ваше замовлення.
                @else
                    Починаємо готувати ваше замовлення.
                @endif
            </p>
        </td>
    </tr>
    <tr>
        <td width="600" align="center" bgcolor="#f4f7fc" style="width:600px;background-color:#f4f7fc;padding:16px 22px;text-align:center;">
            <div style="font-size:11px;letter-spacing:0.08em;text-transform:uppercase;color:#6b7280;font-weight:700;">Номер замовлення</div>
            <div style="margin-top:4px;font-size:18px;line-height:1.3;color:#0b57d0;font-weight:700;">{{ $order->number }}</div>
        </td>
    </tr>
    <tr>
        <td width="600" height="8" bgcolor="#ffffff" style="width:600px;height:8px;background-color:#ffffff;font-size:0;line-height:0;">&nbsp;</td>
    </tr>
    <tr>
        <td width="600" bgcolor="#f4f7fc" style="width:600px;min-width:600px;background-color:#f4f7fc;padding:0;">
            <table role="presentation" width="600" bgcolor="#f4f7fc" cellpadding="0" cellspacing="0" border="0" style="width:600px;min-width:600px;background-color:#f4f7fc;">
                @foreach ($rows as $index => $row)
                    <tr>
                        <td style="{{ $labelStyle }}{{ $index > 0 ? 'border-top:1px solid #e7edf5;' : '' }}">{{ $row[0] }}</td>
                        <td style="{{ $valueStyle }}{{ $index > 0 ? 'border-top:1px solid #e7edf5;' : '' }}">{{ $row[1] }}</td>
                    </tr>
                @endforeach
            </table>
        </td>
    </tr>
    <tr>
        <td width="600" height="8" bgcolor="#ffffff" style="width:600px;height:8px;background-color:#ffffff;font-size:0;line-height:0;">&nbsp;</td>
    </tr>
    <tr>
        <td width="600" bgcolor="#f8fbff" style="width:600px;background-color:#f8fbff;padding:14px 22px 22px;">
            <div style="font-size:11px;letter-spacing:0.08em;text-transform:uppercase;color:#6b7280;font-weight:700;">Адреса</div>
            <div style="margin-top:6px;font-size:15px;line-height:1.4;color:#1f2328;font-weight:700;">{{ $dLabel }}</div>
            @foreach ($addressLines as $line)
                <div style="margin-top:4px;font-size:14px;line-height:1.45;color:#3c4043;">{{ $line }}</div>
            @endforeach
            @if ($notes !== '')
                <div style="margin-top:8px;font-size:13px;line-height:1.45;color:#5f6368;">{{ $notes }}</div>
            @endif
        </td>
    </tr>
    <tr>
        <td width="600" style="width:600px;padding:8px 22px 8px;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 8px;">
        @foreach ($order->items as $item)
            @php
                $photoSrc = \App\Support\ShopMailBranding::publicImageSrc($message ?? null, $item->mailPhotoStoragePath());
                $initial = mb_strtoupper(mb_substr(trim((string) $item->title_snapshot), 0, 1));
            @endphp
            <tr>
                <td width="60" valign="middle" style="padding:12px 12px 12px 0;border-bottom:1px solid #eef1f4;">
                    @if ($photoSrc)
                        <img src="{{ $photoSrc }}" alt="" width="52" height="52" style="display:block;width:52px;height:52px;border-radius:12px;border:0;background:#f1f3f4;" />
                    @else
                        <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="52" height="52" style="width:52px;height:52px;">
                            <tr>
                                <td align="center" valign="middle" width="52" height="52" bgcolor="#eef3fb" style="width:52px;height:52px;background:#eef3fb;border-radius:12px;font-family:Arial,Helvetica,sans-serif;font-size:18px;font-weight:700;color:#367df1;line-height:52px;">
                                    {{ $initial !== '' ? $initial : '·' }}
                                </td>
                            </tr>
                        </table>
                    @endif
                </td>
                <td valign="middle" style="padding:12px 10px 12px 0;border-bottom:1px solid #eef1f4;">
                    <div style="font-size:15px;line-height:1.35;color:#1f2328;font-weight:600;">{{ $item->title_snapshot }}</div>
                    <div style="margin-top:3px;font-size:13px;line-height:1.35;color:#6b7280;">{{ (int) $item->qty }} шт. × {{ \App\Support\ShopMailBranding::money($item->price) }}</div>
                </td>
                <td align="right" valign="middle" style="padding:12px 0;border-bottom:1px solid #eef1f4;font-size:15px;line-height:1.35;font-weight:700;color:#1f2328;white-space:nowrap;">
                    {{ \App\Support\ShopMailBranding::money($item->line_total) }}
                </td>
            </tr>
        @endforeach
        <tr>
            <td colspan="2" style="padding:16px 0 2px;font-size:15px;font-weight:700;color:#1f2328;">Разом</td>
            <td align="right" style="padding:16px 0 2px;font-size:20px;line-height:1.2;font-weight:700;color:#0b57d0;white-space:nowrap;">{{ \App\Support\ShopMailBranding::money($order->total) }}</td>
        </tr>
    </table>

    <center>
        <table role="presentation" align="center" cellpadding="0" cellspacing="0" border="0" style="margin:22px auto 0;">
            <tr>
                <td align="center" bgcolor="#367df1" style="background:#367df1;border-radius:12px;text-align:center;">
                    <a href="{{ $trackUrl }}" style="display:inline-block;padding:14px 26px;color:#ffffff;text-decoration:none;font-size:15px;font-weight:700;text-align:center;">
                        Переглянути замовлення
                    </a>
                </td>
            </tr>
        </table>
    </center>
        </td>
    </tr>
</x-emails.shop-layout>
