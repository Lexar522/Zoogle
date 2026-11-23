<?php

namespace App\Filament\Admin\Widgets\Analytics;

use Filament\Widgets\Widget;

class AnalyticsHeatmapWidget extends Widget
{
    use ReadsAnalyticsInsights;

    protected static bool $isDiscovered = false;

    protected static bool $isLazy = false;

    protected string $view = 'filament.admin.widgets.analytics-heatmap';

    protected int|string|array $columnSpan = 'full';
}
