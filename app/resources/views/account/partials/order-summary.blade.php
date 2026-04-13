@php
    /** @var \App\Models\Order $order */
@endphp
<section class="account-card" aria-labelledby="order-summary-title">
    <div class="account-card__head account-card__head--hero">
        <h1 id="order-summary-title">Замовлення {{ $order->number }}</h1>
    </div>
    <dl style="margin:0;display:grid;gap:0.65rem 1.5rem;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));">
        <div>
            <dt class="muted" style="font-size:0.78rem;font-weight:700;text-transform:uppercase;letter-spacing:0.04em;margin:0 0 0.2rem;">Статус</dt>
            <dd style="margin:0;font-weight:700;">{{ $order->status }}</dd>
        </div>
        <div>
            <dt class="muted" style="font-size:0.78rem;font-weight:700;text-transform:uppercase;letter-spacing:0.04em;margin:0 0 0.2rem;">Оплата</dt>
            <dd style="margin:0;font-weight:700;">{{ $order->payment_status }}</dd>
        </div>
        <div>
            <dt class="muted" style="font-size:0.78rem;font-weight:700;text-transform:uppercase;letter-spacing:0.04em;margin:0 0 0.2rem;">Сума</dt>
            <dd style="margin:0;font-weight:800;color:var(--color-price);font-variant-numeric:tabular-nums;">{{ number_format((float) $order->total, 2) }} UAH</dd>
        </div>
        @php($dLabel = \App\Models\Order::deliveryTypeLabels()[$order->delivery_type] ?? $order->delivery_type)
        <div style="grid-column:1/-1;">
            <dt class="muted" style="font-size:0.78rem;font-weight:700;text-transform:uppercase;letter-spacing:0.04em;margin:0 0 0.2rem;">Доставка</dt>
            <dd style="margin:0;font-weight:600;">{{ $dLabel }}</dd>
            @if ($order->delivery_type === \App\Models\Order::DELIVERY_NOVA_POSHTA)
                <dd style="margin:0.35rem 0 0;color:var(--muted);font-weight:500;">{{ $order->delivery_city }} — {{ $order->delivery_branch }}</dd>
            @elseif ($order->delivery_type === \App\Models\Order::DELIVERY_COURIER && $order->delivery_address)
                <dd style="margin:0.35rem 0 0;color:var(--muted);font-weight:500;">{{ $order->delivery_address }}</dd>
            @endif
        </div>
    </dl>
</section>
<section class="account-card" aria-labelledby="order-items-title">
    <h2 id="order-items-title" class="account-section-title" style="margin-bottom:0.85rem;">Позиції</h2>
    <ul style="list-style:none;margin:0;padding:0;">
        @foreach ($order->items as $item)
            <li style="padding:0.65rem 0;border-bottom:1px solid #eef0f4;display:flex;flex-wrap:wrap;justify-content:space-between;gap:0.35rem 1rem;">
                <span style="font-weight:600;">{{ $item->title_snapshot }} × {{ $item->qty }}</span>
                <span style="font-weight:800;color:var(--color-price);font-variant-numeric:tabular-nums;">{{ number_format((float) $item->line_total, 2) }} UAH</span>
            </li>
        @endforeach
    </ul>
</section>
