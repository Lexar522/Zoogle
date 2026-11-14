<?php

namespace App\Filament\Admin\Widgets\Analytics;

use Filament\Widgets\ChartWidget;

class AnalyticsTrafficChart extends ChartWidget
{
    use ReadsAnalyticsReport;

    protected static bool $isDiscovered = false;

    protected static bool $isLazy = false;

    protected ?string $pollingInterval = null;

    protected ?string $heading = 'Відвідувачі і перегляди';

    protected ?string $maxHeight = '280px';

    public function getDescription(): ?string
    {
        return 'Скільки людей зайшло і скільки сторінок вони відкрили. '.$this->grainCaption().'.';
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getData(): array
    {
        $traffic = $this->analyticsReport()['traffic'];

        return [
            'labels' => $traffic['labels'],
            'datasets' => [
                array_replace(AnalyticsChartStyle::line(AnalyticsChartStyle::BLUE, $traffic['visitors']), [
                    'label' => 'Відвідувачі',
                ]),
                array_replace(AnalyticsChartStyle::line(AnalyticsChartStyle::PURPLE, $traffic['views'], false), [
                    'label' => 'Перегляди сторінок',
                ]),
            ],
        ];
    }

    protected function getOptions(): array
    {
        return AnalyticsChartStyle::options();
    }
}
