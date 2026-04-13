<?php

namespace Tests\Feature;

use App\Models\Bundle;
use App\Models\BundleItem;
use App\Models\OptionGroup;
use App\Models\OptionValue;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CartDrawerTest extends TestCase
{
    use RefreshDatabase;

    public function test_cart_store_fragment_returns_drawer_payload_with_option_badges_and_swatches(): void
    {
        [$product, $colorValue, $sizeValue] = $this->makeProductWithDisplayOptions();

        $response = $this->withHeaders($this->fragmentHeaders())->post(route('cart.store'), [
            'product_id' => $product->id,
            'option_value_ids' => json_encode([$colorValue->id, $sizeValue->id], JSON_THROW_ON_ERROR),
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('summary.lines_count', 1)
            ->assertJsonPath('summary.items_count', 1)
            ->assertJsonPath('statusType', 'success');

        $html = (string) $response->json('html');

        $this->assertStringContainsString('cart-drawer__option-swatch', $html);
        $this->assertStringContainsString('Червоний', $html);
        $this->assertStringContainsString('Розмір: XL', $html);
        $this->assertArrayHasKey(
            $this->makeCartKey((int) $product->id, [(int) $colorValue->id, (int) $sizeValue->id]),
            session('cart', [])
        );
    }

    public function test_drawer_prefers_primary_product_photo_over_variant_photo(): void
    {
        [$product, $colorValue, $sizeValue] = $this->makeProductWithDisplayOptions();

        $product->forceFill([
            'photos' => ['products/main-photo.jpg'],
        ])->save();

        $product->variants()->create([
            'price' => 1000,
            'quantity' => 1,
            'is_available' => true,
            'is_low_stock' => false,
            'is_visible' => true,
            'allows_preorder' => false,
            'is_sold' => false,
            'sort_order' => 1,
            'photos' => ['product-variants/secondary-photo.jpg'],
            'options' => [
                ['option_group_id' => $colorValue->option_group_id, 'option_value_id' => $colorValue->id],
            ],
        ]);

        $response = $this->withHeaders($this->fragmentHeaders())->post(route('cart.store'), [
            'product_id' => $product->id,
            'option_value_ids' => json_encode([$colorValue->id, $sizeValue->id], JSON_THROW_ON_ERROR),
        ]);

        $response->assertOk();

        $html = (string) $response->json('html');

        $this->assertStringContainsString('products/main-photo.jpg', $html);
        $this->assertStringNotContainsString('product-variants/secondary-photo.jpg', $html);
    }

    public function test_cart_update_fragment_updates_quantity_and_total(): void
    {
        [$product, $colorValue, $sizeValue] = $this->makeProductWithDisplayOptions();
        $cartKey = $this->makeCartKey((int) $product->id, [(int) $colorValue->id, (int) $sizeValue->id]);

        $response = $this
            ->withSession([
                'cart' => [
                    $cartKey => [
                        'product_id' => (int) $product->id,
                        'qty' => 1,
                        'option_value_ids' => [(int) $colorValue->id, (int) $sizeValue->id],
                    ],
                ],
            ])
            ->withHeaders($this->fragmentHeaders())
            ->patch(route('cart.update', $cartKey), [
                'qty' => 3,
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('summary.lines_count', 1)
            ->assertJsonPath('summary.items_count', 3);

        $this->assertSame(3000.0, (float) $response->json('summary.total'));

        $this->assertSame(3, session('cart', [])[$cartKey]['qty'] ?? null);
        $this->assertStringContainsString('3 000,00', (string) $response->json('html'));
    }

    public function test_cart_destroy_fragment_returns_empty_drawer_state(): void
    {
        [$product, $colorValue, $sizeValue] = $this->makeProductWithDisplayOptions();
        $cartKey = $this->makeCartKey((int) $product->id, [(int) $colorValue->id, (int) $sizeValue->id]);

        $response = $this
            ->withSession([
                'cart' => [
                    $cartKey => [
                        'product_id' => (int) $product->id,
                        'qty' => 1,
                        'option_value_ids' => [(int) $colorValue->id, (int) $sizeValue->id],
                    ],
                ],
            ])
            ->withHeaders($this->fragmentHeaders())
            ->delete(route('cart.destroy', $cartKey));

        $response
            ->assertOk()
            ->assertJsonPath('summary.lines_count', 0)
            ->assertJsonPath('summary.items_count', 0)
            ->assertJsonPath('summary.is_empty', true);

        $this->assertSame([], session('cart', []));
        $this->assertStringContainsString('Кошик порожній', (string) $response->json('html'));
    }

    public function test_catalog_page_receives_shared_drawer_payload_in_layout(): void
    {
        [$product, $colorValue, $sizeValue] = $this->makeProductWithDisplayOptions();
        $cartKey = $this->makeCartKey((int) $product->id, [(int) $colorValue->id, (int) $sizeValue->id]);

        $response = $this
            ->withSession([
                'cart' => [
                    $cartKey => [
                        'product_id' => (int) $product->id,
                        'qty' => 1,
                        'option_value_ids' => [(int) $colorValue->id, (int) $sizeValue->id],
                    ],
                ],
            ])
            ->get(route('catalog.index'));

        $response
            ->assertOk()
            ->assertSee('data-cart-drawer-content', false)
            ->assertSee('cart-drawer__option-swatch', false)
            ->assertSee('Розмір: XL');
    }

    public function test_bundle_fragment_adds_single_bundle_line_without_option_tokens(): void
    {
        [$product] = $this->makeProductWithDisplayOptions();

        $bundle = Bundle::query()->create([
            'title' => 'Комплект без дочірніх опцій',
            'slug' => 'bundle-without-child-options',
            'is_visible' => true,
            'is_active' => true,
        ]);

        BundleItem::query()->create([
            'bundle_id' => $bundle->id,
            'product_id' => $product->id,
            'qty' => 1,
            'sort_order' => 1,
        ]);

        $response = $this->withHeaders($this->fragmentHeaders())
            ->post(route('bundles.add-to-cart', $bundle));

        $response
            ->assertOk()
            ->assertJsonPath('summary.lines_count', 1)
            ->assertJsonPath('summary.items_count', 1);

        $html = (string) $response->json('html');

        $this->assertStringNotContainsString('cart-drawer__option-badge', $html);
        $this->assertStringNotContainsString('cart-drawer__option-swatch', $html);
        $this->assertStringContainsString('Комплект', $html);
        $this->assertStringContainsString($bundle->title, $html);
        $this->assertStringContainsString($product->title, $html);
        $this->assertArrayHasKey('bundle:'.$bundle->id, session('cart', []));
    }

    /**
     * @return array{0: Product, 1: OptionValue, 2: OptionValue}
     */
    private function makeProductWithDisplayOptions(): array
    {
        $root = $this->catalogRoot();

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
            'selection_mode' => 'single',
            'value_type' => 'text',
            'is_active' => true,
        ]);

        $colorValue = OptionValue::query()->create([
            'option_group_id' => $colorGroup->id,
            'name' => 'Червоний',
            'slug' => 'red',
            'color_hex' => '#ff0000',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $sizeValue = OptionValue::query()->create([
            'option_group_id' => $sizeGroup->id,
            'name' => 'XL',
            'slug' => 'xl',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $product = Product::query()->create([
            'title' => 'Товар для drawer',
            'slug' => 'drawer-product',
            'product_type' => OptionGroup::CATALOG_PRODUCT_TYPE,
            'category_parent_value_id' => $root->id,
            'category_value_id' => null,
            'price' => 1000,
            'is_available' => true,
            'short_description' => 'Тестовий товар',
            'published_at' => now(),
            'variant_options' => [
                [
                    'option_group_id' => $root->option_group_id,
                    'option_value_ids' => [$root->id],
                ],
                [
                    'option_group_id' => $colorGroup->id,
                    'option_value_ids' => [$colorValue->id],
                ],
                [
                    'option_group_id' => $sizeGroup->id,
                    'option_value_ids' => [$sizeValue->id],
                ],
            ],
        ]);

        return [$product, $colorValue, $sizeValue];
    }

    private function catalogRoot(): OptionValue
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

        return OptionValue::query()->firstOrCreate(
            [
                'option_group_id' => $categoryGroup->id,
                'slug' => 'catalog-root',
            ],
            [
                'name' => 'Каталог',
                'sort_order' => 1,
                'is_active' => true,
            ]
        );
    }

    /**
     * @param  list<int>  $optionValueIds
     */
    private function makeCartKey(int $productId, array $optionValueIds): string
    {
        sort($optionValueIds);

        return $productId.':'.($optionValueIds === [] ? 'none' : implode(',', $optionValueIds));
    }

    /**
     * @return array<string, string>
     */
    private function fragmentHeaders(): array
    {
        return [
            'Accept' => 'application/json',
            'X-Cart-Fragment' => '1',
            'X-Requested-With' => 'XMLHttpRequest',
        ];
    }
}
