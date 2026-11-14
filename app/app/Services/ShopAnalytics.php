<?php

namespace App\Services;

use App\Models\OptionValue;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ShopAnalyticsEvent;
use App\Models\ShopAnalyticsOnlineSample;
use App\Models\ShopAnalyticsPresence;
use App\Models\User;
use App\Support\CatalogProductsTable;
use App\Support\SchemaCache;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Статистика вітрини: візити, пошук, перегляди, кошик, онлайн.
 * Продажі читаються із замовлень, без окремого журналу оплат.
 */
class ShopAnalytics
{
    private const COOKIE = 'zoogle_vid';

    private const ONLINE_MINUTES = 5;

    /**
     * @return array<string, string>
     */
    public static function rangeOptions(): array
    {
        $options = [
            'today' => 'Сьогодні',
            '7' => '7 днів',
            '30' => '30 днів',
            '90' => '90 днів',
            'all' => 'Весь час',
        ];

        $cursor = now()->copy()->startOfQuarter();
        for ($i = 0; $i < 8; $i++) {
            $quarter = (int) $cursor->quarter;
            $options['q:'.$cursor->year.'-'.$quarter] = self::quarterLabel($cursor->year, $quarter);
            $cursor->subQuarter();
        }

        return $options;
    }

    /**
     * @return array<string, array<string, string>>
     */
    public static function groupedRangeOptions(): array
    {
        $options = self::rangeOptions();
        $quick = [];
        foreach (['today', '7', '30', '90', 'all'] as $key) {
            $quick[$key] = $options[$key];
            unset($options[$key]);
        }

        return [
            'Період' => $quick,
            'Квартали' => $options,
        ];
    }

    public static function resetVisits(): void
    {
        if (! self::ready()) {
            return;
        }

        DB::transaction(function (): void {
            ShopAnalyticsEvent::query()->delete();
            ShopAnalyticsPresence::query()->delete();
            ShopAnalyticsOnlineSample::query()->delete();
        });

        if (app()->bound('shop-analytics-reports')) {
            app()->forgetInstance('shop-analytics-reports');
        }
    }

    /**
     * Видати cookie відвідувача ще у відповіді: сам запис перегляду йде вже після відправки сторінки.
     */
    public static function prepareVisitor(Request $request): void
    {
        try {
            if (! $request->isMethod('GET')) {
                return;
            }

            $path = trim($request->path(), '/');
            if (self::skippedPath($path) || self::isPrefetch($request) || ! self::isDocument($request)) {
                return;
            }

            self::visitorKey($request);
        } catch (Throwable $e) {
            self::reportFailure($e);
        }
    }

    public static function recordPageView(Request $request): void
    {
        try {
            self::capturePageView($request);
        } catch (Throwable $e) {
            self::reportFailure($e);
        }
    }

    private static function capturePageView(Request $request): void
    {
        if (! $request->isMethod('GET')) {
            return;
        }

        $path = trim($request->path(), '/');
        if (self::skippedPath($path) || self::isPrefetch($request) || ! self::isDocument($request)) {
            return;
        }

        $visitor = self::visitorKey($request);
        if ($visitor === null) {
            return;
        }

        self::touchPresence($request, $visitor);
        if (! self::rememberDedupe($request, 'page:'.$path, 30)) {
            return;
        }

        // Токен сторінки успіху відкриває замовлення, тому в статистику він не потрапляє.
        $label = preg_replace('~^(checkout/success/\d+)/.*$~', '$1', $path);

        self::insert($visitor, ShopAnalyticsEvent::TYPE_PAGE, null, '/'.$label, 1, null);
        self::sampleOnline();
    }

    public static function recordProductView(Request $request, int $productId, string $title): void
    {
        self::recordSubject($request, ShopAnalyticsEvent::TYPE_PRODUCT_VIEW, $productId, $title, 1, null, 1800);
    }

    public static function recordBundleView(Request $request, int $bundleId, string $title): void
    {
        self::recordSubject($request, ShopAnalyticsEvent::TYPE_BUNDLE_VIEW, $bundleId, $title, 1, null, 1800);
    }

    public static function recordSearch(Request $request, string $query, int $results): void
    {
        $query = self::normalizeSearch($query);
        if ($query === '') {
            return;
        }

        self::recordSubject($request, ShopAnalyticsEvent::TYPE_SEARCH, null, $query, 1, max(0, $results), 900);
    }

    public static function recordCart(Request $request, int $subjectId, string $title, int $quantity): void
    {
        self::recordSubject(
            $request,
            ShopAnalyticsEvent::TYPE_CART,
            $subjectId,
            $title,
            max(1, $quantity),
            null,
            5,
        );
    }

    public static function recordCheckout(Request $request): void
    {
        self::recordSubject($request, ShopAnalyticsEvent::TYPE_CHECKOUT, null, null, 1, null, 600);
    }

