{{-- Рядок позиції на оформленні замовлення: та сама сітка й класи, що й у кошику (без видалення та зміни кількості). --}}
@php
    $isBundle = ($item['line_kind'] ?? 'product') === 'bundle';
    $title = (string) ($item['title'] ?? ($item['product']->title ?? 'Позиція'));
    $titleUrl = $item['title_url'] ?? null;
@endphp
<article class="cart-drawer__line cart-drawer__line--checkout @if ($isBundle) cart-drawer__line--bundle @endif">
    <div class="cart-drawer__line-media">
        @if ($item['photo'])
            <img src="{{ asset('storage/' . $item['photo']) }}" alt="{{ $title }}">
        @else
            <div class="cart-drawer__line-media-empty">Немає фото</div>
        @endif
    </div>

    <div class="cart-drawer__line-main cart-drawer__line-main--checkout">
        <div class="cart-drawer__line-body">
            <div class="cart-drawer__line-header cart-drawer__line-header--checkout">
                @if (filled($titleUrl))
                    <a href="{{ $titleUrl }}" class="cart-drawer__line-title">
                        {{ $title }}
                    </a>
                @else
                    <span class="cart-drawer__line-title">{{ $title }}</span>
                @endif
            </div>

            @if ($isBundle)
                <div class="cart-drawer__bundle-meta">
                    <span class="cart-drawer__bundle-badge">Комплект</span>
                    <span class="cart-drawer__bundle-meta-text">{{ count($item['bundle_items'] ?? []) }} товарів у складі</span>
                </div>
                @if (! empty($item['bundle_items'] ?? []))
                    <div class="cart-drawer__bundle-list">
                        @foreach ($item['bundle_items'] as $bundleItem)
                            <span class="cart-drawer__bundle-item">
                                {{ $bundleItem['title'] ?? 'Товар' }} × {{ (int) ($bundleItem['qty'] ?? 1) }}
                            </span>
                        @endforeach
                    </div>
                @endif
            @else
                @include('cart.partials.options-display', ['item' => $item])
            @endif
        </div>

        <div class="cart-drawer__line-aside">
            <div class="cart-drawer__line-pricing cart-drawer__line-pricing--checkout-aside">
                @if (($item['old_line_total'] ?? null) !== null && (float) ($item['old_line_total'] ?? 0) > (float) ($item['line_total'] ?? 0) + 0.001)
                    <span class="cart-drawer__unit-price cart-drawer__unit-price--old">
                        {{ number_format((float) $item['old_line_total'], 2, ',', ' ') }} ₴
                    </span>
                @endif
                <span class="cart-drawer__line-total">{{ number_format((float) $item['line_total'], 2, ',', ' ') }} ₴</span>
            </div>
            <div class="cart-drawer__line-actions cart-drawer__line-actions--checkout-aside">
                <span
                    class="cart-drawer__checkout-qty"
                    aria-label="{{ $isBundle ? 'Кількість комплектів' : 'Кількість товарів у позиції' }}"
                >{{ (int) $item['qty'] }}</span>
            </div>
        </div>
    </div>
</article>
