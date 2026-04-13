<?php

namespace Tests\Feature;

use App\Models\OptionGroup;
use App\Models\OptionValue;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\ListingOptionSelectionService;
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
}
