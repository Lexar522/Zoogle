<?php

namespace Tests\Feature;

use App\Models\OptionGroup;
use App\Models\OptionValue;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\ListingOptionSelectionService;
use App\Support\ListingDefaultOptionPrice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShopLogicRegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_option_selection_service_splits_multiple_matching_group_into_separate_lines(): void
    {
        [$product, , $sizeS, $sizeM, $giftWrap] = $this->makeProductWithMatchingAndAddonOptions();

        $service = app(ListingOptionSelectionService::class);
        $lines = $service->splitIntoCartLineOptionSets($product, [$sizeS->id, $sizeM->id, $giftWrap->id]);

        $this->assertSame([
            [(int) $sizeS->id, (int) $giftWrap->id],
            [(int) $sizeM->id, (int) $giftWrap->id],
        ], $lines);
    }

    public function test_option_selection_service_splits_multiple_non_matching_volume_addons_into_separate_lines(): void
    {
        // Регресія клієнта: PDP рахував 2×(base+addon), кошик — 1×base+2×addon (616 vs 462).
        [$product, $vol1, $vol3] = $this->makeProductWithMultiVolumeAddonOnly();

        $service = app(ListingOptionSelectionService::class);
        $lines = $service->splitIntoCartLineOptionSets($product, [$vol1->id, $vol3->id]);

        $this->assertSame([
            [(int) $vol1->id],
            [(int) $vol3->id],
        ], $lines);
    }

    public function test_exclusivity_family_size_groups_require_only_one_pick(): void
    {
        [$product, $smallPack, $largePack] = $this->makeProductWithExclusiveSizePackageGroups();

        $service = app(ListingOptionSelectionService::class);

        $this->assertNotSame([], $service->missingRequiredOptionGroupNames($product, []));
        $this->assertSame([], $service->missingRequiredOptionGroupNames($product, [$smallPack->id]));
        $this->assertSame([], $service->missingRequiredOptionGroupNames($product, [$largePack->id]));

        $sanitized = $service->sanitizeSelectionForProduct($product, [$smallPack->id, $largePack->id]);
        $this->assertSame([(int) $largePack->id], $sanitized);
    }

    public function test_catalog_default_price_counts_only_one_exclusivity_size_family(): void
    {
        [$product] = $this->makeProductWithExclusiveSizePackageGroups();

        $this->assertSame(530.0, ListingDefaultOptionPrice::addonTotalForProduct($product));
    }

    public function test_cart_accepts_one_pack_from_exclusivity_size_family(): void
    {
        [$product, , $largePack] = $this->makeProductWithExclusiveSizePackageGroups();

        $response = $this->post(route('cart.store'), [
            'product_id' => $product->id,
            'option_value_ids' => json_encode([$largePack->id], JSON_THROW_ON_ERROR),
        ]);

        $response->assertRedirect();
        $this->assertNotSame([], session('cart', []));
    }

    public function test_cart_rejects_option_combination_that_does_not_resolve_to_a_variant(): void
    {
        [$product, , , $sizeM, $giftWrap] = $this->makeProductWithMatchingAndAddonOptions();

        $response = $this->post(route('cart.store'), [
            'product_id' => $product->id,
            'option_value_ids' => json_encode([$sizeM->id, $giftWrap->id], JSON_THROW_ON_ERROR),
        ]);

        $response
            ->assertRedirect()
            ->assertSessionHas('error', 'Обрана комбінація опцій недоступна для цього товару.');
    }

    public function test_cart_rejects_product_with_options_when_no_option_selected(): void
    {
        [$product] = $this->makeProductWithMatchingAndAddonOptions();

        $response = $this->post(route('cart.store'), [
            'product_id' => $product->id,
            'option_value_ids' => json_encode([], JSON_THROW_ON_ERROR),
        ]);

        $response
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSame([], session('cart', []));
    }

    public function test_cart_accepts_valid_variant_with_non_matching_addon_option(): void
    {
        [$product, , $sizeS, , $giftWrap] = $this->makeProductWithMatchingAndAddonOptions();

        $response = $this->post(route('cart.store'), [
            'product_id' => $product->id,
            'option_value_ids' => json_encode([$sizeS->id, $giftWrap->id], JSON_THROW_ON_ERROR),
        ]);

        $response
            ->assertRedirect(route('cart.index'))
            ->assertSessionHas('success', 'Позицію додано в кошик.');

        $this->assertArrayHasKey(
            $product->id.':'.$sizeS->id.','.$giftWrap->id,
            session('cart', [])
        );
    }

    public function test_cart_accepts_option_from_group_exclusive_to_single_variant(): void
    {
        [$product, $black, $sizeS] = $this->makeProductWithExclusiveAddonGroup();

        $response = $this->post(route('cart.store'), [
            'product_id' => $product->id,
            'option_value_ids' => json_encode([$black->id, $sizeS->id], JSON_THROW_ON_ERROR),
        ]);

        $response
            ->assertRedirect(route('cart.index'))
            ->assertSessionHas('success', 'Позицію додано в кошик.');

        $this->assertArrayHasKey(
            $product->id.':'.$black->id.','.$sizeS->id,
            session('cart', [])
        );
    }

    public function test_cart_rejects_unsellable_variant(): void
    {
        [$product, $variant, $sizeS, , $giftWrap] = $this->makeProductWithMatchingAndAddonOptions();
        $variant->forceFill([
            'is_available' => false,
            'is_low_stock' => false,
            'allows_preorder' => false,
            'quantity' => 0,
        ])->save();

        $response = $this->post(route('cart.store'), [
            'product_id' => $product->id,
            'option_value_ids' => json_encode([$sizeS->id, $giftWrap->id], JSON_THROW_ON_ERROR),
        ]);

        $response
            ->assertRedirect()
            ->assertSessionHas('error');
        $this->assertSame([], session('cart', []));
    }

    public function test_cart_rejects_qty_above_variant_stock(): void
    {
        [$product, $variant, $sizeS, , $giftWrap] = $this->makeProductWithMatchingAndAddonOptions();
        $variant->forceFill(['quantity' => 1, 'is_available' => true])->save();

        $this->post(route('cart.store'), [
            'product_id' => $product->id,
            'option_value_ids' => json_encode([$sizeS->id, $giftWrap->id], JSON_THROW_ON_ERROR),
        ])->assertRedirect(route('cart.index'));

        $cart = session('cart', []);
        $this->assertNotSame([], $cart);
        $cartKey = array_key_first($cart);

        $this->patch(route('cart.update', $cartKey), ['qty' => 5])
            ->assertRedirect()
            ->assertSessionHas('error');
        $this->assertSame(1, (int) session('cart')[$cartKey]['qty']);
    }

    public function test_checkout_success_requires_matching_token(): void
    {
        $order = Order::query()->create([
            'customer_name' => 'Test User',
            'customer_phone' => '380000000000',
            'total' => 1000,
            'status' => Order::STATUS_NEW,
            'payment_status' => 'pending',
            'delivery_type' => Order::DELIVERY_PICKUP,
        ]);

        $this->get(route('checkout.success', ['order' => $order->id]))
            ->assertNotFound();

        $this->get(route('checkout.success', ['order' => $order->id, 'token' => $order->success_token]))
            ->assertOk();

        $this->post(route('checkout.success', ['order' => $order->id, 'token' => $order->success_token]))
            ->assertOk();
    }

    public function test_checkout_success_applies_approved_wayforpay_return_payload(): void
    {
        config([
            'services.wayforpay.merchant_account' => 'test_merchant',
            'services.wayforpay.secret_key' => 'secret',
            'services.wayforpay.merchant_domain' => 'example.test',
        ]);

        $order = Order::query()->create([
            'customer_name' => 'Test User',
            'customer_phone' => '380000000000',
            'total' => 1,
            'status' => Order::STATUS_NEW,
            'payment_status' => 'pending',
            'payment_provider' => 'wayforpay',
            'delivery_type' => Order::DELIVERY_PICKUP,
        ]);

        $orderReference = $order->id.'-pay-01kqmk8jsj2s3vxn3qrxebva05';
        $payload = [
            'merchantAccount' => 'test_merchant',
            'orderReference' => $orderReference,
            'amount' => '1.00',
            'currency' => 'UAH',
            'authCode' => '123456',
            'cardPan' => '41****1111',
            'transactionStatus' => 'Approved',
            'reasonCode' => '1100',
        ];
        $payload['merchantSignature'] = hash_hmac('md5', implode(';', [
            $payload['merchantAccount'],
            $payload['orderReference'],
            $payload['amount'],
            $payload['currency'],
            $payload['authCode'],
            $payload['cardPan'],
            $payload['transactionStatus'],
            $payload['reasonCode'],
        ]), 'secret');

        $this->post(route('checkout.success', ['order' => $order->id, 'token' => $order->success_token]), $payload)
            ->assertOk()
            ->assertSee('Оплачено');

        $order->refresh();

        $this->assertSame(Order::STATUS_PAID, $order->status);
        $this->assertSame('paid', $order->payment_status);
        $this->assertNotNull($order->paid_at);
    }

    /**
     * @return array{0: Product, 1: ProductVariant, 2: OptionValue, 3: OptionValue, 4: OptionValue}
     */
    private function makeProductWithMatchingAndAddonOptions(): array
    {
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

        $catalogRoot = OptionValue::query()->firstOrCreate(
            [
                'option_group_id' => $categoryGroup->id,
                'slug' => 'animal',
            ],
            [
                'name' => 'Товар',
                'sort_order' => 1,
                'is_active' => true,
            ]
        );

        $sizeGroup = OptionGroup::query()->create([
            'product_type' => OptionGroup::CATALOG_PRODUCT_TYPE,
            'name' => 'Розмір',
            'slug' => 'size',
            'selection_mode' => 'multiple',
            'value_type' => 'text',
            'is_active' => true,
        ]);

        $addonGroup = OptionGroup::query()->create([
            'product_type' => OptionGroup::CATALOG_PRODUCT_TYPE,
            'name' => 'Додатково',
            'slug' => 'addon',
            'selection_mode' => 'multiple',
            'value_type' => 'text',
            'is_active' => true,
        ]);

        $sizeS = OptionValue::query()->create([
            'option_group_id' => $sizeGroup->id,
            'name' => 'S',
            'slug' => 's',
            'price' => 0,
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $sizeM = OptionValue::query()->create([
            'option_group_id' => $sizeGroup->id,
            'name' => 'M',
            'slug' => 'm',
            'price' => 400,
            'sort_order' => 2,
            'is_active' => true,
        ]);

        $giftWrap = OptionValue::query()->create([
            'option_group_id' => $addonGroup->id,
            'name' => 'Упаковка',
            'slug' => 'gift-wrap',
            'price' => 50,
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $product = Product::query()->create([
            'title' => 'Test product',
            'slug' => 'test-product',
            'product_type' => OptionGroup::CATALOG_PRODUCT_TYPE,
            'category_parent_value_id' => $catalogRoot->id,
            'category_value_id' => null,
            'description' => 'Description',
            'short_description' => 'Short',
            'is_available' => true,
            'variant_options' => [
                [
                    'option_group_id' => $categoryGroup->id,
                    'option_value_ids' => [$catalogRoot->id],
                ],
                [
                    'option_group_id' => $sizeGroup->id,
                    'option_value_ids' => [$sizeS->id, $sizeM->id],
                ],
                [
                    'option_group_id' => $addonGroup->id,
                    'option_value_ids' => [$giftWrap->id],
                ],
            ],
        ]);

        $variant = ProductVariant::query()->create([
            'product_id' => $product->id,
            'price' => 1000,
            'quantity' => 1,
            'is_available' => true,
            'is_low_stock' => false,
            'is_visible' => true,
            'allows_preorder' => false,
            'is_sold' => false,
            'sort_order' => 1,
            'options' => [
                ['option_group_id' => $sizeGroup->id, 'option_value_id' => $sizeS->id],
            ],
        ]);

        $product->load('variants');

        return [$product, $variant, $sizeS, $sizeM, $giftWrap];
    }

    /**
     * @return array{0: Product, 1: OptionValue, 2: OptionValue}
     */
    private function makeProductWithExclusiveAddonGroup(): array
    {
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

        $catalogRoot = OptionValue::query()->firstOrCreate(
            [
                'option_group_id' => $categoryGroup->id,
                'slug' => 'animal',
            ],
            [
                'name' => 'Товар',
                'sort_order' => 1,
                'is_active' => true,
            ]
        );

        $colorGroup = OptionGroup::query()->create([
            'product_type' => OptionGroup::CATALOG_PRODUCT_TYPE,
            'name' => 'Колір',
            'slug' => 'color',
            'selection_mode' => 'single',
            'value_type' => 'color',
            'is_active' => true,
        ]);

        $sizeGroup = OptionGroup::query()->create([
            'product_type' => OptionGroup::CATALOG_PRODUCT_TYPE,
            'name' => 'Розмір',
            'slug' => 'size',
            'selection_mode' => 'multiple',
            'value_type' => 'text',
            'is_active' => true,
        ]);

        $black = OptionValue::query()->create([
            'option_group_id' => $colorGroup->id,
            'name' => 'Чорний',
            'slug' => 'black',
            'color_hex' => '#000000',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $white = OptionValue::query()->create([
            'option_group_id' => $colorGroup->id,
            'name' => 'Білий',
            'slug' => 'white',
            'color_hex' => '#ffffff',
            'sort_order' => 2,
            'is_active' => true,
        ]);

        $sizeS = OptionValue::query()->create([
            'option_group_id' => $sizeGroup->id,
            'name' => 'S',
            'slug' => 'size-s',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $product = Product::query()->create([
            'title' => 'Exclusive addon product',
            'slug' => 'exclusive-addon-product',
            'product_type' => OptionGroup::CATALOG_PRODUCT_TYPE,
            'category_parent_value_id' => $catalogRoot->id,
            'category_value_id' => null,
            'description' => 'Description',
            'short_description' => 'Short',
            'is_available' => true,
            'variant_options' => [
                [
                    'option_group_id' => $categoryGroup->id,
                    'option_value_ids' => [$catalogRoot->id],
                ],
                [
                    'option_group_id' => $colorGroup->id,
                    'option_value_ids' => [$black->id, $white->id],
                ],
                [
                    'option_group_id' => $sizeGroup->id,
                    'option_value_ids' => [$sizeS->id],
                ],
            ],
        ]);

        ProductVariant::query()->create([
            'product_id' => $product->id,
            'price' => 2000,
            'quantity' => 1,
            'is_available' => true,
            'is_low_stock' => false,
            'is_visible' => true,
            'allows_preorder' => false,
            'is_sold' => false,
            'sort_order' => 1,
            'options' => [
                ['option_group_id' => $colorGroup->id, 'option_value_id' => $black->id],
                ['option_group_id' => $sizeGroup->id, 'option_value_id' => $sizeS->id],
            ],
        ]);

        ProductVariant::query()->create([
            'product_id' => $product->id,
            'price' => 1000,
            'quantity' => 1,
            'is_available' => true,
            'is_low_stock' => false,
            'is_visible' => true,
            'allows_preorder' => false,
            'is_sold' => false,
            'sort_order' => 2,
            'options' => [
                ['option_group_id' => $colorGroup->id, 'option_value_id' => $white->id],
            ],
        ]);

        $product->load('variants');

        return [$product, $black, $sizeS];
    }

    /**
     * Дві single-групи «Розмір: …» — взаємовиключна сімʼя пакетів (як дубія 1–1,5 / 3–4 см).
     *
     * @return array{0: Product, 1: OptionValue, 2: OptionValue}
     */
    private function makeProductWithExclusiveSizePackageGroups(): array
    {
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

        $catalogRoot = OptionValue::query()->firstOrCreate(
            [
                'option_group_id' => $categoryGroup->id,
                'slug' => 'animal',
            ],
            [
                'name' => 'Товар',
                'sort_order' => 1,
                'is_active' => true,
            ]
        );

        $smallGroup = OptionGroup::query()->create([
            'product_type' => OptionGroup::CATALOG_PRODUCT_TYPE,
            'name' => 'Розмір: 1-1,5 см',
            'slug' => 'size-small-'.uniqid(),
            'selection_mode' => 'single',
            'value_type' => 'text',
            'is_active' => true,
        ]);

        $largeGroup = OptionGroup::query()->create([
            'product_type' => OptionGroup::CATALOG_PRODUCT_TYPE,
            'name' => 'Розмір: 3-4 см',
            'slug' => 'size-large-'.uniqid(),
            'selection_mode' => 'single',
            'value_type' => 'text',
            'is_active' => true,
        ]);

        $smallPack = OptionValue::query()->create([
            'option_group_id' => $smallGroup->id,
            'name' => '50 шт + контейнер',
            'slug' => 'small-50-'.uniqid(),
            'price' => 530,
            'sort_order' => 1,
            'is_active' => true,
        ]);

        OptionValue::query()->create([
            'option_group_id' => $smallGroup->id,
            'name' => '100 шт + контейнер',
            'slug' => 'small-100-'.uniqid(),
            'price' => 1010,
            'sort_order' => 2,
            'is_active' => true,
        ]);

        $largePack = OptionValue::query()->create([
            'option_group_id' => $largeGroup->id,
            'name' => '15 шт + контейнер',
            'slug' => 'large-15-'.uniqid(),
            'price' => 405,
            'sort_order' => 1,
            'is_active' => true,
        ]);

        OptionValue::query()->create([
            'option_group_id' => $largeGroup->id,
            'name' => '30 шт + контейнер',
            'slug' => 'large-30-'.uniqid(),
            'price' => 770,
            'sort_order' => 2,
            'is_active' => true,
        ]);

        $product = Product::query()->create([
            'title' => 'Дубія пакети',
            'slug' => 'dubia-packs-'.uniqid(),
            'sku' => 'DUBIA-'.uniqid(),
            'price' => 0,
            'is_available' => true,
            'is_visible' => true,
            'stock_status' => 'in_stock',
            'category_parent_value_id' => $catalogRoot->id,
            'category_value_id' => $catalogRoot->id,
            'variant_options' => [
                [
                    'option_group_id' => $smallGroup->id,
                    'option_value_ids' => OptionValue::query()->where('option_group_id', $smallGroup->id)->pluck('id')->all(),
                    'default_option_value_id' => $smallPack->id,
                    'value_prices' => [
                        (string) $smallPack->id => 530,
                    ],
                ],
                [
                    'option_group_id' => $largeGroup->id,
                    'option_value_ids' => OptionValue::query()->where('option_group_id', $largeGroup->id)->pluck('id')->all(),
                    'default_option_value_id' => $largePack->id,
                    'value_prices' => [
                        (string) $largePack->id => 405,
                    ],
                ],
            ],
        ]);

        return [$product, $smallPack, $largePack];
    }

    /**
     * Товар лише з multi-select «Об'єм» як addon (не впливає на SKU) — як у кейсі 616 vs 462.
     *
     * @return array{0: Product, 1: OptionValue, 2: OptionValue}
     */
    private function makeProductWithMultiVolumeAddonOnly(): array
    {
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

        $catalogRoot = OptionValue::query()->firstOrCreate(
            [
                'option_group_id' => $categoryGroup->id,
                'slug' => 'animal',
            ],
            [
                'name' => 'Товар',
                'sort_order' => 1,
                'is_active' => true,
            ]
        );

        $volumeGroup = OptionGroup::query()->create([
            'product_type' => OptionGroup::CATALOG_PRODUCT_TYPE,
            'name' => 'Об\'єм',
            'slug' => 'volume-addon-'.uniqid(),
            'selection_mode' => 'multiple',
            'value_type' => 'text',
            'is_active' => true,
        ]);

        $vol1 = OptionValue::query()->create([
            'option_group_id' => $volumeGroup->id,
            'name' => '1л',
            'slug' => '1l-'.uniqid(),
            'price' => 154,
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $vol3 = OptionValue::query()->create([
            'option_group_id' => $volumeGroup->id,
            'name' => '3л',
            'slug' => '3l-'.uniqid(),
            'price' => 154,
            'sort_order' => 2,
            'is_active' => true,
        ]);

        $product = Product::query()->create([
            'title' => 'Volume addon product',
            'slug' => 'volume-addon-'.uniqid(),
            'product_type' => OptionGroup::CATALOG_PRODUCT_TYPE,
            'category_parent_value_id' => $catalogRoot->id,
            'category_value_id' => null,
            'price' => 154,
            'description' => 'Description',
            'short_description' => 'Short',
            'is_available' => true,
            'variant_options' => [
                [
                    'option_group_id' => $categoryGroup->id,
                    'option_value_ids' => [$catalogRoot->id],
                ],
                [
                    'option_group_id' => $volumeGroup->id,
                    'option_value_ids' => [$vol1->id, $vol3->id],
                    'value_prices' => [
                        (string) $vol1->id => 154,
                        (string) $vol3->id => 154,
                    ],
                ],
            ],
        ]);

        return [$product, $vol1, $vol3];
    }
}
