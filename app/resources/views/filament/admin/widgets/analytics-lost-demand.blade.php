@php
    use App\Filament\Admin\Resources\Products\ProductResource;

    $rows = $this->insights()->lostDemand($this->insightsRange());
    $top = max([1, ...array_map(fn (array $r): int => $r['cart_adds'] * 3 + $r['views'], $rows)]);
@endphp

<x-filament-widgets::widget>
    <x-filament::section
        heading="Втрачений попит"
        description="Товари, які дивились чи клали в кошик, але зараз їх не купити. Варто дозамовити або повернути на вітрину."
    >
        @if ($rows === [])
            <div class="zs-empty">Усе, що цікавить покупців, можна купити.</div>
        @else
            <ol class="zs-rank__list" style="--zs-tone: #ff9500">
                @foreach ($rows as $row)
                    <li>
                        <div class="zs-rank__row">
                            <span class="zs-rank__n">{{ $loop->iteration }}</span>
                            <a class="zs-rank__name zs-lost__link" href="{{ ProductResource::getUrl('edit', ['record' => $row['product_id']]) }}" title="{{ $row['label'] }}">{{ $row['label'] }}</a>
                            <span class="zs-rank__meta">{{ $row['views'] }} перегл. · {{ $row['cart_adds'] }} у кошик</span>
                        </div>
                        <div class="zs-lost__reason">{{ $row['reason'] }}</div>
                        <div class="zs-bar zs-bar--thin"><span style="width: {{ round(($row['cart_adds'] * 3 + $row['views']) / $top * 100, 1) }}%"></span></div>
                    </li>
                @endforeach
            </ol>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
