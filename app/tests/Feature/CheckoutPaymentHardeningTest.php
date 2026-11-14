<?php

namespace Tests\Feature;

use App\Models\OptionGroup;
use App\Models\OptionValue;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ShopIntegrationSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class CheckoutPaymentHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.liqpay.public_key' => 'pub', 'services.liqpay.private_key' => 'prv']);
        ShopIntegrationSetting::record()->forceFill(['pickup_enabled' => true, 'pickup_address' => 'x'])->save();
    }

    public function test_guest_cannot_read_orders_by_typing_someone_elses_email(): void
    {
        [$product, , $size] = $this->makeSellableProduct();
        $victimOrder = $this->placeOrder($product, $size, 'victim@example.test');

        $this->flushSession();

        $this->get(route('account.orders.index'))->assertRedirect(route('login'));

        $attackerOrder = $this->placeOrder($product, $size, 'victim@example.test');
        $this->get(route('checkout.success', ['order' => $attackerOrder->id, 'token' => $attackerOrder->success_token]))
            ->assertOk();

        $this->get(route('account.orders.index'))
            ->assertOk()
            ->assertSee($attackerOrder->number, false)
            ->assertDontSee($victimOrder->number, false);
        $this->get(route('account.orders.show', $victimOrder))->assertNotFound();
    }

    public function test_guest_with_token_link_sees_that_order_and_keeps_it_in_the_session(): void
    {
        [$product, , $size] = $this->makeSellableProduct();
        $order = $this->placeOrder($product, $size, 'buyer@example.test');

        $this->flushSession();

        $this->get(route('account.orders.show', ['order' => $order, 'token' => $order->success_token]))->assertOk();
        $this->get(route('account.orders.show', $order))->assertOk();
        $this->get(route('account.orders.index'))->assertOk()->assertSee($order->number, false);
    }

    public function test_sandbox_callback_does_not_pay_order_when_sandbox_is_off(): void
    {
        config(['services.liqpay.sandbox' => false]);
        $order = $this->makeOrder();

        $this->liqPayCallback(['order_id' => (string) $order->id, 'status' => 'sandbox', 'amount' => (float) $order->total]);

        $this->assertSame('pending', $order->fresh()->payment_status);
    }

    public function test_sandbox_callback_pays_order_when_sandbox_is_on(): void
    {
        config(['services.liqpay.sandbox' => true]);
        $order = $this->makeOrder();

        $this->liqPayCallback(['order_id' => (string) $order->id, 'status' => 'sandbox', 'amount' => (float) $order->total]);

        $this->assertSame('paid', $order->fresh()->payment_status);
    }

    public function test_late_failure_does_not_overwrite_paid_order(): void
    {
        $order = $this->makeOrder();

        $this->liqPayCallback(['order_id' => (string) $order->id, 'status' => 'success', 'amount' => (float) $order->total]);
        $this->liqPayCallback(['order_id' => (string) $order->id, 'status' => 'failure']);

        $this->assertSame('paid', $order->fresh()->payment_status);
    }

    public function test_failure_does_not_overwrite_partially_paid_order(): void
    {
        $order = $this->makeOrder();
        $order->forceFill(['payment_status' => 'partial', 'immediate_portion_paid_at' => now()])->save();

        $this->liqPayCallback(['order_id' => $order->id.'-def', 'status' => 'failure']);

        $this->assertSame('partial', $order->fresh()->payment_status);
    }

    public function test_failure_marks_unpaid_order_as_failed(): void
    {
        $order = $this->makeOrder();

        $this->liqPayCallback(['order_id' => (string) $order->id, 'status' => 'failure']);

        $this->assertSame('failed', $order->fresh()->payment_status);
    }

    public function test_refund_still_marks_paid_order_as_failed(): void
    {
        $order = $this->makeOrder();

        $this->liqPayCallback(['order_id' => (string) $order->id, 'status' => 'success', 'amount' => (float) $order->total]);
        $this->liqPayCallback(['order_id' => (string) $order->id, 'status' => 'refund']);

        $this->assertSame('failed', $order->fresh()->payment_status);
    }

    public function test_mail_failure_does_not_break_checkout(): void
    {
        [$product, $variant, $size] = $this->makeSellableProduct(quantity: 5);
        Mail::shouldReceive('to')->once()->andThrow(new \RuntimeException('smtp down'));

        $response = $this->withSession($this->cart($product, $size))
            ->post(route('checkout.store'), $this->checkoutPayload('buyer@example.test'));

        $order = Order::query()->sole();
        $response->assertRedirect(route('checkout.success', ['order' => $order->id, 'token' => $order->success_token]));
        $this->assertSame(4, (int) $variant->fresh()->quantity);
        $this->assertSame([], session('cart', []));
    }

    public function test_ensure_admin_requires_a_password(): void
    {
        $this->artisan('shop:ensure-admin')->assertFailed();
        $this->artisan('shop:ensure-admin', ['--password' => 'Admin0000'])->assertFailed();
    }

    public function test_x_forwarded_host_does_not_change_generated_links(): void
    {
        $this->withHeaders(['X-Forwarded-Host' => 'evil.example'])
            ->get('/')
            ->assertOk()
            ->assertDontSee('evil.example', false);
    }

    public function test_checkout_submissions_are_rate_limited(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->post(route('checkout.store'), [])->assertRedirect();
        }

        $this->post(route('checkout.store'), [])->assertStatus(429);
    }

    public function test_settings_survive_app_key_rotation(): void
    {
        $oldKey = (string) config('app.key');
        ShopIntegrationSetting::record()->forceFill(['nova_poshta_api_key' => 'np-secret'])->save();

        $this->useAppKey('base64:'.base64_encode(random_bytes(32)), [$oldKey]);
        $this->artisan('shop:reencrypt-settings')->assertSuccessful();

        $this->useAppKey((string) config('app.key'), []);
        $this->assertSame('np-secret', ShopIntegrationSetting::record()->nova_poshta_api_key);
    }

    /**
     * @param  list<string>  $previousKeys
     */
    private function useAppKey(string $key, array $previousKeys): void
    {
        config(['app.key' => $key, 'app.previous_keys' => $previousKeys]);
        app()->forgetInstance('encrypter');
        Crypt::clearResolvedInstance('encrypter');
    }

    private function makeOrder(): Order
    {
        [$product, , $size] = $this->makeSellableProduct();

        return $this->placeOrder($product, $size, 'buyer@example.test');
    }

    private function placeOrder(Product $product, OptionValue $size, string $email): Order
    {
        $this->withSession($this->cart($product, $size))
            ->post(route('checkout.store'), $this->checkoutPayload($email))
            ->assertRedirect();

        return Order::query()->latest('id')->firstOrFail();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function liqPayCallback(array $payload): void
    {
        $data = base64_encode((string) json_encode($payload));

        $this->post(route('payments.liqpay.callback'), [
            'data' => $data,
            'signature' => base64_encode(sha1('prv'.$data.'prv', true)),
        ])->assertOk();
    }

    /**
     * @return array<string, mixed>
     */
    private function cart(Product $product, OptionValue $size): array
    {
        return [
            'cart' => [
                'p1' => [
                    'line_kind' => 'product',
                    'product_id' => $product->id,
                    'qty' => 1,
                    'option_value_ids' => [$size->id],
                ],
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function checkoutPayload(string $email): array
    {
        return [
            'customer_name' => 'Buyer',
            'customer_phone' => '380501112233',
            'customer_email' => $email,
            'payment_method' => 'cod',
            'delivery_type' => Order::DELIVERY_PICKUP,
        ];
    }

    /**
     * @return array{0: Product, 1: ProductVariant, 2: OptionValue}
     */
    private function makeSellableProduct(int $quantity = 5): array
    {
        $categoryGroup = OptionGroup::query()->firstOrCreate(
            ['product_type' => OptionGroup::CATALOG_PRODUCT_TYPE, 'slug' => 'category'],
            ['name' => 'Категорія', 'selection_mode' => 'single', 'value_type' => 'text', 'is_active' => true]
        );
        $root = OptionValue::query()->firstOrCreate(
            ['option_group_id' => $categoryGroup->id, 'slug' => 'catalog-root'],
            ['name' => 'Каталог', 'sort_order' => 1, 'is_active' => true]
        );
        $sizeGroup = OptionGroup::query()->create([
            'product_type' => OptionGroup::CATALOG_PRODUCT_TYPE,
            'name' => 'Розмір',
            'slug' => 'size-hardening-'.uniqid(),
            'selection_mode' => 'single',
            'value_type' => 'text',
            'is_active' => true,
        ]);
        $size = OptionValue::query()->create([
            'option_group_id' => $sizeGroup->id,
            'name' => 'S',
            'slug' => 's-hardening-'.uniqid(),
            'sort_order' => 1,
            'is_active' => true,
        ]);
        $product = Product::query()->create([
            'title' => 'Hardening product '.uniqid(),
            'slug' => 'hardening-product-'.uniqid(),
            'product_type' => OptionGroup::CATALOG_PRODUCT_TYPE,
            'category_parent_value_id' => $root->id,
            'price' => 1000,
            'is_available' => true,
            'stock_status' => Product::STOCK_IN_STOCK,
            'variant_options' => [
                ['option_group_id' => $categoryGroup->id, 'option_value_ids' => [$root->id]],
                ['option_group_id' => $sizeGroup->id, 'option_value_ids' => [$size->id]],
            ],
        ]);
        $variant = ProductVariant::query()->create([
            'product_id' => $product->id,
            'price' => 1000,
            'quantity' => $quantity,
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

        return [$product, $variant, $size];
    }
}