    public static function recordFavorite(Request $request, int $productId, string $title): void
    {
        self::recordSubject($request, ShopAnalyticsEvent::TYPE_FAVORITE, $productId, $title, 1, null, 30);
    }

    /**
     * @return array<string, mixed>
     */
    public static function report(?string $range): array
    {
        $range = array_key_exists((string) $range, self::rangeOptions()) ? (string) $range : '7';
        $cached = app()->bound('shop-analytics-reports') ? app('shop-analytics-reports') : [];
        if (isset($cached[$range]) && is_array($cached[$range])) {
            return $cached[$range];
        }

        $cached[$range] = self::buildReport($range);
        app()->instance('shop-analytics-reports', $cached);

        return $cached[$range];
    }

    public static function formatMoney(float $amount): string
    {
        $decimals = abs($amount - round($amount)) < 0.001 ? 0 : 2;

        return number_format($amount, $decimals, ',', ' ').' ₴';
    }

    /**
     * @return array{0: Carbon, 1: Carbon, 2: string}
     */
    public static function period(?string $range): array
    {
        $range = array_key_exists((string) $range, self::rangeOptions()) ? (string) $range : '7';
        $end = now()->endOfDay();
        if ($range === 'today') {
            return [now()->startOfDay(), $end, 'hour'];
        }

        if ($range === 'all') {
            $from = self::historyStart()->startOfDay();
            $grain = (int) $from->diffInDays($end) > 120 ? 'month' : 'day';

            return [$from, $end, $grain];
        }

        if (preg_match('/^q:(\d{4})-([1-4])$/', $range, $match) === 1) {
            $start = Carbon::createFromDate(
                (int) $match[1],
                ((int) $match[2] - 1) * 3 + 1,
                1,
                (string) config('app.timezone'),
            )->startOfDay();
            $quarterEnd = $start->copy()->addMonths(3)->subSecond();
            $to = $quarterEnd->lessThan($end) ? $quarterEnd : $end;
            if ($start->greaterThan($to)) {
                $to = $start->copy()->endOfDay();
            }

            return [$start, $to, 'day'];
        }

        $days = in_array($range, ['7', '30', '90'], true) ? (int) $range : 7;

        return [now()->startOfDay()->subDays($days - 1), $end, 'day'];
    }

    private static function recordSubject(
        Request $request,
        string $type,
        ?int $subjectId,
        ?string $label,
        int $quantity,
        ?int $results,
        int $dedupeSeconds,
    ): void {
        try {
            self::captureSubject($request, $type, $subjectId, $label, $quantity, $results, $dedupeSeconds);
        } catch (Throwable $e) {
            self::reportFailure($e);
        }
    }

    private static function captureSubject(
        Request $request,
        string $type,
        ?int $subjectId,
        ?string $label,
        int $quantity,
        ?int $results,
        int $dedupeSeconds,
    ): void {
        $visitor = self::visitorKey($request);
        if ($visitor === null) {
            return;
        }

        self::touchPresence($request, $visitor);
        $dedupe = $type.':'.(string) ($subjectId ?? '').':'.mb_strtolower((string) $label);
        if (! self::rememberDedupe($request, $dedupe, $dedupeSeconds)) {
            return;
        }

        self::insert($visitor, $type, $subjectId, $label, $quantity, $results);
        self::sampleOnline();
    }

    private static function visitorKey(Request $request): ?string
    {
        if (! self::ready() || self::isBot($request) || self::isStaff($request)) {
            return null;
        }

        $token = $request->attributes->get('shop_analytics_token');
        if (! is_string($token) || preg_match('/^[a-f0-9]{32}$/', $token) !== 1) {
            $token = $request->cookie(self::COOKIE);
        }
        if (! is_string($token) || preg_match('/^[a-f0-9]{32}$/', $token) !== 1) {
            $token = bin2hex(random_bytes(16));
            Cookie::queue(cookie(
                self::COOKIE,
                $token,
                60 * 24 * 400,
                '/',
                null,
                $request->isSecure(),
                true,
                false,
                'lax',
            ));
        }

        $request->attributes->set('shop_analytics_token', $token);

        return hash('sha256', $token);
    }

    private static function touchPresence(Request $request, string $visitor): void
    {
        $last = (int) $request->session()->get('shop_analytics_presence_at', 0);
        if ($last > 0 && (time() - $last) < 45) {
            return;
        }

        $request->session()->put('shop_analytics_presence_at', time());
        $now = now();

        try {
            $row = ShopAnalyticsPresence::query()->whereKey($visitor)->first();
            if ($row === null) {
                ShopAnalyticsPresence::query()->create([
                    'visitor_key' => $visitor,
                    'first_seen_at' => $now,
                    'last_seen_at' => $now,
                ]);

                return;
            }

            $row->forceFill(['last_seen_at' => $now])->save();
        } catch (QueryException) {
            // Паралельний перший візит з тим самим ключем.
        }
    }

