@php
    $statusMessage = $statusMessage ?? null;
    $statusType = $statusType ?? 'success';
@endphp

@if (($summary['is_empty'] ?? true) === true)
    <div class="cart-drawer__empty">
        <h3 class="cart-drawer__empty-title">Кошик порожній</h3>
        <p class="cart-drawer__empty-text">Додайте товари з каталогу, щоб побачити їх тут.</p>
        <a href="{{ route('catalog.index') }}" class="cart-drawer__empty-link">Перейти до каталогу</a>
    </div>
@else
    <div class="cart-drawer__lines">
        @foreach ($items as $item)
            @include('cart.partials.line', ['item' => $item])
        @endforeach
    </div>

    <div class="cart-drawer__footer">
        <div class="cart-drawer__summary">
            <div>
                <span class="cart-drawer__summary-label">Позицій</span>
                <span class="cart-drawer__summary-value">{{ (int) ($summary['lines_count'] ?? 0) }}</span>
            </div>
            <div>
                <span class="cart-drawer__summary-label">Товарів</span>
                <span class="cart-drawer__summary-value">{{ (int) ($summary['items_count'] ?? 0) }}</span>
            </div>
            <div>
                <span class="cart-drawer__summary-label">Разом</span>
                <span
                    class="cart-drawer__summary-value"
                    data-cart-summary-total="{{ number_format((float) ($summary['total'] ?? 0), 2, '.', '') }}"
                >{{ number_format((float) ($summary['total'] ?? 0), 2, ',', ' ') }} ₴</span>
            </div>
        </div>

        <a href="{{ route('checkout.create') }}" class="cart-drawer__checkout-btn">Оформити замовлення</a>
    </div>
@endif
