<x-filament-widgets::widget>
    <div class="zo-tiles" wire:poll.60s>
        @foreach ($this->tiles() as $tile)
            <{{ $tile['url'] ? 'a' : 'div' }}
                @if ($tile['url']) href="{{ $tile['url'] }}" wire:navigate @endif
                class="zo-card zo-tile @if ($tile['url']) zo-tile--link @endif"
                style="--zo-tone: {{ $tile['tone'] }}"
            >
                <div class="zo-tile__label">
                    <span class="zo-icon"><x-filament::icon :icon="$tile['icon']" /></span>
                    {{ $tile['label'] }}
                </div>
                <div class="zo-tile__value">{{ $tile['value'] }}</div>
                <div class="zo-tile__hint">{{ $tile['hint'] }}</div>
            </{{ $tile['url'] ? 'a' : 'div' }}>
        @endforeach
    </div>
</x-filament-widgets::widget>