    private static function insert(
        string $visitor,
        string $type,
        ?int $subjectId,
        ?string $label,
        int $quantity,
        ?int $results,
    ): void {
        $now = now();
        $text = $label !== null ? trim($label) : null;
        if ($text !== null) {
            $text = mb_substr($text, 0, 191);
            if ($text === '') {
                $text = null;
            }
        }

        try {
            ShopAnalyticsEvent::query()->create([
                'occurred_on' => $now->toDateString(),
                'occurred_at' => $now,
                'visitor_key' => $visitor,
                'type' => $type,
                'subject_id' => $subjectId !== null && $subjectId > 0 ? $subjectId : null,
                'label' => $text,
                'quantity' => max(1, $quantity),
                'results_count' => $results,
            ]);
        } catch (Throwable $e) {
            if (Cache::add('shop-analytics-error', 1, 300)) {
                report($e);
            }
        }
    }

    private static function sampleOnline(): void
    {
        $bucket = now()->startOfMinute()->format('Y-m-d H:i');
        if (! Cache::add('shop-analytics-sample:'.$bucket, 1, 70)) {
            return;
        }

        $online = self::onlineNow();
        ShopAnalyticsOnlineSample::query()->updateOrCreate(
            ['sampled_at' => now()->startOfMinute()],
            ['online_count' => $online],
        );
    }

    private static function onlineNow(): int
    {
        if (! self::ready()) {
            return 0;
        }

        return ShopAnalyticsPresence::query()
            ->where('last_seen_at', '>=', now()->subMinutes(self::ONLINE_MINUTES))
            ->count();
    }

