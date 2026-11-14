<?php

namespace App\Filament\Admin\Widgets\Analytics;

use Filament\Widgets\Widget;

class AnalyticsHeroWidget extends Widget
{
    use ReadsAnalyticsReport;

    protected static bool $isDiscovered = false;

    protected static bool $isLazy = false;

    protected string $view = 'filament.admin.widgets.analytics-hero';

    protected int | string | array $columnSpan = 'full';

    /**
     * @return array<string, mixed>
     */
    public function report(): array
    {
        return $this->analyticsReport();
    }
}
