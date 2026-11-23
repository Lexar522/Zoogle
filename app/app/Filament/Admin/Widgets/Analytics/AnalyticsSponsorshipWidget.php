<?php

namespace App\Filament\Admin\Widgets\Analytics;

use App\Models\ShopSponsorshipSetting;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Schema;

class AnalyticsSponsorshipWidget extends Widget
{
    use ReadsAnalyticsInsights;

    protected static bool $isDiscovered = false;

    protected static bool $isLazy = false;

    protected string $view = 'filament.admin.widgets.analytics-sponsorship';

    public static function canView(): bool
    {
        return Schema::hasTable('sponsorships') && ShopSponsorshipSetting::isEnabled();
    }
}
