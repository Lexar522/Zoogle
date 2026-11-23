<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Product;
use App\Models\ShopAnalyticsEvent;
use App\Models\ShopSponsorshipSetting;
use App\Models\Sponsorship;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * Додаткові блоки сторінки «Статистика»: порівняння з минулим періодом, активність за днями й годинами,
 * доставка й оплата, втрачений попит, віртуальна опіка. Період — той самий, що обрано на сторінці.
 */
class ShopAnalyticsInsights
{
    /** @var list<string> */
    public const WEEKDAYS = ['Понеділок', 'Вівторок', 'Середа', 'Четвер', "П'ятниця", 'Субота', 'Неділя'];

    /** @var list<string> */
    public const WEEKDAYS_SHORT = ['Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб', 'Нд'];

    private const LOST_DEMAND_LIMIT = 8;

    /** Сайт зберігає час в UTC, а власник магазину дивиться години й дні за Києвом. */
    private const SHOP_TIMEZONE = 'Europe/Kyiv';

    /** @var array<string, string> */
    private const PAYMENT_LABELS = [
        'online' => 'Онлайн',
        'cod' => 'При отриманні',
    ];

    /**
     * Ключові показники поточного періоду проти такого ж відрізку перед ним. null — для «Весь час».
     *
     * @return list<array{key: string, label: string, current: float, previous: float, delta: ?float, money: bool, percent: bool}>|null
     */
    public function comparison(string $range): ?array
    {
        if ($range === 'all') {
            return null;
        }

        [$from, $to] = ShopAnalytics::period($range);
        $previousTo = $from->copy()->subSecond();
        $previousFrom = $previousTo->copy()->subSeconds($from->diffInSeconds($to));

        $current = $this->totals($from, $to);
        $previous = $this->totals($previousFrom, $previousTo);

        $metrics = [
            ['key' => 'visitors', 'label' => 'Відвідувачі', 'money' => false, 'percent' => false],
            ['key' => 'orders', 'label' => 'Замовлення', 'money' => false, 'percent' => false],
            ['key' => 'revenue', 'label' => 'Виручка', 'money' => true, 'percent' => false],
            ['key' => 'average_order', 'label' => 'Середній чек', 'money' => true, 'percent' => false],
            ['key' => 'conversion', 'label' => 'Конверсія', 'money' => false, 'percent' => true],
        ];

        return array_map(fn (array $metric): array => [
            ...$metric,
            'current' => $current[$metric['key']],
            'previous' => $previous[$metric['key']],
            'delta' => $this->delta($current[$metric['key']], $previous[$metric['key']]),
        ], $metrics);
    }

    /**
     * Перегляди сторінок за днем тижня (0 — понеділок) і годиною; найактивніший день і година, година замовлень.
     *
     * @return array{cells: array<int, array<int, int>>, max: int, busiest_day: ?string, busiest_hour: ?string, order_hour: ?string}
     */
    public function activityHeatmap(string $range): array
    {
        [$from, $to] = ShopAnalytics::period($range);
        $cells = array_fill(0, 7, array_fill(0, 24, 0));

        ShopAnalyticsEvent::query()
            ->where('type', ShopAnalyticsEvent::TYPE_PAGE)
            ->whereBetween('occurred_at', [$from, $to])
            ->select(['id', 'occurred_at'])
            ->lazyById(1000)
            ->each(function (ShopAnalyticsEvent $event) use (&$cells): void {
                $at = Carbon::parse($event->occurred_at)->timezone(self::SHOP_TIMEZONE);
                $cells[$at->dayOfWeekIso - 1][$at->hour]++;
            });

        $byDay = array_map('array_sum', $cells);
        $byHour = array_map(fn (int $hour): int => array_sum(array_column($cells, $hour)), range(0, 23));
        $max = max(array_map('max', $cells));

        $orderHours = array_fill(0, 24, 0);
        foreach ($this->ordersBetween($from, $to)->get(['placed_at', 'created_at', 'status']) as $order) {
            if ((string) $order->status !== Order::STATUS_CANCELLED) {
                $orderHours[Carbon::parse($order->placed_at ?? $order->created_at)->timezone(self::SHOP_TIMEZONE)->hour]++;
            }
        }

        return [
            'cells' => $cells,
            'max' => $max,
            'busiest_day' => max($byDay) > 0 ? self::WEEKDAYS[array_search(max($byDay), $byDay, true)] : null,
            'busiest_hour' => max($byHour) > 0 ? $this->hourLabel((int) array_search(max($byHour), $byHour, true)) : null,
            'order_hour' => max($orderHours) > 0 ? $this->hourLabel((int) array_search(max($orderHours), $orderHours, true)) : null,
        ];
    }

    /**
     * Частки способів доставки та оплати серед нескасованих замовлень періоду.
     *
     * @return array{delivery: list<array{label: string, value: int, percent: float}>, payment: list<array{label: string, value: int, percent: float}>, paid_percent: float, total: int}
     */
    public function deliveryAndPayment(string $range): array
    {
        [$from, $to] = ShopAnalytics::period($range);
        $orders = $this->ordersBetween($from, $to)
            ->where('status', '!=', Order::STATUS_CANCELLED)
            ->get(['delivery_type', 'checkout_payment_method', 'payment_status']);

        $deliveryLabels = Order::deliveryTypeLabels();

        return [
            'delivery' => $this->shares($orders->map(fn (Order $order): string => $deliveryLabels[$order->delivery_type] ?? 'Не вказано')),
            'payment' => $this->shares($orders->map(fn (Order $order): string => self::PAYMENT_LABELS[(string) $order->checkout_payment_method] ?? 'Не вказано')),
            'paid_percent' => $orders->isEmpty() ? 0.0 : round($orders->where('payment_status', 'paid')->count() / $orders->count() * 100, 1),
            'total' => $orders->count(),
        ];
    }

