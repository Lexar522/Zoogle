<?php

namespace Tests\Feature;

use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderTrackTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_track_requires_valid_token(): void
    {
        $order = Order::query()->create([
            'customer_name' => 'T',
            'customer_phone' => '380000000000',
            'total' => 10,
            'status' => Order::STATUS_NEW,
            'payment_status' => 'pending',
            'delivery_type' => Order::DELIVERY_PICKUP,
        ]);

        $this->get(route('orders.track', ['order' => $order, 'token' => 'wrong']))
            ->assertNotFound();

        $this->followingRedirects()
            ->get(route('orders.track', ['order' => $order, 'token' => $order->success_token]))
            ->assertOk()
            ->assertSee($order->number, false);
    }

    public function test_order_track_remembers_email_for_account_without_google(): void
    {
        $order = Order::query()->create([
            'customer_name' => 'T',
            'customer_phone' => '380000000000',
            'customer_email' => 'guest@example.test',
            'total' => 10,
            'status' => Order::STATUS_NEW,
            'payment_status' => 'pending',
            'delivery_type' => Order::DELIVERY_PICKUP,
        ]);

        $this->get(route('orders.track', ['order' => $order, 'token' => $order->success_token]))
            ->assertRedirect(route('orders.track', $order));

        $this->get(route('account.orders.show', $order))
            ->assertOk()
            ->assertSee($order->number, false);
    }
}