    /**
     * @return array<string, mixed>
     */
    private static function buildReport(string $range): array
    {
        [$from, $to, $grain] = self::period($range);
        $empty = self::emptyReport($range, $from, $to, $grain);
        if (! self::ready()) {
            return $empty;
        }

        $events = ShopAnalyticsEvent::query()->whereBetween('occurred_at', [$from, $to]);
        $visitors = (clone $events)->distinct()->count('visitor_key');
        $typeRows = (clone $events)
            ->select('type')
            ->selectRaw('COUNT(*) as aggregate')
            ->selectRaw('SUM(quantity) as qty')
            ->groupBy('type')
            ->get()
            ->keyBy('type');

        $countOf = fn (string $type): int => (int) ($typeRows[$type]->aggregate ?? 0);
        $pageViews = $countOf(ShopAnalyticsEvent::TYPE_PAGE);
        $productViews = $countOf(ShopAnalyticsEvent::TYPE_PRODUCT_VIEW) + $countOf(ShopAnalyticsEvent::TYPE_BUNDLE_VIEW);
        $searches = $countOf(ShopAnalyticsEvent::TYPE_SEARCH);
        $cartAdds = $countOf(ShopAnalyticsEvent::TYPE_CART);
        $checkouts = $countOf(ShopAnalyticsEvent::TYPE_CHECKOUT);
        $favorites = $countOf(ShopAnalyticsEvent::TYPE_FAVORITE);

        $productViewers = (clone $events)
            ->whereIn('type', [ShopAnalyticsEvent::TYPE_PRODUCT_VIEW, ShopAnalyticsEvent::TYPE_BUNDLE_VIEW])
            ->distinct()
            ->count('visitor_key');
        $cartVisitors = (clone $events)
            ->where('type', ShopAnalyticsEvent::TYPE_CART)
            ->distinct()
            ->count('visitor_key');
        $checkoutVisitors = (clone $events)
            ->where('type', ShopAnalyticsEvent::TYPE_CHECKOUT)
            ->distinct()
            ->count('visitor_key');

        $returning = (int) DB::table('shop_analytics_events')
            ->join('shop_analytics_presence', 'shop_analytics_presence.visitor_key', '=', 'shop_analytics_events.visitor_key')
            ->whereBetween('shop_analytics_events.occurred_at', [$from, $to])
            ->where('shop_analytics_presence.first_seen_at', '<', $from)
            ->distinct()
            ->count('shop_analytics_events.visitor_key');
        $newVisitors = max(0, $visitors - $returning);

        $online = self::onlineNow();
        $peak = (int) ShopAnalyticsOnlineSample::query()
            ->whereBetween('sampled_at', [$from, $to])
            ->max('online_count');
        $samples = $grain === 'month'
            ? collect()
            : ShopAnalyticsOnlineSample::query()
                ->whereBetween('sampled_at', [$from, $to])
                ->orderBy('sampled_at')
                ->get(['sampled_at', 'online_count']);
        if ($to->greaterThanOrEqualTo(now())) {
            $peak = max($peak, $online);
        }

        $orders = Order::query()
            ->where(function ($query) use ($from, $to): void {
                $query->whereBetween('placed_at', [$from, $to])
                    ->orWhere(function ($inner) use ($from, $to): void {
                        $inner->whereNull('placed_at')->whereBetween('created_at', [$from, $to]);
                    });
            })
            ->get(['id', 'status', 'total', 'placed_at', 'created_at']);

        $activeOrders = $orders->filter(
            fn (Order $order): bool => (string) $order->status !== Order::STATUS_CANCELLED
        );
        $revenue = (float) $activeOrders->sum(fn (Order $order): float => (float) $order->total);
        $orderCount = $activeOrders->count();

        $traffic = self::trafficSeries($from, $to, $grain);
        $sales = self::salesSeries($from, $to, $grain, $orders);

        return [
            'range' => $range,
            'from' => $from,
            'to' => $to,
            'hourly' => $grain === 'hour',
            'grain' => $grain,
            'period_label' => self::rangeOptions()[$range] ?? '7 днів',
            'visitors' => $visitors,
            'new_visitors' => $newVisitors,
            'returning_visitors' => $returning,
            'page_views' => $pageViews,
            'product_views' => $productViews,
            'searches' => $searches,
            'cart_adds' => $cartAdds,
            'checkouts' => $checkouts,
            'favorites' => $favorites,
            'online_now' => $online,
            'peak_online' => $peak,
            'orders' => $orderCount,
            'cancelled_orders' => $orders->count() - $orderCount,
            'revenue' => $revenue,
            'average_order' => $orderCount > 0 ? $revenue / $orderCount : 0.0,
            'conversion' => $visitors > 0 ? round($orderCount / $visitors * 100, 1) : 0.0,
            'view_to_cart' => $productViews > 0 ? round($cartAdds / $productViews * 100, 1) : 0.0,
            'traffic' => $traffic,
            'sales' => $sales,
            'online' => self::onlineSeries($from, $to, $grain, $samples, $online),
            'funnel' => [
                'labels' => ['Відвідувачі', 'Переглянули товар', 'У кошик', 'Оформлення', 'Замовлення'],
                'values' => [$visitors, $productViewers, $cartVisitors, $checkoutVisitors, $orderCount],
            ],
            'statuses' => self::statusSeries($orders),
            'top_sold' => self::topSold($activeOrders->pluck('id')->all()),
            'top_viewed' => self::topSubjects(
                $from,
                $to,
                [ShopAnalyticsEvent::TYPE_PRODUCT_VIEW, ShopAnalyticsEvent::TYPE_BUNDLE_VIEW],
                'views',
            ),
            'top_cart' => self::topSubjects($from, $to, [ShopAnalyticsEvent::TYPE_CART], 'adds'),
            'top_favorites' => self::topSubjects($from, $to, [ShopAnalyticsEvent::TYPE_FAVORITE], 'adds'),
            'top_search' => self::topSearches($from, $to, false),
            'empty_search' => self::topSearches($from, $to, true),
            'top_categories' => self::topCategories($activeOrders->pluck('id')->all()),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function emptyReport(string $range, Carbon $from, Carbon $to, string $grain): array
    {
        $labels = self::bucketLabels($from, $to, $grain);
        $zeros = array_fill(0, count($labels), 0);

        return [
            'range' => $range,
            'from' => $from,
            'to' => $to,
            'hourly' => $grain === 'hour',
            'grain' => $grain,
            'period_label' => self::rangeOptions()[$range] ?? '7 днів',
            'visitors' => 0,
            'new_visitors' => 0,
            'returning_visitors' => 0,
            'page_views' => 0,
            'product_views' => 0,
            'searches' => 0,
            'cart_adds' => 0,
            'checkouts' => 0,
            'favorites' => 0,
            'online_now' => 0,
            'peak_online' => 0,
            'orders' => 0,
            'cancelled_orders' => 0,
            'revenue' => 0.0,
            'average_order' => 0.0,
            'conversion' => 0.0,
            'view_to_cart' => 0.0,
            'traffic' => ['labels' => $labels, 'visitors' => $zeros, 'views' => $zeros],
            'sales' => ['labels' => $labels, 'revenue' => array_map('floatval', $zeros), 'orders' => $zeros],
            'online' => ['labels' => $labels, 'counts' => $zeros],
            'funnel' => [
                'labels' => ['Відвідувачі', 'Переглянули товар', 'У кошик', 'Оформлення', 'Замовлення'],
                'values' => [0, 0, 0, 0, 0],
            ],
            'statuses' => ['labels' => ['Немає замовлень'], 'values' => [1]],
            'top_sold' => [],
            'top_viewed' => [],
            'top_cart' => [],
            'top_favorites' => [],
            'top_search' => [],
            'empty_search' => [],
            'top_categories' => [],
        ];
    }

    /**
     * @return array{labels: list<string>, visitors: list<int>, views: list<int>}
     */
    private static function trafficSeries(Carbon $from, Carbon $to, string $grain): array
    {
        if ($grain === 'hour') {
            $rows = ShopAnalyticsEvent::query()
                ->whereBetween('occurred_at', [$from, $to])
                ->get(['occurred_at', 'visitor_key', 'type']);
            $visitors = array_fill(0, 24, []);
            $views = array_fill(0, 24, 0);
            foreach ($rows as $row) {
                $hour = (int) $row->occurred_at?->timezone(config('app.timezone'))->format('G');
                $visitors[$hour][$row->visitor_key] = true;
                if ($row->type === ShopAnalyticsEvent::TYPE_PAGE) {
                    $views[$hour]++;
                }
            }

            return [
                'labels' => array_map(fn (int $hour): string => sprintf('%02d:00', $hour), range(0, 23)),
                'visitors' => array_map(fn (array $keys): int => count($keys), $visitors),
                'views' => $views,
            ];
        }

        if ($grain === 'month') {
            $expression = self::monthSql('occurred_at');
            $rows = ShopAnalyticsEvent::query()
                ->whereBetween('occurred_at', [$from, $to])
                ->selectRaw($expression.' as bucket')
                ->selectRaw('COUNT(DISTINCT visitor_key) as visitors')
                ->selectRaw("SUM(CASE WHEN type = 'page' THEN 1 ELSE 0 END) as views")
                ->groupByRaw($expression)
                ->get()
                ->keyBy(fn ($row): string => (string) $row->bucket);

            $labels = [];
            $visitorSeries = [];
            $viewSeries = [];
            foreach (self::months($from, $to) as $month) {
                $labels[] = self::monthLabel($month);
                $visitorSeries[] = (int) ($rows[$month]->visitors ?? 0);
                $viewSeries[] = (int) ($rows[$month]->views ?? 0);
            }

            return ['labels' => $labels, 'visitors' => $visitorSeries, 'views' => $viewSeries];
        }

        $rows = ShopAnalyticsEvent::query()
            ->whereBetween('occurred_at', [$from, $to])
            ->select('occurred_on')
            ->selectRaw('COUNT(DISTINCT visitor_key) as visitors')
            ->selectRaw("SUM(CASE WHEN type = 'page' THEN 1 ELSE 0 END) as views")
            ->groupBy('occurred_on')
            ->get()
            ->keyBy(fn ($row): string => $row->occurred_on?->toDateString() ?? '');

        $labels = [];
        $visitorSeries = [];
        $viewSeries = [];
        foreach (self::days($from, $to) as $day) {
            $labels[] = Carbon::parse($day)->format('d.m');
            $visitorSeries[] = (int) ($rows[$day]->visitors ?? 0);
            $viewSeries[] = (int) ($rows[$day]->views ?? 0);
        }

        return ['labels' => $labels, 'visitors' => $visitorSeries, 'views' => $viewSeries];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Order>  $orders
     * @return array{labels: list<string>, revenue: list<float>, orders: list<int>}
     */
    private static function salesSeries(Carbon $from, Carbon $to, string $grain, $orders): array
    {
        $hourly = $grain === 'hour';
        $buckets = $hourly ? array_fill(0, 24, ['revenue' => 0.0, 'orders' => 0]) : [];
        if ($grain === 'day') {
            foreach (self::days($from, $to) as $day) {
                $buckets[$day] = ['revenue' => 0.0, 'orders' => 0];
            }
        }
        if ($grain === 'month') {
            foreach (self::months($from, $to) as $month) {
                $buckets[$month] = ['revenue' => 0.0, 'orders' => 0];
            }
        }

        foreach ($orders as $order) {
            if ((string) $order->status === Order::STATUS_CANCELLED) {
                continue;
            }
            $at = ($order->placed_at ?? $order->created_at)?->timezone(config('app.timezone'));
            if ($at === null) {
                continue;
            }
            if ($hourly) {
                $key = (int) $at->format('G');
            } elseif ($grain === 'month') {
                $key = $at->format('Y-m');
                if (! isset($buckets[$key])) {
                    continue;
                }
            } else {
                $key = $at->toDateString();
                if (! isset($buckets[$key])) {
                    continue;
                }
            }
            $buckets[$key]['revenue'] += (float) $order->total;
            $buckets[$key]['orders']++;
        }

        if ($hourly) {
            return [
                'labels' => array_map(fn (int $hour): string => sprintf('%02d:00', $hour), range(0, 23)),
                'revenue' => array_map(fn (array $bucket): float => round($bucket['revenue'], 2), $buckets),
                'orders' => array_map(fn (array $bucket): int => $bucket['orders'], $buckets),
            ];
        }

        $labels = [];
        $revenue = [];
        $counts = [];
        $keys = $grain === 'month' ? self::months($from, $to) : self::days($from, $to);
        foreach ($keys as $key) {
            $labels[] = $grain === 'month' ? self::monthLabel($key) : Carbon::parse($key)->format('d.m');
            $revenue[] = round($buckets[$key]['revenue'], 2);
            $counts[] = $buckets[$key]['orders'];
        }

        return ['labels' => $labels, 'revenue' => $revenue, 'orders' => $counts];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, ShopAnalyticsOnlineSample>  $samples
     * @return array{labels: list<string>, counts: list<int>}
     */
    private static function onlineSeries(Carbon $from, Carbon $to, string $grain, $samples, int $onlineNow): array
    {
        if ($grain === 'month') {
            $expression = self::monthSql('sampled_at');
            $rows = ShopAnalyticsOnlineSample::query()
                ->whereBetween('sampled_at', [$from, $to])
                ->selectRaw($expression.' as bucket')
                ->selectRaw('MAX(online_count) as peak')
                ->groupByRaw($expression)
                ->get()
                ->keyBy(fn ($row): string => (string) $row->bucket);
            $labels = [];
            $counts = [];
            $current = now()->format('Y-m');
            foreach (self::months($from, $to) as $month) {
                $labels[] = self::monthLabel($month);
                $count = (int) ($rows[$month]->peak ?? 0);
                if ($month === $current && $to->greaterThanOrEqualTo(now())) {
                    $count = max($count, $onlineNow);
                }
                $counts[] = $count;
            }

            return ['labels' => $labels, 'counts' => $counts];
        }

        if ($grain === 'hour') {
            $counts = array_fill(0, 24, 0);
            foreach ($samples as $sample) {
                $hour = (int) $sample->sampled_at?->timezone(config('app.timezone'))->format('G');
                $counts[$hour] = max($counts[$hour], (int) $sample->online_count);
            }
            $counts[(int) now()->format('G')] = max($counts[(int) now()->format('G')], $onlineNow);

            return [
                'labels' => array_map(fn (int $hour): string => sprintf('%02d:00', $hour), range(0, 23)),
                'counts' => $counts,
            ];
        }

        $byDay = [];
        foreach (self::days($from, $to) as $day) {
            $byDay[$day] = 0;
        }
        foreach ($samples as $sample) {
            $day = $sample->sampled_at?->timezone(config('app.timezone'))->toDateString();
            if ($day !== null && isset($byDay[$day])) {
                $byDay[$day] = max($byDay[$day], (int) $sample->online_count);
            }
        }
        $today = now()->toDateString();
        if (isset($byDay[$today])) {
            $byDay[$today] = max($byDay[$today], $onlineNow);
        }

        return [
            'labels' => array_map(fn (string $day): string => Carbon::parse($day)->format('d.m'), array_keys($byDay)),
            'counts' => array_values($byDay),
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Order>  $orders
     * @return array{labels: list<string>, values: list<int>}
     */
    private static function statusSeries($orders): array
    {
        if ($orders->isEmpty()) {
            return ['labels' => ['Немає замовлень'], 'values' => [1]];
        }

        $labels = Order::statusLabels();
        $counts = [];
        foreach ($orders as $order) {
            $status = (string) $order->status;
            $name = $labels[$status] ?? $status;
            $counts[$name] = ($counts[$name] ?? 0) + 1;
        }

        return ['labels' => array_keys($counts), 'values' => array_values($counts)];
    }

    /**
     * @param  list<int>  $orderIds
     * @return list<array{label: string, qty: int, revenue: float}>
     */
    private static function topSold(array $orderIds): array
    {
        if ($orderIds === []) {
            return [];
        }

        $productColumn = SchemaCache::hasColumn('order_items', 'product_id') ? 'product_id' : 'animal_listing_id';
        $columns = ['title_snapshot', 'qty', 'line_total', $productColumn];
        if (SchemaCache::hasColumn('order_items', 'bundle_id')) {
            $columns[] = 'bundle_id';
        }

        $grouped = [];
        foreach (OrderItem::query()->whereIn('order_id', $orderIds)->get($columns) as $item) {
            $bundleId = (int) ($item->bundle_id ?? 0);
            $productId = (int) ($item->{$productColumn} ?? 0);
            $key = $bundleId > 0 ? 'b'.$bundleId : 'p'.$productId;
            $label = trim((string) $item->title_snapshot);
            if ($label === '') {
                $label = $bundleId > 0 ? 'Комплект #'.$bundleId : 'Товар #'.$productId;
            }
            if (! isset($grouped[$key])) {
                $grouped[$key] = ['label' => $label, 'qty' => 0, 'revenue' => 0.0, 'product_id' => $productId];
            }
            $grouped[$key]['qty'] += (int) $item->qty;
            $grouped[$key]['revenue'] += (float) $item->line_total;
        }

        usort($grouped, fn (array $a, array $b): int => $b['qty'] <=> $a['qty']);

        return array_map(function (array $row): array {
            return [
                'label' => $row['label'],
                'qty' => $row['qty'],
                'revenue' => round($row['revenue'], 2),
            ];
        }, array_slice($grouped, 0, 8));
    }

    /**
     * @param  list<int>  $orderIds
     * @return list<array{label: string, qty: int, revenue: float}>
     */
    private static function topCategories(array $orderIds): array
    {
        if ($orderIds === [] || ! SchemaCache::hasColumn(CatalogProductsTable::name(), 'category_value_id')) {
            return [];
        }

        $productColumn = SchemaCache::hasColumn('order_items', 'product_id') ? 'product_id' : 'animal_listing_id';
        $items = OrderItem::query()->whereIn('order_id', $orderIds)->get([$productColumn, 'qty', 'line_total']);
        $productIds = $items->pluck($productColumn)->map(fn ($id): int => (int) $id)->filter(fn (int $id): bool => $id > 0)->unique()->values();
        if ($productIds->isEmpty()) {
            return [];
        }

        $categoryByProduct = Product::query()
            ->whereIn('id', $productIds->all())
            ->pluck('category_value_id', 'id');
        $names = OptionValue::query()
            ->whereIn('id', $categoryByProduct->filter()->unique()->values()->all())
            ->pluck('name', 'id');

        $grouped = [];
        foreach ($items as $item) {
            $categoryId = (int) ($categoryByProduct[(int) ($item->{$productColumn} ?? 0)] ?? 0);
            if ($categoryId <= 0) {
                continue;
            }
            $label = trim((string) ($names[$categoryId] ?? ''));
            if ($label === '') {
                continue;
            }
            if (! isset($grouped[$categoryId])) {
                $grouped[$categoryId] = ['label' => $label, 'qty' => 0, 'revenue' => 0.0];
            }
            $grouped[$categoryId]['qty'] += (int) $item->qty;
            $grouped[$categoryId]['revenue'] += (float) $item->line_total;
        }

        usort($grouped, fn (array $a, array $b): int => $b['revenue'] <=> $a['revenue']);

        return array_values(array_slice($grouped, 0, 8));
    }

    /**
     * @param  list<string>  $types
     * @return list<array{label: string, views?: int, adds?: int}>
     */
    private static function topSubjects(Carbon $from, Carbon $to, array $types, string $metric): array
    {
        $rows = ShopAnalyticsEvent::query()
            ->whereBetween('occurred_at', [$from, $to])
            ->whereIn('type', $types)
            ->whereNotNull('label')
            ->select('label')
            ->selectRaw('COUNT(*) as hits')
            ->selectRaw('SUM(quantity) as qty')
            ->groupBy('label')
            ->orderByDesc('hits')
            ->limit(8)
            ->get();

        return $rows->map(function ($row) use ($metric): array {
            return [
                'label' => (string) $row->label,
                $metric => $metric === 'adds' ? (int) $row->qty : (int) $row->hits,
            ];
        })->all();
    }

    /**
     * @return list<array{label: string, searches: int}>
     */
    private static function topSearches(Carbon $from, Carbon $to, bool $emptyOnly): array
    {
        $query = ShopAnalyticsEvent::query()
            ->whereBetween('occurred_at', [$from, $to])
            ->where('type', ShopAnalyticsEvent::TYPE_SEARCH)
            ->whereNotNull('label');
        if ($emptyOnly) {
            $query->where('results_count', 0);
        }

        return $query
            ->select('label')
            ->selectRaw('COUNT(*) as searches')
            ->groupBy('label')
            ->orderByDesc('searches')
            ->limit(8)
            ->get()
            ->map(fn ($row): array => [
                'label' => (string) $row->label,
                'searches' => (int) $row->searches,
            ])
            ->all();
    }

    /**
     * @return list<string>
     */
    private static function bucketLabels(Carbon $from, Carbon $to, string $grain): array
    {
        if ($grain === 'hour') {
            return array_map(fn (int $hour): string => sprintf('%02d:00', $hour), range(0, 23));
        }

        if ($grain === 'month') {
            return array_map(self::monthLabel(...), self::months($from, $to));
        }

        return array_map(
            fn (string $day): string => Carbon::parse($day)->format('d.m'),
            self::days($from, $to),
        );
    }

    /**
     * @return list<string>
     */
    private static function months(Carbon $from, Carbon $to): array
    {
        $months = [];
        $cursor = $from->copy()->startOfMonth();
        $end = $to->copy()->startOfMonth();
        $guard = 0;
        while ($cursor->lte($end) && $guard < 240) {
            $months[] = $cursor->format('Y-m');
            $cursor->addMonth();
            $guard++;
        }

        return $months;
    }

    private static function monthLabel(string $month): string
    {
        $names = [
            '01' => 'січ',
            '02' => 'лют',
            '03' => 'бер',
            '04' => 'кві',
            '05' => 'тра',
            '06' => 'чер',
            '07' => 'лип',
            '08' => 'сер',
            '09' => 'вер',
            '10' => 'жов',
            '11' => 'лис',
            '12' => 'гру',
        ];
        [$year, $part] = array_pad(explode('-', $month, 2), 2, '');

        return ($names[$part] ?? $part).' '.$year;
    }

    private static function monthSql(string $column): string
    {
        return DB::connection()->getDriverName() === 'sqlite'
            ? "strftime('%Y-%m', {$column})"
            : "DATE_FORMAT({$column}, '%Y-%m')";
    }

    private static function quarterLabel(int $year, int $quarter): string
    {
        $roman = [1 => 'I', 2 => 'II', 3 => 'III', 4 => 'IV'];

        return ($roman[$quarter] ?? (string) $quarter).' квартал '.$year;
    }

    private static function historyStart(): Carbon
    {
        $fallback = now()->startOfDay();
        if (! self::ready()) {
            return $fallback;
        }

        $oldest = null;
        foreach ([
            ShopAnalyticsEvent::query()->min('occurred_at'),
            Order::query()->min('placed_at'),
            Order::query()->whereNull('placed_at')->min('created_at'),
        ] as $stamp) {
            if ($stamp === null || $stamp === '') {
                continue;
            }
            $at = Carbon::parse($stamp);
            if ($oldest === null || $at->lessThan($oldest)) {
                $oldest = $at;
            }
        }

        if ($oldest === null || $oldest->greaterThan(now())) {
            return $fallback;
        }

        return $oldest;
    }

    /**
     * @return list<string>
     */
    private static function days(Carbon $from, Carbon $to): array
    {
        $days = [];
        $cursor = $from->copy()->startOfDay();
        $end = $to->copy()->startOfDay();
        $guard = 0;
        while ($cursor->lte($end) && $guard < 120) {
            $days[] = $cursor->toDateString();
            $cursor->addDay();
            $guard++;
        }

        return $days;
    }

    private static function rememberDedupe(Request $request, string $key, int $seconds): bool
    {
        $bucket = $request->session()->get('shop_analytics_dedupe', []);
        if (! is_array($bucket)) {
            $bucket = [];
        }
        $now = time();
        if (isset($bucket[$key]) && ($now - (int) $bucket[$key]) < $seconds) {
            return false;
        }
        $bucket[$key] = $now;
        if (count($bucket) > 40) {
            $bucket = array_slice($bucket, -20, null, true);
        }
        $request->session()->put('shop_analytics_dedupe', $bucket);

        return true;
    }

    private static function normalizeSearch(string $query): string
    {
        $query = trim(preg_replace('/\s+/u', ' ', $query) ?? '');

        return mb_substr(mb_strtolower($query, 'UTF-8'), 0, 191);
    }

    private static function skippedPath(string $path): bool
    {
        foreach (['feeds', 'admin', 'livewire', 'filament', 'payments', 'nova-poshta', 'geocode', 'build', 'storage', 'preview'] as $prefix) {
            if ($path === $prefix || str_starts_with($path, $prefix.'/')) {
                return true;
            }
        }

        return in_array($path, ['up', 'robots.txt', 'sitemap.xml', 'favicon.ico', 'catalog/suggest'], true);
    }

    private static function isPrefetch(Request $request): bool
    {
        $purpose = strtolower((string) ($request->headers->get('Purpose') ?: $request->headers->get('Sec-Purpose')));

        return str_contains($purpose, 'prefetch');
    }

    private static function isDocument(Request $request): bool
    {
        $dest = strtolower((string) $request->headers->get('Sec-Fetch-Dest'));

        return $dest === '' || $dest === 'document';
    }

    private static function isBot(Request $request): bool
    {
        $agent = strtolower((string) $request->userAgent());
        if ($agent === '') {
            return false;
        }

        return preg_match('/bot|crawl|spider|slurp|facebookexternal|telegrambot|whatsapp|preview|wget|curl\/|python-requests|headless|lighthouse|pingdom|uptimerobot/i', $agent) === 1;
    }

    private static function isStaff(Request $request): bool
    {
        $user = $request->user();

        return $user instanceof User && $user->hasAnyRole(['admin', 'manager']);
    }

    private static function reportFailure(Throwable $e): void
    {
        try {
            if (Cache::add('shop-analytics-error', 1, 300)) {
                report($e);
            }
        } catch (Throwable) {
            //
        }
    }

    private static function ready(): bool
    {
        try {
            return SchemaCache::hasTable('shop_analytics_events')
                && SchemaCache::hasTable('shop_analytics_presence')
                && SchemaCache::hasTable('shop_analytics_online_samples');
        } catch (Throwable) {
            return false;
        }
    }
}