    /**
     * Товари, які переглядали чи клали в кошик, але зараз їх не купити: немає в наявності або приховані.
     *
     * @return list<array{product_id: int, label: string, views: int, cart_adds: int, reason: string}>
     */
    public function lostDemand(string $range): array
    {
        [$from, $to] = ShopAnalytics::period($range);

        $interest = ShopAnalyticsEvent::query()
            ->whereIn('type', [ShopAnalyticsEvent::TYPE_PRODUCT_VIEW, ShopAnalyticsEvent::TYPE_CART])
            ->whereBetween('occurred_at', [$from, $to])
            ->whereNotNull('subject_id')
            ->selectRaw('subject_id, type, label, COUNT(DISTINCT visitor_key) as people')
            ->groupBy('subject_id', 'type', 'label')
            ->get()
            ->groupBy('subject_id');
        if ($interest->isEmpty()) {
            return [];
        }

        $products = Product::query()
            ->whereIn('id', $interest->keys())
            ->where(fn (Builder $query) => $query
                ->where('is_available', false)
                ->orWhere('stock_status', Product::STOCK_OUT_OF_STOCK))
            ->get(['id', 'title', 'is_available', 'stock_status']);

        return $products
            ->map(function (Product $product) use ($interest): ?array {
                // Кошик комплекту пишеться тим самим типом із номером комплекту — беремо лише події з назвою цього товару
                $rows = $interest[$product->id]->filter(fn ($row): bool => $row->type === ShopAnalyticsEvent::TYPE_PRODUCT_VIEW
                    || (string) $row->label === (string) $product->getRawOriginal('title'));
                $views = (int) $rows->where('type', ShopAnalyticsEvent::TYPE_PRODUCT_VIEW)->sum('people');
                $cartAdds = (int) $rows->where('type', ShopAnalyticsEvent::TYPE_CART)->sum('people');
                if ($views + $cartAdds === 0) {
                    return null;
                }

                return [
                    'product_id' => (int) $product->id,
                    'label' => (string) mt($product->title),
                    'views' => $views,
                    'cart_adds' => $cartAdds,
                    'reason' => ! $product->is_available ? 'Прихований з вітрини' : 'Немає в наявності',
                ];
            })
            ->filter()
            ->sortByDesc(fn (array $row): int => $row['cart_adds'] * 3 + $row['views'])
            ->take(self::LOST_DEMAND_LIMIT)
            ->values()
            ->all();
    }

    /**
     * Віртуальна опіка: активні опікуни зараз, заявки без оплати, підтверджені оплати за період.
     *
     * @return array{active: int, pending: int, confirmed_count: int, confirmed_sum: float}|null
     */
    public function sponsorship(string $range): ?array
    {
        if (! Schema::hasTable('sponsorships') || ! ShopSponsorshipSetting::isEnabled()) {
            return null;
        }

        [$from, $to] = ShopAnalytics::period($range);
        $confirmed = Sponsorship::query()->whereBetween('paid_at', [$from, $to]);

        return [
            'active' => Sponsorship::query()
                ->where('status', Sponsorship::STATUS_ACTIVE)
                ->whereDate('ends_at', '>=', today())
                ->count(),
            'pending' => Sponsorship::query()->where('status', Sponsorship::STATUS_PENDING)->count(),
            'confirmed_count' => (clone $confirmed)->count(),
            'confirmed_sum' => round((float) (clone $confirmed)->sum('amount'), 2),
        ];
    }

    /**
     * @return array{visitors: float, orders: float, revenue: float, average_order: float, conversion: float}
     */
    private function totals(Carbon $from, Carbon $to): array
    {
        $visitors = (float) ShopAnalyticsEvent::query()
            ->whereBetween('occurred_at', [$from, $to])
            ->distinct()
            ->count('visitor_key');

        $orders = $this->ordersBetween($from, $to)
            ->where('status', '!=', Order::STATUS_CANCELLED)
            ->get(['total']);
        $count = (float) $orders->count();
        $revenue = round((float) $orders->sum(fn (Order $order): float => (float) $order->total), 2);

        return [
            'visitors' => $visitors,
            'orders' => $count,
            'revenue' => $revenue,
            'average_order' => $count > 0 ? round($revenue / $count, 2) : 0.0,
            'conversion' => $visitors > 0 ? round($count / $visitors * 100, 1) : 0.0,
        ];
    }

    /**
     * @return Builder<Order>
     */
    private function ordersBetween(Carbon $from, Carbon $to): Builder
    {
        return Order::query()->where(fn (Builder $query) => $query
            ->whereBetween('placed_at', [$from, $to])
            ->orWhere(fn (Builder $inner) => $inner->whereNull('placed_at')->whereBetween('created_at', [$from, $to])));
    }

    private function delta(float $current, float $previous): ?float
    {
        if ($previous <= 0.0) {
            return null;
        }

        return round(($current - $previous) / $previous * 100, 1);
    }

    /**
     * @param  Collection<int, string>  $labels
     * @return list<array{label: string, value: int, percent: float}>
     */
    private function shares(Collection $labels): array
    {
        $total = $labels->count();
        if ($total === 0) {
            return [];
        }

        return $labels
            ->countBy()
            ->sortDesc()
            ->map(fn (int $value, string $label): array => [
                'label' => $label,
                'value' => $value,
                'percent' => round($value / $total * 100, 1),
            ])
            ->values()
            ->all();
    }

    private function hourLabel(int $hour): string
    {
        return sprintf('%02d:00–%02d:00', $hour, ($hour + 1) % 24);
    }
}
