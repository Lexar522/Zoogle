@php
    use App\Services\ShopAnalytics;

    $metrics = $this->insights()->comparison($this->insightsRange());
    $format = fn (array $m, float $value): string => match (true) {
        $m['money'] => ShopAnalytics::formatMoney($value),
        $m['percent'] => rtrim(rtrim(number_format($value, 1, ',', ' '), '0'), ',').'%',
        default => number_format($value, 0, ',', ' '),
    };
@endphp

<x-filament-widgets::widget>
    @if ($metrics !== null)
        <section class="zs-compare">
            <h2 class="zs-section-title">Порівняно з попереднім періодом</h2>
            <div class="zs-compare__grid">
                @foreach ($metrics as $m)
                    @php($up = $m['delta'] !== null && $m['delta'] > 0)
                    @php($down = $m['delta'] !== null && $m['delta'] < 0)
                    <article class="zs-card zs-compare__item">
                        <div class="zs-compare__label">{{ $m['label'] }}</div>
                        <div class="zs-compare__value">{{ $format($m, $m['current']) }}</div>
                        <div class="zs-compare__foot">
                            @if ($m['delta'] === null)
                                <span class="zs-delta zs-delta--flat">{{ $m['current'] > 0 ? 'нове' : '—' }}</span>
                            @else
                                <span class="zs-delta {{ $up ? 'zs-delta--up' : ($down ? 'zs-delta--down' : 'zs-delta--flat') }}">
                                    {{ $up ? '▲' : ($down ? '▼' : '•') }} {{ number_format(abs($m['delta']), 1, ',', ' ') }}%
                                </span>
                            @endif
                            <span class="zs-compare__prev">було {{ $format($m, $m['previous']) }}</span>
                        </div>
                    </article>
                @endforeach
            </div>
        </section>
    @endif
</x-filament-widgets::widget>
