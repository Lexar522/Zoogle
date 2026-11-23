@php
    $data = $this->insights()->deliveryAndPayment($this->insightsRange());
    $colors = ['#007aff', '#34c759', '#ff9500', '#af52de', '#5ac8fa', '#ff2d55'];
    $blocks = [
        ['title' => 'Доставка', 'rows' => $data['delivery']],
        ['title' => 'Спосіб оплати', 'rows' => $data['payment']],
    ];
@endphp

<x-filament-widgets::widget>
    <x-filament::section
        heading="Доставка та оплата"
        description="Як покупці отримують і оплачують замовлення (без скасованих)."
    >
        @if ($data['total'] === 0)
            <div class="zs-empty">Немає замовлень за цей період.</div>
        @else
            @foreach ($blocks as $block)
                <div class="zs-split">
                    <div class="zs-split__title">{{ $block['title'] }}</div>
                    <div class="zs-stack" role="img" aria-label="{{ $block['title'] }}">
                        @foreach ($block['rows'] as $row)
                            <span style="width: {{ $row['percent'] }}%; background: {{ $colors[$loop->index % count($colors)] }}"></span>
                        @endforeach
                    </div>
                    <ul class="zs-legend">
                        @foreach ($block['rows'] as $row)
                            <li>
                                <span class="zs-legend__dot" style="background: {{ $colors[$loop->index % count($colors)] }}"></span>
                                <span class="zs-legend__label">{{ $row['label'] }}</span>
                                <span class="zs-legend__value">{{ $row['value'] }}</span>
                                <span class="zs-legend__percent">{{ $row['percent'] }}%</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endforeach
            <div class="zs-split__note">Уже оплачено: <b>{{ $data['paid_percent'] }}%</b> замовлень</div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
