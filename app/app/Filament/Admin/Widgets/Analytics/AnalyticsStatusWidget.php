<?php

namespace App\Filament\Admin\Widgets\Analytics;

use App\Models\Order;
use Filament\Widgets\Widget;

class AnalyticsStatusWidget extends Widget
{
    use ReadsAnalyticsReport;

    private const COLORS = ['#007aff', '#34c759', '#ff9500', '#af52de', '#5ac8fa', '#ffcc00'];

    private const CANCELLED_COLOR = '#8e8e93';

    protected static bool $isDiscovered = false;

    protected static bool $isLazy = false;

    protected string $view = 'filament.admin.widgets.analytics-status';

    /**
     * @return list<array{label: string, value: int, percent: float, color: string}>
     */
    public function rows(): array
    {
        $report = $this->analyticsReport();
        $total = (int) $report['orders'] + (int) $report['cancelled_orders'];
        if ($total <= 0) {
            return [];
        }

        $cancelledLabel = Order::statusLabels()[Order::STATUS_CANCELLED] ?? null;
        $rows = [];
        foreach ($report['statuses']['labels'] as $index => $label) {
            $value = (int) ($report['statuses']['values'][$index] ?? 0);
            $rows[] = [
                'label' => (string) $label,
                'value' => $value,
                'percent' => round($value / $total * 100, 1),
                'color' => $label === $cancelledLabel ? self::CANCELLED_COLOR : self::COLORS[$index % count(self::COLORS)],
            ];
        }

        return $rows;
    }
}
