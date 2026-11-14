<?php

namespace App\Filament\Admin\Resources\Orders\Pages;

use App\Filament\Admin\Resources\Orders\OrderResource;
use App\Filament\Admin\Resources\Orders\Widgets\OrdersSummaryWidget;
use App\Models\Order;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListOrders extends ListRecords
{
    protected static string $resource = OrderResource::class;

    /**
     * @return array<string>
     */
    public function getPageClasses(): array
    {
        return [...parent::getPageClasses(), 'zoogle-orders-page'];
    }

    protected function getHeaderActions(): array
    {
        return [];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            OrdersSummaryWidget::class,
        ];
    }

    /**
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        $counts = Order::query()
            ->toBase()
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $tabs = ['all' => Tab::make('Усі')];

        foreach (Order::statusLabels() as $status => $label) {
            $tabs[$status] = Tab::make($label)
                ->badge((int) ($counts[$status] ?? 0) ?: null)
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', $status));
        }

        $issues = Order::query()->whereNotNull('payment_issue')->count();
        if ($issues > 0) {
            $tabs['payment_issue'] = Tab::make('Проблеми з оплатою')
                ->badge($issues)
                ->badgeColor('danger')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->whereNotNull('payment_issue'));
        }

        return $tabs;
    }
}
