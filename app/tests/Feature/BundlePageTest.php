<?php

namespace Tests\Feature;

use App\Models\Bundle;
use App\Models\BundleItem;
use App\Models\OptionGroup;
use App\Models\OptionValue;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\CartDrawerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BundlePageTest extends TestCase
{
    use RefreshDatabase;

    public function test_bundle_show_page_lists_products_links_and_prices(): void
    {
        $root = $this->catalogRoot();

        $product = Product::query()->create([
            'title' => 'Сухий корм',
            'slug' => 'dry-food',
            'product_type' => OptionGroup::CATALOG_PRODUCT_TYPE,
            'category_parent_value_id' => $root->id,
            'category_value_id' => null,
            'price' => 1500,
            'is_available' => true,
            'short_description' => 'Товар для комплекту',
            'variant_options' => [
                [
                    'option_group_id' => $root->option_group_id,
                    'option_value_ids' => [$root->id],
                ],
            ],
        ]);

        $bundle = Bundle::query()->create([
            'title' => 'Стартовий набір',
            'slug' => 'starter-bundle',
            'short_description' => 'Готовий комплект',
            'is_visible' => true,
            'is_active' => true,
        ]);

        BundleItem::query()->create([
            'bundle_id' => $bundle->id,
            'product_id' => $product->id,
            'qty' => 2,
            'sort_order' => 1,
        ]);

        $response = $this->get(route('bundles.show', $bundle->slug));

        $response
            ->assertOk()
            ->assertSee('Стартовий набір')
            ->assertSee('Сухий корм')
            ->assertSee(route('catalog.show', $product->slug))
            ->assertSee('1 500,00')
            ->assertSee('3 000,00');
    }

    public function test_adding_bundle_to_cart_creates_single_bundle_line(): void
    {
        [$product] = $this->makeProductWithOptions();

        $bundle = Bundle::query()->create([
            'title' => 'Набір без опцій',
            'slug' => 'bundle-no-options',
            'is_visible' => true,
            'is_active' => true,
        ]);

        BundleItem::query()->create([
            'bundle_id' => $bundle->id,
            'product_id' => $product->id,
            'qty' => 1,
            'sort_order' => 1,
        ]);

        $response = $this->post(route('bundles.add-to-cart', $bundle));

        $response->assertRedirect(route('cart.index'));

        $cart = session('cart', []);
        $key = 'bundle:'.$bundle->id;

        $this->assertArrayHasKey($key, $cart);
        $this->assertSame('bundle', $cart[$key]['line_kind']);
        $this->assertSame($bundle->id, $cart[$key]['bundle_id']);
        $this->assertSame([], $cart[$key]['option_value_ids']);
        $this->assertSame(1, $cart[$key]['qty']);

        $resolved = app(CartDrawerService::class)->fromCart($cart, true)['items'];
        $this->assertCount(1, $resolved);

        $line = $resolved->first();
        $this->assertSame('bundle', $line['line_kind']);
        $this->assertSame($bundle->id, $line['bundle']->id);
        $this->assertSame($bundle->title, $line['title']);
        $this->assertSame([], $line['option_value_ids']);
        $this->assertCount(1, $line['bundle_items']);
        $this->assertSame($product->id, $line['bundle_items'][0]['product_id']);
    }

    public function test_checkout_stores_bundle_as_single_order_item_with_snapshot(): void
    {
        [$product] = $this->makeProductWithOptions();

        $bundle = Bundle::query()->create([
            'title' => 'Bundle order snapshot',
            'slug' => 'bundle-order-snapshot',
            'is_visible' => true,
            'is_active' => true,
        ]);

        BundleItem::query()->create([
            'bundle_id' => $bundle->id,
            'product_id' => $product->id,
            'qty' => 2,
            'sort_order' => 1,
        ]);

        $response = $this
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
            ->post(route('checkout.store'), [
                'customer_name' => 'Тестовий покупець',
                'customer_phone' => '380000000000',
                'customer_email' => 'bundle@example.test',
                'payment_method' => 'cod',
                'delivery_type' => Order::DELIVERY_PICKUP,
            ]);

        $order = Order::query()->first();
        $this->assertNotNull($order);
        $response->assertRedirect(route('checkout.success', ['order' => $order->id, 'token' => $order->success_token]));

        $this->assertDatabaseHas('order_items', [
            'order_id' => $order->id,
            'product_id' => null,
            'bundle_id' => $bundle->id,
            'title_snapshot' => $bundle->title,
            'qty' => 1,
        ]);

        $orderItem = OrderItem::query()->where('order_id', $order->id)->first();
        $this->assertNotNull($orderItem);
        $this->assertSame($bundle->id, $orderItem->bundle_id);
        $this->assertNull($orderItem->product_id);
        $this->assertIsArray($orderItem->bundle_snapshot);
        $this->assertSame($bundle->title, $orderItem->bundle_snapshot['title'] ?? null);
        $this->assertSame(1, $orderItem->bundle_snapshot['line_qty'] ?? null);
        $this->assertCount(1, $orderItem->bundle_snapshot['items'] ?? []);
        $this->assertSame($product->id, $orderItem->bundle_snapshot['items'][0]['product_id'] ?? null);
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
     * @return array{0: Product, 1: ProductVariant}
     */
    private function makeProductWithOptions(): array
    {
        $root = $this->catalogRoot();

        $sizeGroup = OptionGroup::query()->create([
            'product_type' => OptionGroup::CATALOG_PRODUCT_TYPE,
            'name' => 'Розмір',
            'slug' => 'size',
            'selection_mode' => 'single',
            'value_type' => 'text',
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
            'title' => 'Товар з опціями',
            'slug' => 'product-with-options',
            'product_type' => OptionGroup::CATALOG_PRODUCT_TYPE,
            'category_parent_value_id' => $root->id,
            'category_value_id' => null,
            'price' => 1800,
            'is_available' => true,
            'variant_options' => [
                [
                    'option_group_id' => $root->option_group_id,
                    'option_value_ids' => [$root->id],
                ],
                [
                    'option_group_id' => $sizeGroup->id,
                    'option_value_ids' => [$sizeS->id],
                ],
            ],
        ]);

        $variant = ProductVariant::query()->create([
            'product_id' => $product->id,
            'price' => 1800,
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

        return [$product, $variant];
    }
}
