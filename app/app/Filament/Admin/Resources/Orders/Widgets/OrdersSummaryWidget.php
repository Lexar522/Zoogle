<?php

namespace App\Filament\Admin\Resources\Orders\Widgets;

use App\Filament\Admin\Resources\Orders\OrderResource;
use App\Models\Order;
use App\Services\ShopAnalytics;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\Widget;

class OrdersSummaryWidget extends Widget
{
    protected static bool $isDiscovered = false;

    protected static bool $isLazy = false;

    protected string $view = 'filament.admin.orders.orders-summary';

    protected int|string|array $columnSpan = 'full';

    /**
     * @return list<array{label: string, value: string, hint: string, tone: string, icon: Heroicon, url: ?string}>
     */
    public function tiles(): array
    {
        $counts = Order::query()
            ->toBase()
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');
        $count = fn (string $status): int => (int) ($counts[$status] ?? 0);

        $today = Order::query()
            ->where('placed_at', '>=', now()->startOfDay())
            ->where('status', '!=', Order::STATUS_CANCELLED)
            ->toBase()
            ->selectRaw('count(*) as orders, coalesce(sum(total), 0) as revenue')
            ->first();

        return [
            [
                'label' => 'Нові',
                'value' => (string) $count(Order::STATUS_NEW),
                'hint' => 'Чекають на обробку',
                'tone' => '#007aff',
                'icon' => Heroicon::OutlinedInboxArrowDown,
                'url' => $this->tabUrl(Order::STATUS_NEW),
            ],
            [
                'label' => 'В обробці',
                'value' => (string) $count(Order::STATUS_PROCESSING),
                'hint' => 'Оплачені, ще не в роботі: '.$count(Order::STATUS_PAID),
                'tone' => '#ff9500',
                'icon' => Heroicon::OutlinedClock,
                'url' => $this->tabUrl(Order::STATUS_PROCESSING),
            ],
            [
                'label' => 'Відправлені',
                'value' => (string) $count(Order::STATUS_SHIPPED),
                'hint' => 'Зараз у дорозі',
                'tone' => '#af52de',
                'icon' => Heroicon::OutlinedTruck,
                'url' => $this->tabUrl(Order::STATUS_SHIPPED),
            ],
            [
                'label' => 'Сьогодні',
                'value' => ShopAnalytics::formatMoney((float) ($today->revenue ?? 0)),
                'hint' => 'Замовлень: '.(int) ($today->orders ?? 0).', без скасованих',
                'tone' => '#34c759',
                'icon' => Heroicon::OutlinedBanknotes,
                'url' => null,
            ],
        ];
    }

    private function tabUrl(string $tab): string
    {
        return OrderResource::getUrl('index', ['tab' => $tab]);
    }
}
