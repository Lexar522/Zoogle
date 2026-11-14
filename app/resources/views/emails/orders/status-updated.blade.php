@php
    $trackUrl = route('account.orders.show', ['order' => $order, 'token' => $order->success_token]);
    $preheader = $update->statusChanged()
        ? __('shop.mail_order_status_intro', ['name' => $order->customer_name ?: __('shop.mail_order_customer_fallback'), 'number' => $order->number])
        : __('shop.mail_order_message_intro', ['name' => $order->customer_name ?: __('shop.mail_order_customer_fallback'), 'number' => $order->number]);
@endphp
<x-emails.shop-layout :preheader="$preheader" :subject="__('shop.mail_order_status_heading')" :message="$message ?? null">
    <h1 style="margin:8px 0 6px;font-size:24px;line-height:1.25;color:#121417;font-weight:700;">{{ __('shop.mail_order_status_heading') }}</h1>
    <p style="margin:0 0 18px;font-size:15px;line-height:1.5;color:#5f6368;">{{ $preheader }}</p>

    @if ($update->statusChanged() && $update->statusLabel())
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 18px;">
            <tr>
                <td style="background:#f4f7fc;border:1px solid #e4eaf3;border-radius:12px;padding:14px 16px;">
                    <div style="font-size:11px;letter-spacing:0.08em;text-transform:uppercase;color:#5f6368;font-weight:700;">{{ __('shop.mail_order_status_now') }}</div>
                    <div style="margin-top:4px;font-size:20px;line-height:1.2;color:#0b57d0;font-weight:700;">{{ $update->statusLabel() }}</div>
                    @if ($update->previousStatusLabel())
                        <div style="margin-top:6px;font-size:13px;color:#80868b;">{{ __('shop.mail_order_status_was', ['status' => $update->previousStatusLabel()]) }}</div>
                    @endif
                </td>
            </tr>
        </table>
    @endif

    @if (filled($update->message))
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 18px;">
            <tr>
                <td style="background:#f8fbff;border:1px solid #d7e3f8;border-radius:12px;padding:12px 14px;">
                    <div style="font-size:11px;letter-spacing:0.06em;text-transform:uppercase;color:#5f6368;font-weight:700;">{{ __('shop.mail_order_shop_comment') }}</div>
                    <div style="margin-top:6px;font-size:14px;line-height:1.5;color:#202124;">{{ $update->message }}</div>
                </td>
            </tr>
        </table>
    @endif

    @if (\App\Models\Order::isNovaPoshtaDelivery($order->delivery_type) && filled(trim((string) ($order->nova_poshta_ttn ?? ''))))
        <p style="margin:0 0 12px;font-size:14px;color:#202124;"><strong>{{ __('shop.checkout_success_np_ttn') }}</strong> {{ trim((string) $order->nova_poshta_ttn) }}</p>
    @endif

    <p style="margin:0 0 20px;font-size:14px;color:#202124;"><strong>{{ __('shop.mail_order_total') }}:</strong> {{ \App\Support\ShopMailBranding::money($order->total) }}</p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:8px 0;">
        <tr>
            <td align="center">
                <a href="{{ $trackUrl }}" style="display:inline-block;background:#367df1;color:#ffffff;text-decoration:none;font-size:15px;font-weight:700;padding:13px 22px;border-radius:10px;">
                    {{ __('shop.mail_order_open_button') }}
                </a>
            </td>
        </tr>
    </table>
</x-emails.shop-layout>
