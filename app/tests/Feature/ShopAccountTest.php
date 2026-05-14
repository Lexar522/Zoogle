<?php

namespace Tests\Feature;

use App\Models\Bundle;
use App\Models\BundleItem;
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
            ->assertSee($user->checkoutDisplayName(), false);
    }

    public function test_user_can_update_profile_from_account(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patch(route('account.profile.update'), [
                'first_name' => 'Олена',
                'last_name' => 'Коваленко',
                'phone' => '+380501112233',
            ])
            ->assertRedirect(route('account.index'))
            ->assertSessionHas('status', 'profile-saved');

        $user->refresh();
        $this->assertSame('Олена', $user->first_name);
        $this->assertSame('Коваленко', $user->last_name);
        $this->assertSame('+380501112233', $user->phone);
        $this->assertSame('Олена Коваленко', $user->checkoutDisplayName());
    }

    public function test_checkout_prefills_profile_for_logged_in_user(): void
    {
        $user = User::factory()->create([
            'first_name' => 'Іван',
            'last_name' => 'Тестовий',
            'phone' => '+380971112233',
        ]);

        $product = Product::query()->create([
            'title' => 'Checkout prefill product',
            'slug' => 'checkout-prefill-'.uniqid('', true),
            'product_type' => 'product',
            'is_available' => true,
            'price' => 10,
        ]);

        $bundle = Bundle::query()->create([
            'title' => 'Checkout prefill bundle',
            'slug' => 'checkout-prefill-bundle-'.uniqid('', true),
            'is_visible' => true,
            'is_active' => true,
        ]);

        BundleItem::query()->create([
            'bundle_id' => $bundle->id,
            'product_id' => $product->id,
            'qty' => 1,
            'sort_order' => 1,
        ]);

        $this->actingAs($user)
            ->withSession([
                'cart' => [
                    'bundle:'.$bundle->id => [
                        'line_kind' => 'bundle',
                        'bundle_id' => $bundle->id,
                        'qty' => 1,
                        'option_value_ids' => [],
                    ],
                ],
            ])
            ->get(route('checkout.create'))
            ->assertOk()
            ->assertSee('Іван Тестовий', false)
            ->assertSee('+380971112233', false);
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

    public function test_user_can_view_guest_order_when_email_matches(): void
    {
        $user = User::factory()->create([
            'email' => 'buyer@example.test',
        ]);

        $order = Order::query()->create([
            'user_id' => null,
            'customer_name' => 'Guest',
            'customer_phone' => '380000000001',
            'customer_email' => 'buyer@example.test',
            'total' => 15,
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

    public function test_orders_index_filters_deferred_pending(): void
    {
        $user = User::factory()->create();
        Order::query()->create([
            'user_id' => $user->id,
            'customer_name' => 'T',
            'customer_phone' => '380000000000',
            'total' => 10,
            'status' => Order::STATUS_NEW,
            'payment_status' => 'pending',
            'delivery_type' => Order::DELIVERY_PICKUP,
            'deferred_online_payment' => true,
            'online_payment_unlocked_at' => null,
        ]);
        Order::query()->create([
            'user_id' => $user->id,
            'customer_name' => 'T',
            'customer_phone' => '380000000000',
            'total' => 20,
            'status' => Order::STATUS_NEW,
            'payment_status' => 'pending',
            'delivery_type' => Order::DELIVERY_PICKUP,
            'deferred_online_payment' => false,
        ]);

        $this->actingAs($user)
            ->get(route('account.orders.index', ['payment' => 'deferred_pending']))
            ->assertOk()
            ->assertSee('10.00 UAH', false)
            ->assertDontSee('20.00 UAH', false);
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
            ->assertJson(['favorited' => true, 'favorites_count' => 1]);

        $this->assertTrue($user->favoriteProducts()->whereKey($product->id)->exists());

        $this->actingAs($user)
            ->postJson(route('favorites.toggle'), ['product_id' => $product->id])
            ->assertOk()
            ->assertJson(['favorited' => false, 'favorites_count' => 0]);

        $this->assertFalse($user->favoriteProducts()->whereKey($product->id)->exists());
    }
}
