<?php

namespace Tests\Feature\Admin;

use App\Filament\Admin\Pages\SiteStatistics;
use App\Models\OptionGroup;
use App\Models\OptionValue;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ShopAnalyticsEvent;
use App\Models\ShopAnalyticsPresence;
use App\Models\User;
use App\Services\ShopAnalytics;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SiteStatisticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_visits_search_and_repeat_views_are_counted_once_per_person(): void
    {
        $token = bin2hex(random_bytes(16));

        $this->withCookie('zoogle_vid', $token)
            ->get('/')
            ->assertOk();

        $this->withCookie('zoogle_vid', $token)
            ->get('/')
            ->assertOk();

        $this->withCookie('zoogle_vid', $token)
            ->get('/catalog?q=Клітка')
            ->assertOk();

        $this->withCookie('zoogle_vid', $token)
            ->get('/catalog?q=клітка&page=1')
            ->assertOk();

        $report = ShopAnalytics::report('today');

        $this->assertSame(1, $report['visitors']);
        $this->assertSame(1, $report['new_visitors']);
        $this->assertSame(0, $report['returning_visitors']);
        $this->assertSame(2, $report['page_views']);
        $this->assertSame(1, $report['searches']);
        $this->assertSame(1, $report['online_now']);
        $this->assertGreaterThanOrEqual(1, $report['peak_online']);
        $this->assertSame('клітка', $report['top_search'][0]['label'] ?? null);
        $this->assertSame('клітка', $report['empty_search'][0]['label'] ?? null);
    }

    public function test_first_visit_sets_a_visitor_cookie(): void
    {
        $this->get('/')->assertOk()->assertCookie('zoogle_vid');
    }

    public function test_two_people_online_and_a_returning_visitor(): void
    {
        $this->withCookie('zoogle_vid', bin2hex(random_bytes(16)))->get('/')->assertOk();
        $this->flushSession();
        $this->withCookie('zoogle_vid', bin2hex(random_bytes(16)))->get('/catalog')->assertOk();

        $report = ShopAnalytics::report('today');
        $this->assertSame(2, $report['online_now']);
        $this->assertSame(2, $report['peak_online']);
        $this->assertSame(2, $report['visitors']);
        $this->assertSame(2, $report['new_visitors']);

        ShopAnalyticsPresence::query()->firstOrFail()->forceFill([
            'first_seen_at' => now()->subDays(10),
        ])->save();

        $again = ShopAnalytics::report('7');
        $this->assertSame(1, $again['returning_visitors']);
        $this->assertSame(1, $again['new_visitors']);
    }

    public function test_empty_cart_checkout_redirect_is_not_a_visit(): void
    {
        $this->get('/checkout')->assertRedirect();

        $this->assertSame(0, ShopAnalyticsEvent::query()->count());
    }

    public function test_statistics_page_renders_before_any_visits(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::query()->where('email', 'admin@sitezoo.local')->firstOrFail();

        $this->actingAs($admin)
            ->get('/admin/statistics')
            ->assertOk()
            ->assertSee('Статистика сайту')
            ->assertSee('Зараз онлайн')
            ->assertSee('Ще немає даних за цей період.')
            ->assertSee('Немає продажів');
    }

    public function test_bots_and_staff_are_not_counted(): void
    {
        $this->withHeader('User-Agent', 'Mozilla/5.0 (compatible; Googlebot/2.1)')
            ->get('/')
            ->assertOk();

        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::query()->where('email', 'admin@sitezoo.local')->firstOrFail();

        $this->actingAs($admin)->get('/')->assertOk();

        $this->assertSame(0, ShopAnalyticsEvent::query()->count());
        $this->assertSame(0, ShopAnalytics::report('today')['visitors']);
    }

    public function test_product_view_cart_and_orders_show_on_the_statistics_page(): void
    {
        $category = $this->category('Для папуг');
        $product = Product::query()->create([
            'title' => 'Клітка для статистики',
            'slug' => 'stats-cage',
            'product_type' => OptionGroup::CATALOG_PRODUCT_TYPE,
            'category_parent_value_id' => $category->id,
            'category_value_id' => $category->id,
            'price' => 250,
            'is_available' => true,
            'variant_options' => [
                [
                    'option_group_id' => $category->option_group_id,
                    'option_value_ids' => [$category->id],
                ],
            ],
        ]);

        $token = bin2hex(random_bytes(16));
        $this->withCookie('zoogle_vid', $token)
            ->get(route('catalog.show', $product->slug))
            ->assertOk();

        $this->withCookie('zoogle_vid', $token)
            ->post(route('cart.store'), [
                'product_id' => $product->id,
            ])
            ->assertRedirect(route('cart.index'));

        $this->withCookie('zoogle_vid', $token)
            ->get(route('checkout.create'))
            ->assertOk();

        $customer = User::factory()->create();
        $this->actingAs($customer)
            ->withCookie('zoogle_vid', $token)
            ->post(route('favorites.toggle'), ['product_id' => $product->id])
            ->assertOk()
            ->assertJson(['favorited' => true]);

        $sold = Order::query()->create([
            'customer_name' => 'Аня',
            'customer_phone' => '0500000000',
            'total' => 500,
            'status' => Order::STATUS_NEW,
        ]);
        OrderItem::query()->create([
            'order_id' => $sold->id,
            'product_id' => $product->id,
            'title_snapshot' => 'Клітка для статистики',
            'price' => 250,
            'qty' => 2,
            'line_total' => 500,
        ]);
        Order::query()->create([
            'customer_name' => 'Богдан',
            'customer_phone' => '0500000001',
            'total' => 90,
            'status' => Order::STATUS_CANCELLED,
        ]);

        ShopAnalyticsEvent::query()->create([
            'occurred_on' => now()->subDay()->toDateString(),
            'occurred_at' => now()->subDay(),
            'visitor_key' => str_repeat('c', 64),
            'type' => ShopAnalyticsEvent::TYPE_PAGE,
            'label' => '/yesterday',
            'quantity' => 1,
        ]);

        $today = ShopAnalytics::report('today');
        $this->assertSame(1, $today['product_views']);
        $this->assertSame(1, $today['cart_adds']);
        $this->assertSame(1, $today['checkouts']);
        $this->assertSame(1, $today['favorites']);
        $this->assertSame('Клітка для статистики', $today['top_favorites'][0]['label'] ?? null);
        $this->assertSame(1, $today['orders']);
        $this->assertSame(500.0, $today['revenue']);
        $this->assertSame(500.0, $today['average_order']);
        $this->assertSame('Клітка для статистики', $today['top_sold'][0]['label'] ?? null);
        $this->assertSame(2, $today['top_sold'][0]['qty'] ?? null);
        $this->assertSame('Клітка для статистики', $today['top_viewed'][0]['label'] ?? null);
        $this->assertSame('Для папуг', $today['top_categories'][0]['label'] ?? null);
        $this->assertSame(1, $today['visitors']);

        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::query()->where('email', 'admin@sitezoo.local')->firstOrFail();
        $this->actingAs($admin);

        $this->get('/admin/statistics')
            ->assertOk()
            ->assertSee('Статистика сайту')
            ->assertSee('Зараз онлайн')
            ->assertSee('Клітка для статистики')
            ->assertSee('Що купують')
            ->assertSee('Пошук без результатів');

        Livewire::test(SiteStatistics::class)
            ->assertSee('Відвідувачі і перегляди')
            ->assertSee('Шлях до покупки')
            ->set('filters.range', 'today')
            ->assertSet('filters.range', 'today');
    }

    public function test_visits_stay_available_for_all_time_and_a_quarter(): void
    {
        $oldAt = now()->subDays(400);
        ShopAnalyticsEvent::query()->create([
            'occurred_on' => $oldAt->toDateString(),
            'occurred_at' => $oldAt,
            'visitor_key' => str_repeat('a', 64),
            'type' => ShopAnalyticsEvent::TYPE_PAGE,
            'label' => '/old',
            'quantity' => 1,
        ]);

        $this->get('/')->assertOk();
        $this->assertSame(2, ShopAnalyticsEvent::query()->count());

        $order = Order::query()->create([
            'customer_name' => 'Стара',
            'customer_phone' => '0500000002',
            'total' => 80,
            'status' => Order::STATUS_NEW,
        ]);
        $order->forceFill([
            'placed_at' => $oldAt,
            'created_at' => $oldAt,
        ])->save();

        $all = ShopAnalytics::report('all');
        $this->assertSame('month', $all['grain']);
        $this->assertSame('Весь час', $all['period_label']);
        $this->assertSame(2, $all['visitors']);
        $this->assertSame(1, $all['orders']);
        $this->assertSame(80.0, $all['revenue']);
        $this->assertTrue(collect($all['traffic']['labels'])->contains(
            fn (string $label): bool => str_contains($label, (string) $oldAt->year),
        ));
        $this->assertTrue(collect($all['sales']['labels'])->contains(
            fn (string $label): bool => str_contains($label, (string) $oldAt->year),
        ));

        $currentKey = 'q:'.now()->year.'-'.now()->quarter;
        $current = ShopAnalytics::report($currentKey);
        $this->assertSame('day', $current['grain']);
        $this->assertSame(1, $current['visitors']);
        $this->assertSame(0, $current['orders']);

        $oldKey = 'q:'.$oldAt->year.'-'.$oldAt->quarter;
        $this->assertArrayHasKey($oldKey, ShopAnalytics::rangeOptions());
        $oldQuarter = ShopAnalytics::report($oldKey);
        $this->assertSame(1, $oldQuarter['visitors']);
        $this->assertSame(1, $oldQuarter['orders']);
        $this->assertSame(80.0, $oldQuarter['revenue']);

        $emptyAt = now()->copy()->subMonths(8)->startOfQuarter();
        $emptyKey = 'q:'.$emptyAt->year.'-'.$emptyAt->quarter;
        if ($emptyKey === $oldKey || $emptyKey === $currentKey) {
            $emptyAt->subQuarter();
            $emptyKey = 'q:'.$emptyAt->year.'-'.$emptyAt->quarter;
        }
        $this->assertArrayHasKey($emptyKey, ShopAnalytics::rangeOptions());
        $this->assertSame(0, ShopAnalytics::report($emptyKey)['visitors']);
        $this->assertSame(0, ShopAnalytics::report($emptyKey)['orders']);
    }

    public function test_reset_clears_visits_and_keeps_orders(): void
    {
        $this->get('/')->assertOk();
        Order::query()->create([
            'customer_name' => 'Аня',
            'customer_phone' => '0500000000',
            'total' => 120,
            'status' => Order::STATUS_NEW,
        ]);

        ShopAnalytics::resetVisits();

        $this->assertSame(0, ShopAnalyticsEvent::query()->count());
        $this->assertSame(0, ShopAnalyticsPresence::query()->count());
        $this->assertSame(1, Order::query()->count());

        $report = ShopAnalytics::report('all');
        $this->assertSame(0, $report['visitors']);
        $this->assertSame(1, $report['orders']);
        $this->assertSame(120.0, $report['revenue']);
    }

    public function test_statistics_page_can_reset_visits(): void
    {
        $this->get('/')->assertOk();
        $this->assertSame(1, ShopAnalyticsEvent::query()->count());

        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::query()->where('email', 'admin@sitezoo.local')->firstOrFail();
        $this->actingAs($admin);

        Livewire::test(SiteStatistics::class)
            ->assertSee('Обнулити статистику')
            ->assertSee('Весь час')
            ->assertSee('квартал')
            ->callAction('resetAnalytics');

        $this->assertSame(0, ShopAnalyticsEvent::query()->count());
    }

    private function category(string $name): OptionValue
    {
        $group = OptionGroup::query()->firstOrCreate(
            [
                'product_type' => OptionGroup::CATALOG_PRODUCT_TYPE,
                'slug' => 'category',
            ],
            [
                'name' => 'Категорія',
                'selection_mode' => 'single',
                'value_type' => 'text',
                'is_active' => true,
            ]
        );

        return OptionValue::query()->create([
            'option_group_id' => $group->id,
            'name' => $name,
            'slug' => 'stats-'.substr(md5($name), 0, 8),
            'sort_order' => 1,
            'is_active' => true,
        ]);
    }
}
