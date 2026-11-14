@php
    use App\Filament\Admin\Widgets\Analytics\AnalyticsChartStyle;
    use App\Services\ShopAnalytics;
    use Filament\Support\Icons\Heroicon;

    $report = $this->report();
    $timezone = config('app.timezone');
    $from = $report['from']->timezone($timezone);
    $to = $report['to']->timezone($timezone);
    $number = fn (int|float $value): string => number_format((float) $value, 0, ',', ' ');
    $orders = (int) $report['orders'];
    $visitors = (int) $report['visitors'];
    $cssPath = resource_path('css/filament/site-statistics.css');
    $revenueSpark = AnalyticsChartStyle::sparkline($report['sales']['revenue']);

    $tiles = [
        [
            'label' => 'Відвідувачі',
            'value' => $number($visitors),
            'hint' => 'Нові: '.$number($report['new_visitors']).' · повернулись: '.$number($report['returning_visitors']),
            'tone' => AnalyticsChartStyle::BLUE,
            'icon' => Heroicon::OutlinedUsers,
            'series' => $report['traffic']['visitors'],
        ],
        [
            'label' => 'Замовлення',
            'value' => $number($orders),
            'hint' => 'Скасовано: '.$number($report['cancelled_orders']),
            'tone' => AnalyticsChartStyle::ORANGE,
            'icon' => Heroicon::OutlinedShoppingBag,
            'series' => $report['sales']['orders'],
        ],
        [
            'label' => 'Конверсія',
            'value' => $report['conversion'].'%',
            'hint' => 'Частка відвідувачів, які замовили',
            'tone' => AnalyticsChartStyle::GREEN,
            'icon' => Heroicon::OutlinedArrowTrendingUp,
            'series' => [],
        ],
        [
            'label' => 'Перегляди сторінок',
            'value' => $number($report['page_views']),
            'hint' => 'З них товарів і комплектів: '.$number($report['product_views']),
            'tone' => AnalyticsChartStyle::PURPLE,
            'icon' => Heroicon::OutlinedEye,
            'series' => $report['traffic']['views'],
        ],
        [
            'label' => 'Додали в кошик',
            'value' => $number($report['cart_adds']),
            'hint' => $report['view_to_cart'].'% від переглядів · в обране: '.$number($report['favorites']),
            'tone' => AnalyticsChartStyle::PINK,
            'icon' => Heroicon::OutlinedShoppingCart,
            'series' => [],
        ],
        [
            'label' => 'Пошуки',
            'value' => $number($report['searches']),
            'hint' => count($report['empty_search']) > 0
                ? 'Запитів без результату: '.count($report['empty_search'])
                : 'Усі запити знайшли товари',
            'tone' => AnalyticsChartStyle::MUTED,
            'icon' => Heroicon::OutlinedMagnifyingGlass,
            'series' => [],
        ],
    ];
@endphp

<x-filament-widgets::widget>
<div class="zs-hero" wire:poll.60s>
    @if (is_file($cssPath))
        <style>{!! file_get_contents($cssPath) !!}</style>
    @endif

    <section class="zs-card zs-summary">
        <div class="zs-summary__top">
            <div>
                <div class="zs-summary__period">
                    {{ $report['period_label'] ?? 'Період' }}
                    <span>{{ $from->format('d.m.Y') }} – {{ $to->format('d.m.Y') }}</span>
                </div>
                <div class="zs-summary__label">Виручка</div>
                <div class="zs-summary__value">{{ ShopAnalytics::formatMoney((float) $report['revenue']) }}</div>
                <div class="zs-summary__hint">
                    @if ($orders > 0)
                        Середній чек {{ ShopAnalytics::formatMoney((float) $report['average_order']) }}. Скасовані замовлення не враховано.
                    @else
                        За цей період замовлень ще немає.
                    @endif
                </div>
            </div>

            <div class="zs-live">
                <span class="zs-live__dot" aria-hidden="true"></span>
                <span class="zs-live__label">Зараз онлайн</span>
                <span class="zs-live__value">{{ (int) $report['online_now'] }}</span>
                <span class="zs-live__peak">Пік за період: {{ (int) $report['peak_online'] }}</span>
            </div>
        </div>

        @if ($revenueSpark !== null)
            <svg class="zs-summary__spark" viewBox="0 0 100 32" preserveAspectRatio="none" aria-hidden="true" style="--zs-tone: {{ AnalyticsChartStyle::GREEN }}">
                <path d="{{ $revenueSpark['area'] }}" class="zs-spark-area" />
                <path d="{{ $revenueSpark['line'] }}" class="zs-spark-line" vector-effect="non-scaling-stroke" />
            </svg>
        @endif
    </section>

    <div class="zs-tiles">
        @foreach ($tiles as $tile)
            @php($spark = AnalyticsChartStyle::sparkline($tile['series']))
            <article class="zs-card zs-tile" style="--zs-tone: {{ $tile['tone'] }}">
                <div class="zs-tile__label">
                    <span class="zs-icon"><x-filament::icon :icon="$tile['icon']" /></span>
                    {{ $tile['label'] }}
                </div>
                <div class="zs-tile__body">
                    <div class="zs-tile__value">{{ $tile['value'] }}</div>
                    @if ($spark !== null)
                        <svg class="zs-tile__spark" viewBox="0 0 100 32" preserveAspectRatio="none" aria-hidden="true">
                            <path d="{{ $spark['area'] }}" class="zs-spark-area" />
                            <path d="{{ $spark['line'] }}" class="zs-spark-line" vector-effect="non-scaling-stroke" />
                        </svg>
                    @endif
                </div>
                <div class="zs-tile__hint">{{ $tile['hint'] }}</div>
            </article>
        @endforeach
    </div>
</div>
</x-filament-widgets::widget>
