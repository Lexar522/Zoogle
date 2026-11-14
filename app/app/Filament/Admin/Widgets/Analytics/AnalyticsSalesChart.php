<?php

namespace App\Filament\Admin\Widgets\Analytics;

use Filament\Widgets\ChartWidget;

class AnalyticsSalesChart extends ChartWidget
{
    use ReadsAnalyticsReport;

    protected static bool $isDiscovered = false;

    protected static bool $isLazy = false;

    protected ?string $pollingInterval = null;

    protected ?string $heading = 'Виручка і замовлення';

    protected ?string $maxHeight = '280px';

    public function getDescription(): ?string
    {
        return 'Скасовані замовлення не входять у суму. '.$this->grainCaption().'.';
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getData(): array
    {
        $sales = $this->analyticsReport()['sales'];

        return [
            'labels' => $sales['labels'],
            'datasets' => [
                array_replace(AnalyticsChartStyle::line(AnalyticsChartStyle::GREEN, $sales['revenue']), [
                    'label' => 'Виручка, ₴',
                    'yAxisID' => 'y',
                ]),
                array_replace(AnalyticsChartStyle::line(AnalyticsChartStyle::ORANGE, $sales['orders'], false), [
                    'label' => 'Замовлення',
                    'yAxisID' => 'y1',
                ]),
            ],
        ];
    }

    protected function getOptions(): array
    {
        return AnalyticsChartStyle::options([
            'scales' => [
                'y' => [
                    'position' => 'left',
                ],
                // Шкала замовлень схована: точні числа видно в підказці, а друга вісь лише заплутує.
                'y1' => [
                    'display' => false,
                    'position' => 'right',
                    'beginAtZero' => true,
                ],
            ],
        ]);
    }
}
