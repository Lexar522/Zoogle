<?php

namespace App\Filament\Admin\Widgets\Analytics;

use Filament\Widgets\Widget;

class AnalyticsComparisonWidget extends Widget
{
    use ReadsAnalyticsInsights;

    protected static bool $isDiscovered = false;

    protected static bool $isLazy = false;

    protected string $view = 'filament.admin.widgets.analytics-comparison';

    protected int|string|array $columnSpan = 'full';
}
