<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShopAccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_from_account(): void
    {
        $this->get(route('account.index'))
            ->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_open_account_dashboard(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('account.index'))
            ->assertOk()
            ->assertSee($user->name, false);
    }

    public function test_user_can_view_own_order_in_account(): void
    {
        $user = User::factory()->create();
        $order = Order::query()->create([
            'user_id' => $user->id,
            'customer_name' => 'T',
            'customer_phone' => '380000000000',
            'total' => 10,
            'status' => Order::STATUS_NEW,
            'payment_status' => 'pending',
            'delivery_type' => Order::DELIVERY_PICKUP,
        ]);

        $this->actingAs($user)
            ->get(route('account.orders.show', $order))
            ->assertOk()
            ->assertSee($order->number, false);
    }

    public function test_user_cannot_view_another_users_order(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        $order = Order::query()->create([
            'user_id' => $a->id,
            'customer_name' => 'T',
            'customer_phone' => '380000000000',
            'total' => 10,
            'status' => Order::STATUS_NEW,
            'payment_status' => 'pending',
            'delivery_type' => Order::DELIVERY_PICKUP,
        ]);

        $this->actingAs($b)
            ->get(route('account.orders.show', $order))
            ->assertForbidden();
    }

    public function test_guest_cannot_toggle_favorites(): void
    {
        $this->postJson(route('favorites.toggle'), ['product_id' => 1])
            ->assertUnauthorized();
    }

    public function test_authenticated_user_can_toggle_favorite(): void
    {
        $user = User::factory()->create();
        $product = Product::query()->create([
            'title' => 'Test product',
            'slug' => 'test-product-'.uniqid('', true),
            'product_type' => 'product',
            'is_available' => true,
            'price' => 9.99,
        ]);

        $this->actingAs($user)
            ->postJson(route('favorites.toggle'), ['product_id' => $product->id])
            ->assertOk()
            ->assertJson(['favorited' => true]);

        $this->assertTrue($user->favoriteProducts()->whereKey($product->id)->exists());

        $this->actingAs($user)
            ->postJson(route('favorites.toggle'), ['product_id' => $product->id])
            ->assertOk()
            ->assertJson(['favorited' => false]);

        $this->assertFalse($user->favoriteProducts()->whereKey($product->id)->exists());
    }
}
