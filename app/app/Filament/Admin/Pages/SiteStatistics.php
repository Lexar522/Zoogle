<?php

namespace App\Filament\Admin\Pages;

use App\Filament\Admin\Widgets\Analytics\AnalyticsComparisonWidget;
use App\Filament\Admin\Widgets\Analytics\AnalyticsDeliveryWidget;
use App\Filament\Admin\Widgets\Analytics\AnalyticsFunnelWidget;
use App\Filament\Admin\Widgets\Analytics\AnalyticsHeatmapWidget;
use App\Filament\Admin\Widgets\Analytics\AnalyticsHeroWidget;
use App\Filament\Admin\Widgets\Analytics\AnalyticsLostDemandWidget;
use App\Filament\Admin\Widgets\Analytics\AnalyticsOnlineChart;
use App\Filament\Admin\Widgets\Analytics\AnalyticsRankingsWidget;
use App\Filament\Admin\Widgets\Analytics\AnalyticsSalesChart;
use App\Filament\Admin\Widgets\Analytics\AnalyticsSponsorshipWidget;
use App\Filament\Admin\Widgets\Analytics\AnalyticsStatusWidget;
use App\Filament\Admin\Widgets\Analytics\AnalyticsTrafficChart;
use App\Services\ShopAnalytics;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

class SiteStatistics extends Page
{
    use HasFiltersForm;

    protected static ?string $slug = 'statistics';

    protected static ?string $title = 'Статистика';

    protected static ?string $navigationLabel = 'Статистика';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static ?int $navigationSort = -1;

    public function mount(): void
    {
        if (! is_array($this->filters) || blank($this->filters['range'] ?? null)) {
            $this->filters['range'] = '7';
        }
    }

    /**
     * @return array<string>
     */
    public function getPageClasses(): array
    {
        return ['zoogle-stats-page'];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('resetAnalytics')
                ->label('Обнулити статистику')
                ->icon(Heroicon::OutlinedTrash)
                ->color('gray')
                ->outlined()
                ->requiresConfirmation()
                ->modalIconColor('danger')
                ->modalSubmitAction(fn (Action $action): Action => $action->color('danger'))
                ->modalHeading('Обнулити статистику візитів?')
                ->modalDescription('Зникнуть відвідувачі, перегляди, пошук, кошик, обране і графік онлайну. Замовлення та виручка залишаться.')
                ->modalSubmitActionLabel('Обнулити')
                ->action(function (): void {
                    ShopAnalytics::resetVisits();

                    Notification::make()
                        ->title('Статистику візитів обнулено')
                        ->body('Замовлення і виручка на місці.')
                        ->success()
                        ->send();

                    $this->redirect(static::getUrl());
                }),
        ];
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            View::make('filament.admin.statistics-toolbar'),
            Grid::make([
                'default' => 1,
                'xl' => 2,
            ])->schema(fn (): array => $this->getWidgetsSchemaComponents($this->statisticsWidgets())),
        ]);
    }

    /**
     * @return list<class-string>
     */
    private function statisticsWidgets(): array
    {
        return [
            AnalyticsHeroWidget::class,
            AnalyticsComparisonWidget::class,
            AnalyticsSalesChart::class,
            AnalyticsTrafficChart::class,
            AnalyticsFunnelWidget::class,
            AnalyticsStatusWidget::class,
            AnalyticsHeatmapWidget::class,
            AnalyticsDeliveryWidget::class,
            AnalyticsLostDemandWidget::class,
            AnalyticsOnlineChart::class,
            AnalyticsSponsorshipWidget::class,
            AnalyticsRankingsWidget::class,
        ];
    }

    public function getTitle(): string|Htmlable
    {
        return 'Статистика сайту';
    }
}
