@php
    use App\Models\Order;
    use App\Services\ShopAnalytics;

    /** @var Order $order */
    $order = $getRecord();
    $statusTone = match ((string) $order->status) {
        Order::STATUS_NEW => '#007aff',
        Order::STATUS_PAID => '#34c759',
        Order::STATUS_PROCESSING => '#ff9500',
        Order::STATUS_SHIPPED => '#af52de',
        Order::STATUS_CANCELLED => '#ff3b30',
        default => '#8e8e93',
    };
    $paymentTone = match ((string) $order->payment_status) {
        'paid' => '#34c759',
        'partial' => '#ff9500',
        'failed' => '#ff3b30',
        default => '#8e8e93',
    };
    $deliveryLabel = Order::deliveryTypeLabels()[$order->delivery_type] ?? (string) $order->delivery_type;
    $deliveryText = trim($order->deliverySummaryText());
    $phone = trim((string) $order->customer_phone);
    $email = trim((string) $order->customer_email);
    $ttn = trim((string) $order->nova_poshta_ttn);
    $split = $order->mixed_payment_plan || $order->deferred_online_payment;
    $timezone = config('app.timezone');
    $items = $order->items()->get();
@endphp

<section class="zo-card zo-summary">
    @if (filled($order->payment_issue))
        <div role="alert" class="zo-payment-issue">
            <strong>Проблема з оплатою</strong><br>
            {!! nl2br(e($order->payment_issue)) !!}
        </div>
    @endif
    <div class="zo-summary__head">
        <div class="zo-summary__title">
            <div class="zo-summary__number">{{ $order->number }}</div>
            <div class="zo-summary__date">
                Оформлено {{ $order->placed_at?->timezone($timezone)->format('d.m.Y, H:i') ?? '—' }}
                @if ($order->paid_at)
                    · оплачено {{ $order->paid_at->timezone($timezone)->format('d.m.Y, H:i') }}
                @endif
            </div>
            <div class="zo-summary__pills">
                <span class="zo-pill" style="--zo-tone: {{ $statusTone }}">{{ $order->statusLabel() }}</span>
                <span class="zo-pill" style="--zo-tone: {{ $paymentTone }}">{{ $order->paymentStatusLabel() }}</span>
            </div>
        </div>
        <div class="zo-summary__total">
            <span>Сума</span>
            <strong>{{ ShopAnalytics::formatMoney((float) $order->total) }}</strong>
            @if ($split)
                <small>
                    Зараз: {{ ShopAnalytics::formatMoney($order->effectiveImmediateSubtotal()) }}
                    · відкладено: {{ ShopAnalytics::formatMoney($order->effectiveDeferredSubtotal()) }}
                </small>
            @endif
        </div>
    </div>

    <dl class="zo-summary__facts">
        <div>
            <dt>Покупець</dt>
            <dd>
                {{ trim((string) $order->customer_name) ?: 'Без імені' }}
                @if ($phone !== '')
                    <a href="tel:{{ preg_replace('/[^\d+]/', '', $phone) }}">{{ $phone }}</a>
                @endif
                @if ($email !== '')
                    <a href="mailto:{{ $email }}">{{ $email }}</a>
                @endif
            </dd>
        </div>
        <div>
            <dt>Доставка</dt>
            <dd>
                {{ $deliveryLabel }}
                @if ($deliveryText !== '')
                    <span>{{ $deliveryText }}</span>
                @endif
                @if ($ttn !== '')
                    <span>ТТН {{ $ttn }}</span>
                @endif
            </dd>
        </div>
        @if ($items->isNotEmpty())
            <div>
                <dt>Склад</dt>
                <dd>
                    @foreach ($items->take(3) as $item)
                        <span>{{ $item->title_snapshot }} × {{ (int) $item->qty }}</span>
                    @endforeach
                    @if ($items->count() > 3)
                        <span>і ще {{ $items->count() - 3 }} (список нижче)</span>
                    @endif
                </dd>
            </div>
        @endif
        @if (filled($order->customer_notes))
            <div>
                <dt>Примітка покупця</dt>
                <dd>{{ $order->customer_notes }}</dd>
            </div>
        @endif
    </dl>
</section>
