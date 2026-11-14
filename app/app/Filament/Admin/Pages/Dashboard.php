<?php

namespace App\Filament\Admin\Pages;

use Filament\Pages\Dashboard as BaseDashboard;
use Illuminate\Contracts\Support\Htmlable;

class Dashboard extends BaseDashboard
{
    protected static ?string $navigationLabel = 'Головна';

    protected static ?string $title = 'Головна';

    /**
     * Привітання у віджеті вже є заголовком сторінки.
     */
    public function getHeading(): string|Htmlable
    {
        return '';
    }
}
