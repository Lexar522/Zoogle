@php
    use App\Services\ShopAnalyticsInsights;

    $heatmap = $this->insights()->activityHeatmap($this->insightsRange());
    $max = max(1, $heatmap['max']);
@endphp

<x-filament-widgets::widget>
    <x-filament::section
        heading="Коли заходять і купують"
        description="Перегляди сторінок за днями тижня й годинами. Чим темніше — тим більше людей на сайті."
    >
        @if ($heatmap['max'] === 0)
            <div class="zs-empty">Ще немає відвідувань за цей період.</div>
        @else
            <div class="zs-heat-tips">
                <span class="zs-heat-tip"><b>Найактивніший день:</b> {{ $heatmap['busiest_day'] }}</span>
                <span class="zs-heat-tip"><b>Найбільше людей:</b> {{ $heatmap['busiest_hour'] }}</span>
                @if ($heatmap['order_hour'])
                    <span class="zs-heat-tip"><b>Найчастіше замовляють:</b> {{ $heatmap['order_hour'] }}</span>
                @endif
            </div>
            <div class="zs-heat" role="img" aria-label="Активність відвідувачів за днями тижня й годинами">
                <span></span>
                @foreach (range(0, 23) as $hour)
                    <span class="zs-heat__hour">{{ $hour % 3 === 0 ? $hour : '' }}</span>
                @endforeach
                @foreach ($heatmap['cells'] as $day => $hours)
                    <span class="zs-heat__day">{{ ShopAnalyticsInsights::WEEKDAYS_SHORT[$day] }}</span>
                    @foreach ($hours as $hour => $count)
                        <span class="zs-heat__cell" style="--zs-heat: {{ $count > 0 ? round(0.12 + 0.88 * $count / $max, 2) : 0 }}" title="{{ ShopAnalyticsInsights::WEEKDAYS[$day] }}, {{ sprintf('%02d:00', $hour) }} — {{ $count }}"></span>
                    @endforeach
                @endforeach
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
