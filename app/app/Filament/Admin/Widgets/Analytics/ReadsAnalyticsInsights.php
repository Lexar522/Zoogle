<?php

namespace App\Filament\Admin\Widgets\Analytics;

use App\Services\ShopAnalyticsInsights;

/**
 * Додаткові блоки статистики беруть той самий період, що обрано вгорі сторінки.
 */
trait ReadsAnalyticsInsights
{
    use ReadsAnalyticsReport;

    protected function insights(): ShopAnalyticsInsights
    {
        return app(ShopAnalyticsInsights::class);
    }

    protected function insightsRange(): string
    {
        return (string) ($this->analyticsReport()['range'] ?? '7');
    }
}
