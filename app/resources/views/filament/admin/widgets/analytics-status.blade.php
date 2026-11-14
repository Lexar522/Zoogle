@php($rows = $this->rows())

<x-filament-widgets::widget>
    <x-filament::section
        heading="Статуси замовлень"
        description="На якому етапі замовлення за обраний період."
    >
        @if ($rows === [])
            <div class="zs-empty">Немає замовлень за цей період.</div>
        @else
            <div class="zs-stack" role="img" aria-label="Розподіл замовлень за статусами">
                @foreach ($rows as $row)
                    <span style="width: {{ $row['percent'] }}%; background: {{ $row['color'] }}"></span>
                @endforeach
            </div>
            <ul class="zs-legend">
                @foreach ($rows as $row)
                    <li>
                        <span class="zs-legend__dot" style="background: {{ $row['color'] }}"></span>
                        <span class="zs-legend__label">{{ $row['label'] }}</span>
                        <span class="zs-legend__value">{{ $row['value'] }}</span>
                        <span class="zs-legend__percent">{{ $row['percent'] }}%</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
