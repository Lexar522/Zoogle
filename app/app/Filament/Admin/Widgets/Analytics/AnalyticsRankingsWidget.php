<?php

namespace App\Filament\Admin\Widgets\Analytics;

use Filament\Widgets\Widget;

class AnalyticsRankingsWidget extends Widget
{
    use ReadsAnalyticsReport;

    protected static bool $isDiscovered = false;

    protected static bool $isLazy = false;

    protected string $view = 'filament.admin.widgets.analytics-rankings';

    protected int|string|array $columnSpan = 'full';

    /**
     * @return array<string, mixed>
     */
    public function report(): array
    {
        return $this->analyticsReport();
    }
}
