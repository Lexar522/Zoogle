{{-- Відображення обраних опцій товару (кольорові зразки + текстові бейджі), як у кошику. $item — рядок з resolveItems. --}}
@php
    $hasSwatchesOrBadges = ! empty($item['option_swatches'] ?? []) || ! empty($item['option_badges'] ?? []);
@endphp
@if ($hasSwatchesOrBadges)
    <div class="cart-drawer__options">
        @foreach ($item['option_swatches'] ?? [] as $option)
            <span class="cart-drawer__option-swatch" title="{{ $option['label'] }}">
                <span class="cart-drawer__option-swatch-dot" @if (! empty($option['color_hex'])) style="background: {{ $option['color_hex'] }};" @endif>
                    @if (! empty($option['swatch_image']))
                        <img src="{{ asset('storage/' . $option['swatch_image']) }}" alt="{{ $option['label'] }}">
                    @endif
                </span>
                <span class="cart-drawer__option-swatch-label">{{ $option['value_name'] }}</span>
            </span>
        @endforeach

        @foreach ($item['option_badges'] ?? [] as $option)
            <span class="cart-drawer__option-badge" title="{{ $option['label'] }}">{{ $option['label'] }}</span>
        @endforeach
    </div>
@elseif (! empty($item['option_labels'] ?? []))
    <div class="cart-drawer__options">
        @foreach ($item['option_labels'] as $label)
            <span class="cart-drawer__option-badge">{{ $label }}</span>
        @endforeach
    </div>
@endif
