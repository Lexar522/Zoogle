<?php

namespace App\Filament\Admin\Widgets\Analytics;

use Filament\Widgets\ChartWidget;

class AnalyticsOnlineChart extends ChartWidget
{
    use ReadsAnalyticsReport;

    protected static bool $isDiscovered = false;

    protected static bool $isLazy = false;

    protected ?string $pollingInterval = '60s';

    protected ?string $heading = 'Людей на сайті одночасно';

    protected ?string $maxHeight = '220px';

    protected int|string|array $columnSpan = 'full';

    public function getDescription(): ?string
    {
        return $this->grainCaption().'. Онлайн означає активність за останні 5 хвилин.';
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getData(): array
    {
        $online = $this->analyticsReport()['online'];

        return [
            'labels' => $online['labels'],
            'datasets' => [
                array_replace(AnalyticsChartStyle::line(AnalyticsChartStyle::PINK, $online['counts']), [
                    'label' => 'Людей онлайн',
                ]),
            ],
        ];
    }

    protected function getOptions(): array
    {
        return AnalyticsChartStyle::options([
            'plugins' => ['legend' => ['display' => false]],
        ]);
    }
}
