<?php

namespace Tests\Feature\Admin;

use App\Models\OptionGroup;
use App\Models\OptionValue;
use App\Models\Order;
use App\Models\Product;
use App\Models\ShopAnalyticsEvent;
use App\Models\ShopSponsorshipSetting;
use App\Models\SponsorAnimal;
use App\Models\Sponsorship;
use App\Models\User;
use App\Services\ShopAnalyticsInsights;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class StatisticsInsightsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-18 15:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_compares_with_the_previous_period_of_the_same_length(): void
    {
        $this->order(300, now()->subDays(2));
        $this->order(500, now()->subDays(3));
        $this->order(400, now()->subDays(10));
        $this->visit('a', now()->subDay());
        $this->visit('b', now()->subDays(9));
        $this->visit('c', now()->subDays(9));

        $metrics = collect(app(ShopAnalyticsInsights::class)->comparison('7'))->keyBy('key');

        $this->assertSame(2.0, $metrics['orders']['current']);
        $this->assertSame(1.0, $metrics['orders']['previous']);
        $this->assertSame(100.0, $metrics['orders']['delta']);
        $this->assertSame(800.0, $metrics['revenue']['current']);
        $this->assertSame(100.0, $metrics['revenue']['delta']);
        $this->assertSame(-50.0, $metrics['visitors']['delta']);
        $this->assertNull(app(ShopAnalyticsInsights::class)->comparison('all'));
    }

    public function test_heatmap_finds_the_busiest_weekday_and_hour(): void
    {
        // 17.11.2026 — вівторок. Сайт зберігає час в UTC, а карта показує київський (зараз UTC+2)
        foreach (['a', 'b', 'c'] as $visitor) {
            $this->visit($visitor, Carbon::parse('2026-11-17 17:20:00', 'UTC'));
        }
        $this->visit('d', Carbon::parse('2026-11-16 07:05:00', 'UTC'));
        $this->order(200, Carbon::parse('2026-11-17 18:10:00', 'UTC'));

        $heatmap = app(ShopAnalyticsInsights::class)->activityHeatmap('7');

        $this->assertSame(3, $heatmap['cells'][1][19]);
        $this->assertSame(1, $heatmap['cells'][0][9]);
        $this->assertSame(3, $heatmap['max']);
        $this->assertSame('Вівторок', $heatmap['busiest_day']);
        $this->assertSame('19:00–20:00', $heatmap['busiest_hour']);
        $this->assertSame('20:00–21:00', $heatmap['order_hour']);
    }

    public function test_delivery_and_payment_shares_ignore_cancelled_orders(): void
    {
        $this->order(100, now(), ['delivery_type' => Order::DELIVERY_NOVA_POSHTA_WAREHOUSE, 'checkout_payment_method' => 'online', 'payment_status' => 'paid']);
        $this->order(100, now(), ['delivery_type' => Order::DELIVERY_NOVA_POSHTA_WAREHOUSE, 'checkout_payment_method' => 'cod']);
        $this->order(100, now(), ['delivery_type' => Order::DELIVERY_PICKUP, 'checkout_payment_method' => 'cod']);
        $this->order(100, now(), ['delivery_type' => Order::DELIVERY_PICKUP, 'status' => Order::STATUS_CANCELLED]);

        $data = app(ShopAnalyticsInsights::class)->deliveryAndPayment('7');

        $delivery = collect($data['delivery'])->keyBy('label');
        $this->assertSame(2, $delivery[Order::deliveryTypeLabels()[Order::DELIVERY_NOVA_POSHTA_WAREHOUSE]]['value']);
        $this->assertSame(66.7, $delivery[Order::deliveryTypeLabels()[Order::DELIVERY_NOVA_POSHTA_WAREHOUSE]]['percent']);
        $this->assertSame(1, $delivery[Order::deliveryTypeLabels()[Order::DELIVERY_PICKUP]]['value']);

        $payment = collect($data['payment'])->keyBy('label');
        $this->assertSame(1, $payment['Онлайн']['value']);
        $this->assertSame(2, $payment['При отриманні']['value']);
        $this->assertSame(33.3, $data['paid_percent']);
    }

    public function test_lost_demand_lists_wanted_products_that_cannot_be_bought(): void
    {
        $gone = $this->product('Клітка, якої немає', ['stock_status' => Product::STOCK_OUT_OF_STOCK]);
        $hidden = $this->product('Прихована поїлка', ['is_available' => false]);
        $fine = $this->product('Корм у наявності');

        foreach (['a', 'b'] as $visitor) {
            $this->event($visitor, ShopAnalyticsEvent::TYPE_PRODUCT_VIEW, $gone);
        }
        $this->event('a', ShopAnalyticsEvent::TYPE_CART, $gone);
        $this->event('c', ShopAnalyticsEvent::TYPE_PRODUCT_VIEW, $hidden);
        $this->event('d', ShopAnalyticsEvent::TYPE_PRODUCT_VIEW, $fine);

        $rows = app(ShopAnalyticsInsights::class)->lostDemand('7');

        $this->assertSame(['Клітка, якої немає', 'Прихована поїлка'], array_column($rows, 'label'));
        $this->assertSame(2, $rows[0]['views']);
        $this->assertSame(1, $rows[0]['cart_adds']);
        $this->assertSame('Немає в наявності', $rows[0]['reason']);
        $this->assertSame('Прихований з вітрини', $rows[1]['reason']);
    }

    public function test_lost_demand_ignores_bundle_cart_adds_with_the_same_number(): void
    {
        $hidden = $this->product('Прихована поїлка', ['is_available' => false]);
        ShopAnalyticsEvent::query()->create([
            'occurred_on' => now()->toDateString(),
            'occurred_at' => now(),
            'visitor_key' => str_repeat('z', 64),
            'type' => ShopAnalyticsEvent::TYPE_CART,
            'subject_id' => $hidden->id,
            'label' => 'Комплект для кролика',
            'quantity' => 1,
        ]);

        $this->assertSame([], app(ShopAnalyticsInsights::class)->lostDemand('7'));
    }

    public function test_sponsorship_summary_appears_only_when_section_is_enabled(): void
    {
        $insights = app(ShopAnalyticsInsights::class);
        $this->assertNull($insights->sponsorship('7'));

        ShopSponsorshipSetting::query()->create(['is_enabled' => true, 'jar_url' => 'https://send.monobank.ua/jar/T1']);
        $animal = SponsorAnimal::query()->create(['name' => 'Пуша', 'price_month' => 300, 'is_active' => true]);
        $active = $this->sponsorship($animal, 300);
        $active->confirmPayment();
        $this->sponsorship($animal, 3000);

        $summary = $insights->sponsorship('7');

        $this->assertSame(1, $summary['active']);
        $this->assertSame(1, $summary['pending']);
        $this->assertSame(300.0, $summary['confirmed_sum']);
    }

    public function test_statistics_page_shows_new_blocks(): void
    {
        $this->order(300, now());
        $this->visit('a', now());
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::query()->where('email', 'admin@sitezoo.local')->firstOrFail();

        $this->actingAs($admin)
            ->get('/admin/statistics')
            ->assertOk()
            ->assertSee('Порівняно з попереднім періодом')
            ->assertSee('Коли заходять і купують')
            ->assertSee('Доставка та оплата')
            ->assertSee('Втрачений попит');
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function order(float $total, Carbon $at, array $overrides = []): Order
    {
        $order = Order::query()->create([
            'customer_name' => 'Покупець',
            'customer_phone' => '0500000000',
            'total' => $total,
            'status' => Order::STATUS_NEW,
            'payment_status' => 'pending',
            'delivery_type' => Order::DELIVERY_PICKUP,
            ...$overrides,
        ]);
        $order->forceFill(['placed_at' => $at, 'created_at' => $at])->save();

        return $order;
    }

    private function visit(string $visitor, Carbon $at): void
    {
        ShopAnalyticsEvent::query()->create([
            'occurred_on' => $at->toDateString(),
            'occurred_at' => $at,
            'visitor_key' => str_repeat($visitor, 64),
            'type' => ShopAnalyticsEvent::TYPE_PAGE,
            'label' => '/',
            'quantity' => 1,
        ]);
    }

    private function event(string $visitor, string $type, Product $product): void
    {
        ShopAnalyticsEvent::query()->create([
            'occurred_on' => now()->toDateString(),
            'occurred_at' => now(),
            'visitor_key' => str_repeat($visitor, 64),
            'type' => $type,
            'subject_id' => $product->id,
            'label' => $product->title,
            'quantity' => 1,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function product(string $title, array $overrides = []): Product
    {
        $group = OptionGroup::query()->firstOrCreate(
            ['product_type' => OptionGroup::CATALOG_PRODUCT_TYPE, 'slug' => 'category'],
            ['name' => 'Категорія', 'selection_mode' => 'single', 'value_type' => 'text', 'is_active' => true]
        );
        $category = OptionValue::query()->firstOrCreate(
            ['option_group_id' => $group->id, 'slug' => 'insights'],
            ['name' => 'Клітки', 'sort_order' => 1, 'is_active' => true]
        );

        return Product::query()->create([
            'title' => $title,
            'slug' => 'insights-'.uniqid(),
            'product_type' => OptionGroup::CATALOG_PRODUCT_TYPE,
            'category_parent_value_id' => $category->id,
            'category_value_id' => $category->id,
            'price' => 100,
            'is_available' => true,
            'stock_status' => Product::STOCK_IN_STOCK,
            'variant_options' => [
                ['option_group_id' => $group->id, 'option_value_ids' => [$category->id]],
            ],
            ...$overrides,
        ]);
    }

    private function sponsorship(SponsorAnimal $animal, float $amount): Sponsorship
    {
        return Sponsorship::query()->create([
            'sponsor_animal_id' => $animal->id,
            'animal_name' => $animal->name,
            'term' => 'month',
            'months' => 1,
            'amount' => $amount,
            'sponsor_name' => 'Олена',
            'email' => 'olena@example.com',
            'status' => Sponsorship::STATUS_PENDING,
        ]);
    }
}
