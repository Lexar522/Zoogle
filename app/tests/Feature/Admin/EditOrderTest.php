<?php

namespace Tests\Feature\Admin;

use App\Filament\Admin\Resources\Orders\Pages\EditOrder;
use App\Models\Order;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
