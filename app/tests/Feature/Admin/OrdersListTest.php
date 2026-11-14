<?php

namespace Tests\Feature\Admin;

use App\Filament\Admin\Resources\Orders\Pages\ListOrders;
use App\Filament\Admin\Resources\Orders\Widgets\OrdersSummaryWidget;
use App\Models\Order;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class OrdersListTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->actingAs(User::query()->where('email', 'admin@sitezoo.local')->firstOrFail());
    }

    public function test_status_tabs_filter_the_list(): void
    {
        $new = $this->order(Order::STATUS_NEW);
        $shipped = $this->order(Order::STATUS_SHIPPED);

        Livewire::test(ListOrders::class)
            ->assertCanSeeTableRecords([$new, $shipped])
            ->set('activeTab', Order::STATUS_SHIPPED)
            ->assertCanSeeTableRecords([$shipped])
            ->assertCanNotSeeTableRecords([$new]);
    }

    public function test_summary_counts_orders_and_skips_cancelled_in_todays_money(): void
    {
        $this->order(Order::STATUS_NEW, 100);
        $this->order(Order::STATUS_CANCELLED, 900);

        $tiles = Livewire::test(OrdersSummaryWidget::class)
            ->assertSee('Нові')
            ->instance()
            ->tiles();

        $this->assertSame('1', $tiles[0]['value']);
        $this->assertStringContainsString('100', $tiles[3]['value']);
        $this->assertStringNotContainsString('900', $tiles[3]['value']);
    }

    public function test_list_page_renders_for_admin(): void
    {
        $order = $this->order(Order::STATUS_NEW);

        $this->get('/admin/orders')
            ->assertOk()
            ->assertSee($order->number)
            ->assertSee('zoogle-orders-page', false);
    }

    private function order(string $status, float $total = 10): Order
    {
        return Order::query()->create([
            'customer_name' => 'Тест',
            'customer_phone' => '380000000000',
            'total' => $total,
            'status' => $status,
            'payment_status' => 'pending',
            'delivery_type' => Order::DELIVERY_PICKUP,
        ]);
    }
}
