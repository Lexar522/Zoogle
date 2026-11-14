<?php

namespace App\Filament\Admin\Widgets\Analytics;

use App\Services\ShopAnalytics;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

trait ReadsAnalyticsReport
{
    use InteractsWithPageFilters;

    /**
     * @return array<string, mixed>
     */
    protected function analyticsReport(): array
    {
        $range = $this->pageFilters['range'] ?? null;

        return ShopAnalytics::report(is_string($range) ? $range : null);
    }

    public function getColor(): string
    {
        return 'gray';
    }

    protected function grainCaption(): string
    {
        return match ($this->analyticsReport()['grain'] ?? 'day') {
            'hour' => 'По годинах',
            'month' => 'По місяцях',
            default => 'По днях',
        };
    }
}
