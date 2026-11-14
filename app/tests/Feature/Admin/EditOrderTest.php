<?php

namespace Tests\Feature\Admin;

use App\Filament\Admin\Resources\Orders\Pages\EditOrder;
use App\Filament\Admin\Resources\Orders\RelationManagers\OrderItemsRelationManager;
use App\Mail\OrderStatusUpdated;
use App\Models\Order;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

class EditOrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_edit_order_page_renders_for_admin(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $admin = User::query()->where('email', 'admin@sitezoo.local')->firstOrFail();

        $order = Order::query()->create([
            'customer_name' => 'Test',
            'customer_phone' => '380000000000',
            'total' => 10,
            'status' => Order::STATUS_NEW,
            'payment_status' => 'pending',
            'delivery_type' => Order::DELIVERY_PICKUP,
        ]);

        $this->actingAs($admin);

        Livewire::test(EditOrder::class, ['record' => (string) $order->id])
            ->assertSuccessful();
    }

    public function test_edit_order_items_table_keeps_money_and_icon_actions(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $admin = User::query()->where('email', 'admin@sitezoo.local')->firstOrFail();

        $order = Order::query()->create([
            'customer_name' => 'Test',
            'customer_phone' => '380000000000',
            'total' => 573,
            'status' => Order::STATUS_NEW,
            'payment_status' => 'pending',
            'delivery_type' => Order::DELIVERY_PICKUP,
        ]);
        $order->items()->create([
            'title_snapshot' => 'М\'який гамак для гризунів',
            'price' => 573,
            'qty' => 1,
            'line_total' => 573,
            'option_value_ids' => [],
        ]);

        $this->actingAs($admin);

        Livewire::test(OrderItemsRelationManager::class, [
            'ownerRecord' => $order,
            'pageClass' => EditOrder::class,
        ])
            ->assertSuccessful()
            ->assertSee('fi-ta-order-items-compact', false)
            ->assertSee('fi-ta-col-qty', false)
            ->assertSee('fi-ta-col-money', false)
            ->assertSee('М\'який гамак для гризунів')
            ->assertSee('Змінити')
            ->assertSee('Видалити')
            ->assertSee('573');
    }

    public function test_saving_new_status_emails_the_customer(): void
    {
        Mail::fake();
        $this->seed(RolesAndPermissionsSeeder::class);

        $admin = User::query()->where('email', 'admin@sitezoo.local')->firstOrFail();
        $order = Order::query()->create([
            'customer_name' => 'Test',
            'customer_phone' => '380000000000',
            'customer_email' => 'buyer@example.com',
            'total' => 10,
            'status' => Order::STATUS_NEW,
            'payment_status' => 'pending',
            'delivery_type' => Order::DELIVERY_PICKUP,
        ]);

        $this->actingAs($admin);

        Livewire::test(EditOrder::class, ['record' => (string) $order->id])
            ->fillForm([
                'status' => Order::STATUS_PROCESSING,
                'customer_update_message' => 'Вже комплектуємо.',
                'notify_customer_email' => true,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(Order::STATUS_PROCESSING, $order->fresh()->status);
        $this->assertSame(1, $order->updates()->count());
        Mail::assertSent(OrderStatusUpdated::class, function (OrderStatusUpdated $mail) use ($order): bool {
            return $mail->hasTo('buyer@example.com') && $mail->order->is($order);
        });
    }
}
