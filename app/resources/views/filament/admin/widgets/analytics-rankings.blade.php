@php
    use App\Filament\Admin\Widgets\Analytics\AnalyticsChartStyle;
    use App\Services\ShopAnalytics;
    use Filament\Support\Icons\Heroicon;

    $report = $this->report();
    $countRow = fn (string $key, string $unit = ''): \Closure => fn (array $row): array => [
        'name' => $row['label'],
        'meta' => (int) ($row[$key] ?? 0).$unit,
        'value' => (float) ($row[$key] ?? 0),
    ];
    $moneyRow = fn (array $row): array => [
        'name' => $row['label'],
        'meta' => $row['qty'].' шт · '.ShopAnalytics::formatMoney((float) $row['revenue']),
        'value' => (float) $row['revenue'],
    ];

    $sections = [
        ['title' => 'Що купують', 'tone' => AnalyticsChartStyle::GREEN, 'icon' => Heroicon::OutlinedShoppingBag, 'empty' => 'Немає продажів за цей період.', 'rows' => array_map($moneyRow, $report['top_sold'])],
        ['title' => 'Категорії за виручкою', 'tone' => AnalyticsChartStyle::ORANGE, 'icon' => Heroicon::OutlinedSquares2x2, 'rows' => array_map($moneyRow, $report['top_categories'])],
        ['title' => 'Що переглядають', 'tone' => AnalyticsChartStyle::BLUE, 'icon' => Heroicon::OutlinedEye, 'rows' => array_map($countRow('views'), $report['top_viewed'])],
        ['title' => 'Додають у кошик', 'tone' => AnalyticsChartStyle::PINK, 'icon' => Heroicon::OutlinedShoppingCart, 'rows' => array_map($countRow('adds'), $report['top_cart'])],
        ['title' => 'Додають в обране', 'tone' => AnalyticsChartStyle::PURPLE, 'icon' => Heroicon::OutlinedHeart, 'rows' => array_map($countRow('adds'), $report['top_favorites'])],
        ['title' => 'Що шукають', 'tone' => AnalyticsChartStyle::MUTED, 'icon' => Heroicon::OutlinedMagnifyingGlass, 'rows' => array_map($countRow('searches'), $report['top_search'])],
        ['title' => 'Пошук без результатів', 'tone' => AnalyticsChartStyle::PINK, 'icon' => Heroicon::OutlinedExclamationCircle, 'hint' => 'Цих товарів шукають, але не знаходять.', 'rows' => array_map($countRow('searches'), $report['empty_search'])],
    ];
@endphp

<x-filament-widgets::widget>
<div class="zs-rankings">
    <h2 class="zs-section-title">Рейтинги</h2>
    <div class="zs-rank-grid">
        @foreach ($sections as $section)
            @php($top = max([1, ...array_column($section['rows'], 'value')]))
            <article class="zs-card zs-rank" style="--zs-tone: {{ $section['tone'] }}">
                <header class="zs-rank__title">
                    <span class="zs-icon"><x-filament::icon :icon="$section['icon']" /></span>
                    {{ $section['title'] }}
                </header>
                @if (filled($section['hint'] ?? null) && $section['rows'] !== [])
                    <p class="zs-rank__hint">{{ $section['hint'] }}</p>
                @endif
                @if ($section['rows'] === [])
                    <div class="zs-empty">{{ $section['empty'] ?? 'Ще немає даних за цей період.' }}</div>
                @else
                    <ol class="zs-rank__list">
                        @foreach ($section['rows'] as $row)
                            <li>
                                <div class="zs-rank__row">
                                    <span class="zs-rank__n">{{ $loop->iteration }}</span>
                                    <span class="zs-rank__name" title="{{ $row['name'] }}">{{ $row['name'] }}</span>
                                    <span class="zs-rank__meta">{{ $row['meta'] }}</span>
                                </div>
                                <div class="zs-bar zs-bar--thin"><span style="width: {{ round($row['value'] / $top * 100, 1) }}%"></span></div>
                            </li>
                        @endforeach
                    </ol>
                @endif
            </article>
        @endforeach
    </div>
</div>
</x-filament-widgets::widget>
