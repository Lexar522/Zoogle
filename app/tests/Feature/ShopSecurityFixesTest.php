<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\Users\Pages\EditUser;
use App\Models\OptionGroup;
use App\Models\OptionValue;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ShopAnalyticsEvent;
use App\Models\ShopIntegrationSetting;
use App\Models\ShopMediaSetting;
use App\Models\User;
use App\Support\ListingPhotoDerivative;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ShopSecurityFixesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.liqpay.public_key' => 'pub', 'services.liqpay.private_key' => 'prv', 'services.liqpay.sandbox' => false]);
        ShopIntegrationSetting::record()->forceFill(['pickup_enabled' => true, 'pickup_address' => 'x'])->save();
    }

    /** @return array{0: Product, 1: ProductVariant, 2: OptionValue} */
    private function sellable(int $qty = 2, float $price = 1000, string $title = 'Товар'): array
    {
        $category = OptionGroup::query()->firstOrCreate(
            ['product_type' => OptionGroup::CATALOG_PRODUCT_TYPE, 'slug' => 'category'],
            ['name' => 'Категорія', 'selection_mode' => 'single', 'value_type' => 'text', 'is_active' => true],
        );
        $root = OptionValue::query()->firstOrCreate(
            ['option_group_id' => $category->id, 'slug' => 'fix-root'],
            ['name' => 'Корінь', 'sort_order' => 1, 'is_active' => true],
        );
        $sizes = OptionGroup::query()->create(['product_type' => OptionGroup::CATALOG_PRODUCT_TYPE, 'name' => 'Розмір', 'slug' => 'size-'.uniqid(), 'selection_mode' => 'single', 'value_type' => 'text', 'is_active' => true]);
        $size = OptionValue::query()->create(['option_group_id' => $sizes->id, 'name' => 'S', 'slug' => 's-'.uniqid(), 'sort_order' => 1, 'is_active' => true]);
        $product = Product::query()->create([
            'title' => $title,
            'slug' => 'fix-'.uniqid(),
            'product_type' => OptionGroup::CATALOG_PRODUCT_TYPE,
            'category_parent_value_id' => $root->id,
            'price' => $price,
            'is_available' => true,
            'stock_status' => Product::STOCK_IN_STOCK,
            'variant_options' => [
                ['option_group_id' => $category->id, 'option_value_ids' => [$root->id]],
                ['option_group_id' => $sizes->id, 'option_value_ids' => [$size->id]],
            ],
        ]);
        $variant = ProductVariant::query()->create([
            'product_id' => $product->id,
            'price' => $price,
            'quantity' => $qty,
            'is_available' => true,
            'is_low_stock' => false,
            'is_visible' => true,
            'allows_preorder' => false,
            'is_sold' => false,
            'sort_order' => 1,
            'options' => [['option_group_id' => $sizes->id, 'option_value_id' => $size->id]],
        ]);

        return [$product, $variant, $size];
    }

    private function simpleProduct(?int $stock): Product
    {
        $category = OptionGroup::query()->firstOrCreate(
            ['product_type' => OptionGroup::CATALOG_PRODUCT_TYPE, 'slug' => 'category'],
            ['name' => 'Категорія', 'selection_mode' => 'single', 'value_type' => 'text', 'is_active' => true],
        );
        $root = OptionValue::query()->firstOrCreate(
            ['option_group_id' => $category->id, 'slug' => 'fix-root'],
            ['name' => 'Корінь', 'sort_order' => 1, 'is_active' => true],
        );

        return Product::query()->create([
            'title' => 'Простий',
            'slug' => 'simple-'.uniqid(),
            'product_type' => OptionGroup::CATALOG_PRODUCT_TYPE,
            'category_parent_value_id' => $root->id,
            'price' => 100,
            'is_available' => true,
            'stock_status' => Product::STOCK_IN_STOCK,
            'stock_quantity' => $stock,
            'variant_options' => [['option_group_id' => $category->id, 'option_value_ids' => [$root->id]]],
        ]);
    }

    /** @param list<int> $optionValueIds */
    private function cart(Product $product, array $optionValueIds = [], int $qty = 1): array
    {
        return ['cart' => ['p1' => ['line_kind' => 'product', 'product_id' => $product->id, 'qty' => $qty, 'option_value_ids' => $optionValueIds]]];
    }

    private function payload(array $extra = []): array
    {
        return [
            'customer_name' => 'Покупець',
            'customer_phone' => '+38 (050) 111-22-33',
            'customer_email' => 'buyer@example.test',
            'payment_method' => 'cod',
            'delivery_type' => Order::DELIVERY_PICKUP,
            ...$extra,
        ];
    }

    private function place(Product $product, array $optionValueIds = [], int $qty = 1, array $extra = []): Order
    {
        $this->flushSession();
        $this->withSession($this->cart($product, $optionValueIds, $qty))
            ->post(route('checkout.store'), $this->payload($extra))
            ->assertRedirect();

        return Order::query()->latest('id')->firstOrFail();
    }

    private function liqpay(array $payload)
    {
        $data = base64_encode(json_encode($payload));

        return $this->post(route('payments.liqpay.callback'), [
            'data' => $data,
            'signature' => base64_encode(sha1('prv'.$data.'prv', true)),
        ]);
    }

    private function staff(string $role): User
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create();
        $user->assignRole(Role::firstOrCreate(['name' => $role]));

        return $user;
    }

    public function test_manager_cannot_manage_users_or_payment_keys(): void
    {
        $manager = $this->staff('manager');
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->actingAs($manager);
        $this->get('/admin/users/'.$admin->id.'/edit')->assertForbidden();
        $this->get('/admin/users/'.$manager->id.'/edit')->assertForbidden();
        $this->get('/admin/users/create')->assertForbidden();
        $this->get('/admin/users')->assertForbidden();
        $this->get('/admin/shop-integrations')->assertForbidden();
        $this->get('/admin/orders')->assertOk();

        $this->actingAs($admin);
        $this->get('/admin/users')->assertOk();
        $this->get('/admin/users/'.$manager->id.'/edit')->assertOk();
        $this->get('/admin/shop-integrations')->assertOk();

        Livewire::test(EditUser::class, ['record' => (string) $manager->id])
            ->set('data.roles', ['admin'])
            ->call('save');
        $this->assertTrue(User::query()->find($manager->id)->hasRole('admin'));
    }

    public function test_payment_is_recorded_when_stock_is_gone_after_failed_attempt(): void
    {
        [$product, $variant, $size] = $this->sellable(1);
        $order = $this->place($product, [$size->id]);

        $this->liqpay(['order_id' => (string) $order->id, 'status' => 'failure'])->assertOk();
        $this->assertSame(1, (int) $variant->fresh()->quantity);

        $this->place($product, [$size->id]);
        $this->assertSame(0, (int) $variant->fresh()->quantity);

        $this->liqpay(['order_id' => (string) $order->id, 'status' => 'success', 'amount' => (float) $order->total, 'payment_id' => 7])->assertOk();

        $order->refresh();
        $this->assertSame('paid', $order->payment_status);
        $this->assertNotNull($order->paid_at);
        $this->assertStringContainsString('товару вже немає', (string) $order->payment_issue);
        $this->assertSame(0, (int) $variant->fresh()->quantity);
    }

    public function test_payment_after_failure_takes_stock_back_when_available(): void
    {
        [$product, $variant, $size] = $this->sellable(3);
        $order = $this->place($product, [$size->id]);
        $this->liqpay(['order_id' => (string) $order->id, 'status' => 'failure']);
        $this->assertSame(3, (int) $variant->fresh()->quantity);

        $this->liqpay(['order_id' => (string) $order->id, 'status' => 'success', 'amount' => (float) $order->total])->assertOk();

        $this->assertSame(2, (int) $variant->fresh()->quantity);
        $this->assertNull($order->fresh()->payment_issue);
    }

    public function test_cancelled_order_cannot_be_paid_and_late_money_is_flagged(): void
    {
        [$product, $variant, $size] = $this->sellable(3);
        $order = $this->place($product, [$size->id]);
        $order->status = Order::STATUS_CANCELLED;
        $order->save();

        $this->get(route('checkout.payment', ['order' => $order->id, 'token' => $order->success_token]))
            ->assertRedirect(route('checkout.success', ['order' => $order->id, 'token' => $order->success_token]));

        $this->liqpay(['order_id' => (string) $order->id, 'status' => 'success', 'amount' => (float) $order->total])->assertOk();

        $order->refresh();
        $this->assertSame(Order::STATUS_CANCELLED, $order->status);
        $this->assertSame('paid', $order->payment_status);
        $this->assertStringContainsString('скасоване', (string) $order->payment_issue);
        $this->assertSame(3, (int) $variant->fresh()->quantity);
    }

    public function test_retrying_payment_checks_stock(): void
    {
        [$product, $variant, $size] = $this->sellable(1);
        $order = $this->place($product, [$size->id]);
        $this->liqpay(['order_id' => (string) $order->id, 'status' => 'failure']);
        $url = route('checkout.payment', ['order' => $order->id, 'token' => $order->success_token]);

        $this->get($url)->assertOk();
        $this->assertSame(0, (int) $variant->fresh()->quantity);
        $this->assertSame('pending', $order->fresh()->payment_status);

        $this->liqpay(['order_id' => (string) $order->id, 'status' => 'failure']);
        $this->assertSame(1, (int) $variant->fresh()->quantity);

        $this->place($product, [$size->id]);
        $this->get($url)->assertRedirect();
        $this->assertSame('failed', $order->fresh()->payment_status);
        $this->assertSame(0, (int) $variant->fresh()->quantity);
    }

    public function test_reopening_cancelled_order_takes_stock_again(): void
    {
        [$product, $variant, $size] = $this->sellable(3);
        $order = $this->place($product, [$size->id], 2);
        $order->status = Order::STATUS_CANCELLED;
        $order->save();
        $this->assertSame(3, (int) $variant->fresh()->quantity);

        $order = $order->fresh();
        $order->status = Order::STATUS_NEW;
        $order->save();
        $this->assertSame(1, (int) $variant->fresh()->quantity);
        $this->assertTrue((bool) $order->fresh()->inventory_committed);

        $order = $order->fresh();
        $order->status = Order::STATUS_CANCELLED;
        $order->save();
        $this->assertSame(3, (int) $variant->fresh()->quantity);

        $variant->fresh()->forceFill(['quantity' => 1])->save();
        $order = $order->fresh();
        $order->status = Order::STATUS_NEW;
        try {
            $order->save();
            $this->fail('Без товару замовлення не можна повернути в роботу.');
        } catch (ValidationException) {
            $this->assertSame(Order::STATUS_CANCELLED, $order->fresh()->status);
            $this->assertSame(1, (int) $variant->fresh()->quantity);
        }
    }

    public function test_wrong_amount_or_currency_is_flagged_and_not_counted_as_paid(): void
    {
        [$product, , $size] = $this->sellable(5);

        $order = $this->place($product, [$size->id]);
        $this->liqpay(['order_id' => (string) $order->id, 'status' => 'success', 'amount' => 1.00])->assertOk();
        $this->assertSame('pending', $order->fresh()->payment_status);
        $this->assertStringContainsString('Сума платежу', (string) $order->fresh()->payment_issue);

        $order = $this->place($product, [$size->id]);
        $this->liqpay(['order_id' => (string) $order->id, 'status' => 'success', 'amount' => (float) $order->total * 0.995])->assertOk();
        $this->assertSame('pending', $order->fresh()->payment_status);

        $order = $this->place($product, [$size->id]);
        $this->liqpay(['order_id' => (string) $order->id, 'status' => 'success'])->assertOk();
        $this->assertSame('pending', $order->fresh()->payment_status);

        $order = $this->place($product, [$size->id]);
        $this->liqpay(['order_id' => (string) $order->id, 'status' => 'success', 'currency' => 'USD', 'amount' => (float) $order->total])->assertOk();
        $this->assertSame('pending', $order->fresh()->payment_status);
        $this->assertStringContainsString('USD', (string) $order->fresh()->payment_issue);

        $order = $this->place($product, [$size->id]);
        $this->liqpay(['order_id' => (string) $order->id, 'status' => 'success', 'currency' => 'UAH', 'amount' => (float) $order->total])->assertOk();
        $this->assertSame('paid', $order->fresh()->payment_status);
    }

    public function test_repeated_callback_keeps_first_paid_at(): void
    {
        [$product, , $size] = $this->sellable(5);
        $order = $this->place($product, [$size->id]);
        $this->liqpay(['order_id' => $order->id.'-imm', 'status' => 'success', 'amount' => (float) $order->total]);
        $first = (string) $order->fresh()->paid_at;

        $this->travel(5)->minutes();
        $this->liqpay(['order_id' => $order->id.'-imm', 'status' => 'success', 'amount' => (float) $order->total]);

        $this->assertSame($first, (string) $order->fresh()->paid_at);
    }

    public function test_old_unpaid_online_orders_are_cancelled_and_stock_returned(): void
    {
        [$product, $variant, $size] = $this->sellable(5);

        $old = $this->place($product, [$size->id], 1, ['payment_method' => 'online']);
        $old->forceFill(['placed_at' => now()->subHours(49)])->save();
        $fresh = $this->place($product, [$size->id], 1, ['payment_method' => 'online']);
        $cod = $this->place($product, [$size->id]);
        $cod->forceFill(['placed_at' => now()->subHours(49)])->save();
        $deferred = $this->place($product, [$size->id], 1, ['payment_method' => 'online']);
        $deferred->forceFill(['placed_at' => now()->subHours(49), 'deferred_online_payment' => true])->save();
        $this->assertSame(1, (int) $variant->fresh()->quantity);

        $this->artisan('shop:cancel-unpaid-orders')->assertSuccessful();

        $this->assertSame(Order::STATUS_CANCELLED, $old->fresh()->status);
        $this->assertSame(Order::STATUS_NEW, $fresh->fresh()->status);
        $this->assertSame(Order::STATUS_NEW, $cod->fresh()->status);
        $this->assertSame(Order::STATUS_NEW, $deferred->fresh()->status);
        $this->assertSame(2, (int) $variant->fresh()->quantity);
    }

    public function test_product_without_variants_counts_stock(): void
    {
        $product = $this->simpleProduct(2);

        $this->flushSession();
        $this->withSession($this->cart($product, [], 3))->post(route('checkout.store'), $this->payload());
        $this->assertSame(0, Order::query()->count());

        $first = $this->place($product);
        $this->assertSame(1, (int) $product->fresh()->stock_quantity);

        $this->place($product);
        $product->refresh();
        $this->assertSame(0, (int) $product->stock_quantity);
        $this->assertSame(Product::STOCK_OUT_OF_STOCK, $product->stockStatus());

        $first->status = Order::STATUS_CANCELLED;
        $first->save();
        $product->refresh();
        $this->assertSame(1, (int) $product->stock_quantity);
        $this->assertSame(Product::STOCK_IN_STOCK, $product->stockStatus());
    }

    public function test_product_without_stock_quantity_stays_untracked(): void
    {
        $product = $this->simpleProduct(null);

        $this->place($product, [], 5);

        $product->refresh();
        $this->assertNull($product->stock_quantity);
        $this->assertSame(Product::STOCK_IN_STOCK, $product->stockStatus());
    }

    public function test_forwarded_ip_is_trusted_only_from_internal_proxy(): void
    {
        Route::get('/__ip', fn () => request()->ip());

        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.7'])
            ->withHeaders(['X-Forwarded-For' => '203.0.113.9'])
            ->get('/__ip')
            ->assertSeeText('198.51.100.7');

        $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
            ->withHeaders(['X-Forwarded-For' => '203.0.113.9, 198.51.100.7'])
            ->get('/__ip')
            ->assertSeeText('198.51.100.7');
    }

    public function test_checkout_limit_cannot_be_bypassed_with_forwarded_header(): void
    {
        $statuses = [];
        for ($i = 0; $i < 12; $i++) {
            $statuses[] = $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.7'])
                ->withHeaders(['X-Forwarded-For' => '203.0.113.'.$i])
                ->post(route('checkout.store'), [])
                ->getStatusCode();
        }

        $this->assertContains(429, $statuses);
    }

    public function test_security_headers_are_sent(): void
    {
        $this->get('/up')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Content-Security-Policy', "frame-ancestors 'self'; base-uri 'self'; object-src 'none'; script-src 'self' 'unsafe-inline' 'unsafe-eval' https://unpkg.com https://maps.googleapis.com https://maps.gstatic.com https://www.googletagmanager.com https://www.google-analytics.com https://connect.facebook.net");
    }

    public function test_invalid_phone_is_rejected_and_valid_formats_pass(): void
    {
        [$product, , $size] = $this->sellable(9);

        foreach (['abc', '123', '<script>', '(((((((((('] as $phone) {
            $this->flushSession();
            $this->withSession($this->cart($product, [$size->id]))
                ->post(route('checkout.store'), $this->payload(['customer_phone' => $phone]))
                ->assertSessionHasErrors('customer_phone');
        }
        $this->assertSame(0, Order::query()->count());

        foreach (['380501112233', '+380501112233', '+38 (050) 111-22-33', '050 111 22 33'] as $phone) {
            $this->flushSession();
            $this->withSession($this->cart($product, [$size->id]))
                ->post(route('checkout.store'), $this->payload(['customer_phone' => $phone]))
                ->assertSessionHasNoErrors();
        }
        $this->assertSame(4, Order::query()->count());
    }

    public function test_zero_price_product_cannot_be_ordered(): void
    {
        [$product, , $size] = $this->sellable(5, 0);

        $this->withSession($this->cart($product, [$size->id]))
            ->post(route('checkout.store'), $this->payload())
            ->assertRedirect(route('cart.index'));

        $this->assertSame(0, Order::query()->count());
    }

    public function test_repeated_form_submit_returns_the_same_order(): void
    {
        [$product, $variant, $size] = $this->sellable(5);
        $payload = $this->payload(['checkout_token' => 'same-form-token']);

        $first = $this->withSession($this->cart($product, [$size->id]))->post(route('checkout.store'), $payload);
        $order = Order::query()->latest('id')->firstOrFail();
        $second = $this->withSession($this->cart($product, [$size->id]))->post(route('checkout.store'), $payload);

        $this->assertSame(1, Order::query()->count());
        $this->assertSame(4, (int) $variant->fresh()->quantity);
        $this->assertSame($first->headers->get('Location'), $second->headers->get('Location'));
        $this->assertStringContainsString($order->success_token, (string) $second->headers->get('Location'));

        $this->flushSession();
        $this->withSession($this->cart($product, [$size->id]))
            ->post(route('checkout.store'), $this->payload(['checkout_token' => 'other-form-token']));
        $this->assertSame(2, Order::query()->count());
    }

    public function test_failed_validation_does_not_burn_form_token(): void
    {
        [$product, , $size] = $this->sellable(5);
        $token = ['checkout_token' => 'retry-token'];

        $this->withSession($this->cart($product, [$size->id]))
            ->post(route('checkout.store'), $this->payload([...$token, 'customer_phone' => 'abc']))
            ->assertSessionHasErrors('customer_phone');
        $this->withSession($this->cart($product, [$size->id]))
            ->post(route('checkout.store'), $this->payload($token));

        $this->assertSame(1, Order::query()->count());
    }

    public function test_product_title_cannot_break_out_of_json_ld(): void
    {
        [$product] = $this->sellable(2, 100, 'X</script><script>alert(1)</script>');

        $this->get(route('catalog.show', $product->slug))
            ->assertDontSee('</script><script>alert(1)', false);
    }

    public function test_locale_switch_does_not_redirect_to_foreign_referer(): void
    {
        $this->withHeaders(['Referer' => 'https://evil.example/x'])
            ->get('/locale/uk')
            ->assertRedirect(route('home'));

        $this->get('/locale/uk?return='.urlencode('/\\evil.example'))->assertRedirect(route('home'));
        $this->get('/locale/uk?return='.urlencode('/catalog'))->assertRedirect('/catalog');
    }

    public function test_order_token_is_not_stored_in_analytics(): void
    {
        [$product, , $size] = $this->sellable(2);
        $order = $this->place($product, [$size->id]);

        $this->flushSession();
        $this->withCookie('zoogle_vid', bin2hex(random_bytes(16)))
            ->get(route('checkout.success', ['order' => $order->id, 'token' => $order->success_token]))
            ->assertOk();

        $this->assertSame(0, ShopAnalyticsEvent::query()->where('label', 'like', '%'.$order->success_token.'%')->count());
    }

    public function test_empty_photo_derivative_is_ignored_and_rebuilt(): void
    {
        Storage::fake('public');
        ShopMediaSetting::query()->delete();

        $image = imagecreatetruecolor(10, 10);
        ob_start();
        imagepng($image);
        Storage::disk('public')->put('products/1/photo.png', (string) ob_get_clean());
        imagedestroy($image);
        Storage::disk('public')->put('products/1/photo.card.webp', '');

        $url = ListingPhotoDerivative::cardUrlForPath('products/1/photo.png');
        $this->assertStringNotContainsString('photo.card.webp', $url);

        if (function_exists('imagewebp')) {
            ListingPhotoDerivative::ensureForStoredPath('products/1/photo.png');
            $this->assertGreaterThan(0, Storage::disk('public')->size('products/1/photo.card.webp'));
        }
    }

    public function test_google_login_uses_state_and_rejects_unverified_email(): void
    {
        config(['services.google.client_id' => 'id', 'services.google.client_secret' => 'secret', 'services.google.redirect' => 'http://localhost/auth/google/callback']);

        $location = (string) $this->get(route('login'))->headers->get('Location');
        if (str_contains($location, 'accounts.google.com')) {
            $this->assertStringContainsString('state=', $location);
            $this->assertNotEmpty(session('state'));
        }

        $user = (new SocialiteUser)->setRaw(['email_verified' => false])->map(['id' => 'g1', 'name' => 'N', 'email' => 'victim@example.test']);
        Socialite::shouldReceive('driver->redirectUrl->user')->andReturn($user);

        $this->get(route('auth.google.callback'))->assertRedirect(route('catalog.index'));
        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'victim@example.test']);
    }
}
