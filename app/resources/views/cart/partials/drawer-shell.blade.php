@php
    $drawerItems = $cartDrawerData['items'] ?? collect();
    $drawerSummary = $cartDrawerData['summary'] ?? [
        'lines_count' => 0,
        'items_count' => 0,
        'total' => 0.0,
        'is_empty' => true,
    ];
@endphp

<div
    class="cart-drawer"
    id="cart-drawer"
    data-cart-drawer
    aria-hidden="true"
    hidden
>
    <button type="button" class="cart-drawer__scrim" data-cart-close aria-label="Закрити кошик"></button>

    <aside
        class="cart-drawer__panel"
        role="dialog"
        aria-modal="true"
        aria-labelledby="cart-drawer-title"
        tabindex="-1"
    >
        <div class="cart-drawer__panel-header">
            <div>
                <h2 class="cart-drawer__panel-title" id="cart-drawer-title">Кошик</h2>
                <p class="cart-drawer__panel-meta">
                    <span data-cart-panel-count>{{ (int) ($drawerSummary['items_count'] ?? 0) }}</span>
                    товарів у кошику
                </p>
            </div>

            <button type="button" class="cart-drawer__close-btn" data-cart-close aria-label="Закрити кошик">×</button>
        </div>

        <div class="cart-drawer__content" data-cart-drawer-content>
            @include('cart.partials.drawer-content', [
                'items' => $drawerItems,
                'summary' => $drawerSummary,
            ])
        </div>
    </aside>
</div>
