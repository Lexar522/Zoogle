<?php

namespace Tests\Feature;

use App\Models\OptionGroup;
use App\Models\OptionValue;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ShopIntegrationSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class CategoryCheckoutValidationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{Product, OptionValue, OptionValue, OptionValue}
     */
    private function makeProductInCategoryWithRootFlags(
        bool $pickupOnly,
        bool $deferOnline,
    ): array {
        $categoryGroup = OptionGroup::query()->firstOrCreate(
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

        $root = OptionValue::query()->create([
            'option_group_id' => $categoryGroup->id,
            'parent_id' => null,
            'name' => 'Root animal',
            'slug' => 'root-animal-'.uniqid(),
            'sort_order' => 1,
            'is_active' => true,
            'pickup_only_subtree' => $pickupOnly,
            'defer_online_payment' => $deferOnline,
        ]);

        $child = OptionValue::query()->create([
            'option_group_id' => $categoryGroup->id,
            'parent_id' => $root->id,
            'name' => 'Child',
            'slug' => 'child-'.uniqid(),
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $sizeGroup = OptionGroup::query()->create([
            'product_type' => OptionGroup::CATALOG_PRODUCT_TYPE,
            'name' => 'Size',
            'slug' => 'size-'.uniqid(),
            'selection_mode' => 'single',
            'value_type' => 'text',
            'is_active' => true,
        ]);

        $size = OptionValue::query()->create([
            'option_group_id' => $sizeGroup->id,
            'name' => 'M',
            'slug' => 'm-'.uniqid(),
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $product = Product::query()->create([
            'title' => 'Test restricted',
            'slug' => 'test-restricted-'.uniqid(),
            'product_type' => OptionGroup::CATALOG_PRODUCT_TYPE,
            'category_parent_value_id' => $root->id,
            'category_value_id' => $child->id,
            'description' => 'd',
            'short_description' => 's',
            'is_available' => true,
            'variant_options' => [
                [
                    'option_group_id' => $categoryGroup->id,
                    'option_value_ids' => [$child->id],
                ],
                [
                    'option_group_id' => $sizeGroup->id,
                    'option_value_ids' => [$size->id],
                ],
            ],
        ]);

        ProductVariant::query()->create([
            'product_id' => $product->id,
            'price' => 100,
            'quantity' => 2,
            'is_available' => true,
            'is_low_stock' => false,
            'is_visible' => true,
            'allows_preorder' => false,
            'is_sold' => false,
            'sort_order' => 1,
            'options' => [
                ['option_group_id' => $sizeGroup->id, 'option_value_id' => $size->id],
            ],
        ]);

        $product->load('variants');

        return [$product, $root, $child, $size];
    }

    public function test_checkout_rejects_nova_poshta_when_pickup_only_category(): void
    {
        [$product, , , $size] = $this->makeProductInCategoryWithRootFlags(true, false);

        $this->withSession([
            'cart' => [
                'k1' => [
                    'line_kind' => 'product',
                    'product_id' => $product->id,
                    'qty' => 1,
                    'option_value_ids' => [$size->id],
                ],
            ],
        ]);

        $response = $this->post(route('checkout.store'), [
            'customer_name' => 'Test',
            'customer_phone' => '380501112233',
            'customer_email' => 't@ex.test',
            'payment_method' => 'cod',
            'delivery_type' => Order::DELIVERY_NOVA_POSHTA_WAREHOUSE,
            'delivery_city' => 'Київ',
            'delivery_branch' => '1',
        ]);

        $response->assertSessionHasErrors('delivery_type');
    }

    public function test_checkout_rejects_online_payment_when_defer_flag_on_category(): void
    {
        [$product, , , $size] = $this->makeProductInCategoryWithRootFlags(false, true);

        $this->withSession([
            'cart' => [
                'k1' => [
                    'line_kind' => 'product',
                    'product_id' => $product->id,
                    'qty' => 1,
                    'option_value_ids' => [$size->id],
                ],
            ],
        ]);

        $response = $this->post(route('checkout.store'), [
            'customer_name' => 'Test',
            'customer_phone' => '380501112233',
            'customer_email' => 't@ex.test',
            'payment_method' => 'online',
            'delivery_type' => Order::DELIVERY_PICKUP,
        ]);

        $response->assertSessionHasErrors('payment_method');
    }

    public function test_checkout_mixed_cart_online_redirects_to_immediate_liqpay_leg(): void
    {
        [$deferProduct, , , $deferSize] = $this->makeProductInCategoryWithRootFlags(false, true);
        [$accessory, , , $accSize] = $this->makeProductInCategoryWithRootFlags(false, false);

        Config::set('services.liqpay.public_key', 'pk_test');
        Config::set('services.liqpay.private_key', 'sk_test');

        $this->withSession([
            'cart' => [
                'k1' => [
                    'line_kind' => 'product',
                    'product_id' => $deferProduct->id,
                    'qty' => 1,
                    'option_value_ids' => [$deferSize->id],
                ],
                'k2' => [
                    'line_kind' => 'product',
                    'product_id' => $accessory->id,
                    'qty' => 1,
                    'option_value_ids' => [$accSize->id],
                ],
            ],
        ]);

        $response = $this->post(route('checkout.store'), [
            'customer_name' => 'Test',
            'customer_phone' => '380501112233',
            'customer_email' => 't@ex.test',
            'payment_method' => 'online',
            'delivery_type' => Order::DELIVERY_PICKUP,
        ]);

        $response->assertSessionHasNoErrors();

        $order = Order::query()->latest('id')->first();
        $this->assertNotNull($order);
        $this->assertTrue($order->mixed_payment_plan);
        $this->assertTrue($order->deferred_online_payment);
        $this->assertGreaterThan(0, (float) $order->immediate_subtotal);
        $this->assertGreaterThan(0, (float) $order->deferred_subtotal);

        $response->assertRedirect(route('checkout.payment', [
            'order' => $order->id,
            'token' => $order->success_token,
            'leg' => 'immediate',
        ]));
    }

    public function test_checkout_allows_pickup_and_cod_for_restricted_category(): void
    {
        [$product, , , $size] = $this->makeProductInCategoryWithRootFlags(true, true);

        $this->withSession([
            'cart' => [
                'k1' => [
                    'line_kind' => 'product',
                    'product_id' => $product->id,
                    'qty' => 1,
                    'option_value_ids' => [$size->id],
                ],
            ],
        ]);

        $response = $this->post(route('checkout.store'), [
            'customer_name' => 'Test',
            'customer_phone' => '380501112233',
            'customer_email' => 't@ex.test',
            'payment_method' => 'cod',
            'delivery_type' => Order::DELIVERY_PICKUP,
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();
        $this->assertDatabaseHas('orders', [
            'deferred_online_payment' => true,
        ]);
    }

    public function test_animal_cart_offers_only_pickup_and_taxi(): void
    {
        [$product, , , $size] = $this->makeProductInCategoryWithRootFlags(true, false);
        $this->withSession(['cart' => $this->cartWith($product, $size)]);

        $this->get(route('checkout.create'))
            ->assertOk()
            ->assertSee('value="'.Order::DELIVERY_PICKUP.'"', false)
            ->assertSee('value="'.Order::DELIVERY_TAXI.'"', false)
            ->assertSee('id="taxi-map"', false)
            ->assertDontSee('value="'.Order::DELIVERY_NOVA_POSHTA_WAREHOUSE.'"', false)
            ->assertDontSee('value="'.Order::DELIVERY_NOVA_POSHTA_COURIER.'"', false);
    }

    public function test_regular_cart_offers_taxi_next_to_nova_poshta(): void
    {
        [$product, , , $size] = $this->makeProductInCategoryWithRootFlags(false, false);
        $this->withSession(['cart' => $this->cartWith($product, $size)]);

        $this->get(route('checkout.create'))
            ->assertOk()
            ->assertSee('value="'.Order::DELIVERY_TAXI.'"', false)
            ->assertSee('value="'.Order::DELIVERY_NOVA_POSHTA_WAREHOUSE.'"', false);
    }

    public function test_animal_order_by_taxi_keeps_address_and_map_point(): void
    {
        [$product, , , $size] = $this->makeProductInCategoryWithRootFlags(true, false);
        $this->withSession(['cart' => $this->cartWith($product, $size)]);

        $this->post(route('checkout.store'), $this->checkoutPayload([
            'delivery_type' => Order::DELIVERY_TAXI,
            'delivery_address' => 'вул. Шевченка, 10, під’їзд 2',
            'delivery_lat' => '50.4501',
            'delivery_lng' => '30.5234',
            'delivery_city' => 'Київ',
            'delivery_branch' => 'Відділення 1',
        ]))->assertSessionHasNoErrors()->assertRedirect();

        $order = Order::query()->latest('id')->firstOrFail();
        $this->assertSame(Order::DELIVERY_TAXI, $order->delivery_type);
        $this->assertSame('вул. Шевченка, 10, під’їзд 2', $order->deliverySummaryText());
        $this->assertNull($order->delivery_city, 'Поля Нової Пошти для таксі не зберігаються');
        $this->assertNull($order->delivery_branch);
        $this->assertSame(['lat' => 50.4501, 'lng' => 30.5234], $order->courierDeliveryMapPosition());
        $this->assertStringContainsString('50.4501%2C30.5234', (string) $order->deliveryMapUrl());
    }

    public function test_taxi_needs_an_address(): void
    {
        [$product, , , $size] = $this->makeProductInCategoryWithRootFlags(true, false);
        $this->withSession(['cart' => $this->cartWith($product, $size)]);

        $this->post(route('checkout.store'), $this->checkoutPayload([
            'delivery_type' => Order::DELIVERY_TAXI,
            'delivery_lat' => '50.4501',
            'delivery_lng' => '30.5234',
        ]))->assertSessionHasErrors('delivery_address');
    }

    public function test_taxi_switched_off_in_settings_is_rejected(): void
    {
        ShopIntegrationSetting::record()->update(['taxi_delivery_enabled' => false]);
        [$product, , , $size] = $this->makeProductInCategoryWithRootFlags(true, false);
        $this->withSession(['cart' => $this->cartWith($product, $size)]);

        $this->get(route('checkout.create'))
            ->assertOk()
            ->assertDontSee('value="'.Order::DELIVERY_TAXI.'"', false);
        $this->post(route('checkout.store'), $this->checkoutPayload([
            'delivery_type' => Order::DELIVERY_TAXI,
            'delivery_address' => 'вул. Шевченка, 10',
        ]))->assertSessionHasErrors('delivery_type');
    }

    public function test_animal_cart_uses_taxi_when_pickup_is_off(): void
    {
        ShopIntegrationSetting::record()->update(['pickup_enabled' => false]);
        [$product, , , $size] = $this->makeProductInCategoryWithRootFlags(true, false);
        $this->withSession(['cart' => $this->cartWith($product, $size)]);

        $this->get(route('checkout.create'))
            ->assertOk()
            ->assertSee('value="'.Order::DELIVERY_TAXI.'"', false)
            ->assertDontSee('value="'.Order::DELIVERY_PICKUP.'"', false);

        ShopIntegrationSetting::record()->update(['taxi_delivery_enabled' => false]);
        $this->get(route('checkout.create'))->assertRedirect(route('catalog.index'));
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function cartWith(Product $product, OptionValue $size): array
    {
        return [
            'k1' => [
                'line_kind' => 'product',
                'product_id' => $product->id,
                'qty' => 1,
                'option_value_ids' => [$size->id],
            ],
        ];
    }

    /**
     * @param  array<string, string>  $delivery
     * @return array<string, string>
     */
    private function checkoutPayload(array $delivery): array
    {
        return [
            'customer_name' => 'Test',
            'customer_phone' => '380501112233',
            'customer_email' => 't@ex.test',
            'payment_method' => 'cod',
            ...$delivery,
        ];
    }

    public function test_account_deferred_payment_route_requires_unlock(): void
    {
        $user = User::factory()->create();
        [$product, , , $size] = $this->makeProductInCategoryWithRootFlags(false, true);

        $this->be($user);

        $this->withSession([
            'cart' => [
                'k1' => [
                    'line_kind' => 'product',
                    'product_id' => $product->id,
                    'qty' => 1,
                    'option_value_ids' => [$size->id],
                ],
            ],
        ]);

        $this->post(route('checkout.store'), [
            'customer_name' => 'Test',
            'customer_phone' => '380501112233',
            'customer_email' => 't@ex.test',
            'payment_method' => 'cod',
            'delivery_type' => Order::DELIVERY_PICKUP,
        ]);

        $order = Order::query()->latest('id')->first();
        $this->assertNotNull($order);
        $order->user_id = $user->id;
        $order->save();

        $r = $this->get(route('account.orders.payment', $order));
        $r->assertRedirect(route('account.orders.show', $order));
    }
}
