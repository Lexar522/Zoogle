@php
    $isBundle = ($item['line_kind'] ?? 'product') === 'bundle';
    $title = (string) ($item['title'] ?? ($item['product']->title ?? 'Позиція'));
    $titleUrl = $item['title_url'] ?? null;
@endphp
<article class="cart-drawer__line @if ($isBundle) cart-drawer__line--bundle @endif @if (filled($titleUrl)) cart-drawer__line--linked @endif" data-cart-line-key="{{ $item['key'] }}">
    <div class="cart-drawer__line-media">
        @if ($item['photo'])
            <img src="{{ asset('storage/' . $item['photo']) }}" alt="{{ $title }}">
        @else
            <div class="cart-drawer__line-media-empty">Немає фото</div>
        @endif
    </div>

    <div class="cart-drawer__line-main">
        <div class="cart-drawer__line-header">
            @if (filled($titleUrl))
                <a href="{{ $titleUrl }}" class="cart-drawer__line-title">{{ $title }}</a>
            @else
                <span class="cart-drawer__line-title">{{ $title }}</span>
            @endif
            <form method="POST" action="{{ route('cart.destroy', $item['key']) }}" class="cart-drawer__remove-form" data-cart-remove-form>
                @csrf
                @method('DELETE')
                <button type="submit" class="cart-drawer__remove-btn" aria-label="Видалити з кошика">×</button>
            </form>
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

        <div class="cart-drawer__line-pricing">
            @if (($item['old_line_total'] ?? null) !== null && (float) ($item['old_line_total'] ?? 0) > (float) ($item['line_total'] ?? 0) + 0.001)
                <span class="cart-drawer__unit-price cart-drawer__unit-price--old">
                    {{ number_format((float) $item['old_line_total'], 2, ',', ' ') }} ₴
                </span>
            @endif
            <span
                class="cart-drawer__line-total"
                data-cart-animated-total
                data-cart-line-total="{{ number_format((float) $item['line_total'], 2, '.', '') }}"
            >{{ number_format((float) $item['line_total'], 2, ',', ' ') }} ₴</span>
        </div>

        <div class="cart-drawer__line-actions">
            <form method="POST" action="{{ route('cart.update', $item['key']) }}" class="cart-drawer__qty-form" data-cart-update-form>
                @csrf
                @method('PATCH')
                <button type="button" class="cart-drawer__qty-btn" data-cart-qty-step="-1" aria-label="Зменшити кількість">-</button>
                <input
                    type="number"
                    name="qty"
                    min="1"
                    max="999"
                    value="{{ (int) $item['qty'] }}"
                    class="cart-drawer__qty-input"
                    data-cart-qty-input
                    aria-label="{{ $isBundle ? 'Кількість комплектів' : 'Кількість товару' }}"
                >
                <button type="button" class="cart-drawer__qty-btn" data-cart-qty-step="1" aria-label="Збільшити кількість">+</button>
                <button type="submit" class="visually-hidden">Оновити кількість</button>
            </form>
        </div>
    </div>
    @if (filled($titleUrl))
        <a
            href="{{ $titleUrl }}"
            class="cart-drawer__line-hit"
            tabindex="-1"
            aria-hidden="true"
        ></a>
    @endif
</article>
