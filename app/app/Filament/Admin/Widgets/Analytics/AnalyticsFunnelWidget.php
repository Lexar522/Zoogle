<?php

namespace App\Filament\Admin\Widgets\Analytics;

use Filament\Widgets\Widget;

class AnalyticsFunnelWidget extends Widget
{
    use ReadsAnalyticsReport;

    protected static bool $isDiscovered = false;

    protected static bool $isLazy = false;

    protected string $view = 'filament.admin.widgets.analytics-funnel';

    /**
     * @return list<array{label: string, value: int, width: float, step_percent: ?float}>
     */
    public function steps(): array
    {
        $funnel = $this->analyticsReport()['funnel'];
        $values = array_map('intval', $funnel['values']);
        $max = max([1, ...$values]);
        $steps = [];

        foreach ($funnel['labels'] as $index => $label) {
            $value = $values[$index] ?? 0;
            $previous = $index > 0 ? ($values[$index - 1] ?? 0) : 0;
            // Замовлення рахуються окремо від людей, тому частка понад 100% нічого б не пояснила.
            $stepPercent = $previous > 0 && $value <= $previous ? round($value / $previous * 100, 1) : null;

            $steps[] = [
                'label' => (string) $label,
                'value' => $value,
                'width' => round($value / $max * 100, 1),
                'step_percent' => $stepPercent,
            ];
        }

        return $steps;
    }
}
