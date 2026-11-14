<?php

namespace Tests\Feature;

use App\Models\Bundle;
use App\Models\BundleItem;
use App\Models\OptionGroup;
use App\Models\OptionValue;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\AdminOrderItemEditor;
use App\Services\CartLineResolver;
use App\Services\Payments\ApplyApprovedOrderPayment;
use App\Support\ShopCart;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShopAuditFixesTest extends TestCase
{
    use RefreshDatabase;

    public function test_checkout_decrements_bundle_component_quantity(): void
    {
        [$product, $variant, $size] = $this->makeSellableProduct(quantity: 5);

        $bundle = Bundle::query()->create([
            'title' => 'Audit bundle',
            'slug' => 'audit-bundle',
            'is_visible' => true,
            'is_active' => true,
        ]);
        BundleItem::query()->create([
            'bundle_id' => $bundle->id,
            'product_id' => $product->id,
            'qty' => 2,
            'sort_order' => 1,
            'option_value_ids' => [$size->id],
        ]);

        $this->withSession([
            'cart' => [
                'bundle:'.$bundle->id => [
                    'line_kind' => 'bundle',
                    'bundle_id' => $bundle->id,
                    'qty' => 1,
                    'option_value_ids' => [],
                ],
            ],
        ])->post(route('checkout.store'), $this->checkoutPayload())
            ->assertRedirect();

        $this->assertSame(3, (int) $variant->fresh()->quantity);
        $this->assertTrue((bool) Order::query()->first()?->inventory_committed);
    }

    public function test_cancelling_order_restores_stock(): void
    {
        [$product, $variant, $size] = $this->makeSellableProduct(quantity: 2);

        $this->withSession([
            'cart' => [
                'p1' => [
                    'line_kind' => 'product',
                    'product_id' => $product->id,
                    'qty' => 1,
                    'option_value_ids' => [$size->id],
                ],
            ],
        ])->post(route('checkout.store'), $this->checkoutPayload())
            ->assertRedirect();

        $this->assertSame(1, (int) $variant->fresh()->quantity);

        $order = Order::query()->first();
        $this->assertNotNull($order);
        $order->status = Order::STATUS_CANCELLED;
        $order->save();

        $this->assertSame(2, (int) $variant->fresh()->quantity);
        $this->assertFalse((bool) $order->fresh()->inventory_committed);
    }

    public function test_failed_payment_restores_and_paid_recommits(): void
    {
        [$product, $variant, $size] = $this->makeSellableProduct(quantity: 1);

        $this->withSession([
            'cart' => [
                'p1' => [
                    'line_kind' => 'product',
                    'product_id' => $product->id,
                    'qty' => 1,
                    'option_value_ids' => [$size->id],
                ],
            ],
        ])->post(route('checkout.store'), $this->checkoutPayload())
            ->assertRedirect();

        $this->assertSame(0, (int) $variant->fresh()->quantity);

        $order = Order::query()->first();
        $this->assertNotNull($order);
        $order->payment_status = 'failed';
        $order->save();

        $this->assertSame(1, (int) $variant->fresh()->quantity);
        $this->assertFalse((bool) $order->fresh()->inventory_committed);

        app(ApplyApprovedOrderPayment::class)->apply($order->fresh(), 'full', 1000);

        $this->assertSame(0, (int) $variant->fresh()->quantity);
        $this->assertTrue((bool) $order->fresh()->inventory_committed);
    }

    public function test_cart_resolver_prefers_options_over_stale_variant_id(): void
    {
        [$product, $variantS, $sizeS] = $this->makeSellableProduct(quantity: 3);
        $sizeM = OptionValue::query()->create([
            'option_group_id' => $sizeS->option_group_id,
            'name' => 'M',
            'slug' => 'size-m-audit',
            'sort_order' => 2,
            'is_active' => true,
        ]);
        $variantM = ProductVariant::query()->create([
            'product_id' => $product->id,
            'price' => 1400,
            'quantity' => 3,
            'is_available' => true,
            'is_low_stock' => false,
            'is_visible' => true,
            'allows_preorder' => false,
            'is_sold' => false,
            'sort_order' => 2,
            'options' => [
                ['option_group_id' => $sizeS->option_group_id, 'option_value_id' => $sizeM->id],
            ],
        ]);
        $options = is_array($product->variant_options) ? $product->variant_options : [];
        foreach ($options as $i => $block) {
            if ((int) ($block['option_group_id'] ?? 0) === (int) $sizeS->option_group_id) {
                $options[$i]['option_value_ids'] = [$sizeS->id, $sizeM->id];
            }
        }
        $product->forceFill(['variant_options' => $options])->save();
        $product->unsetRelation('variants');
        $product->load('variants');

        $line = app(CartLineResolver::class)->resolveLine([
            'product_id' => $product->id,
            'qty' => 1,
            'option_value_ids' => [$sizeM->id],
            'variant_id' => $variantS->id,
        ], $product);

        $this->assertNotNull($line);
        $this->assertSame($variantM->id, $line['variant']?->id);
        $this->assertEqualsWithDelta(1400.0, (float) $line['unit_price'], 0.01);
    }

    public function test_drawer_keeps_unsellable_line_and_blocks_checkout(): void
    {
        [$product, $variant, $size] = $this->makeSellableProduct(quantity: 1);
        $this->post(route('cart.store'), [
            'product_id' => $product->id,
            'option_value_ids' => json_encode([$size->id], JSON_THROW_ON_ERROR),
        ])->assertRedirect(route('cart.index'));

        $variant->forceFill([
            'is_available' => false,
            'is_low_stock' => false,
            'quantity' => 0,
        ])->save();

        $this->get(route('cart.index'))
            ->assertOk()
            ->assertSee(__('shop.cart_product_out_of_stock'), false);

        $this->get(route('checkout.create'))
            ->assertRedirect(route('cart.index'))
            ->assertSessionHas('error');
    }

    public function test_cart_update_rejects_product_that_is_no_longer_orderable(): void
    {
        [$product, , $size] = $this->makeSellableProduct(quantity: 3);
        $this->post(route('cart.store'), [
            'product_id' => $product->id,
            'option_value_ids' => json_encode([$size->id], JSON_THROW_ON_ERROR),
        ])->assertRedirect(route('cart.index'));

        $product->forceFill(['stock_status' => Product::STOCK_OUT_OF_STOCK])->save();
        $cartKey = array_key_first(session('cart', []));

        $this->patch(route('cart.update', $cartKey), ['qty' => 2])
            ->assertRedirect()
            ->assertSessionHas('error');
    }

    public function test_search_title_does_not_use_category_name(): void
    {
        [$product, , $size] = $this->makeSellableProduct(quantity: 2);
        $rootId = (int) $product->category_parent_value_id;

        $this->get(route('catalog.index', ['q' => $product->title, 'category' => $rootId]))
            ->assertOk()
            ->assertSee(__('shop.catalog_toolbar_search_results'), false)
            ->assertDontSee('catalog-toolbar__title">'.e(OptionValue::query()->find($rootId)?->name ?? '___never___'), false);
    }

    public function test_category_nav_from_search_drops_query_so_branch_products_appear(): void
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
        $searchRoot = OptionValue::query()->create([
            'option_group_id' => $categoryGroup->id,
            'name' => 'Пошукова гілка',
            'slug' => 'search-nav-root-'.uniqid(),
            'sort_order' => 10,
            'is_active' => true,
        ]);
        $otherRoot = OptionValue::query()->create([
            'option_group_id' => $categoryGroup->id,
            'name' => 'Інша гілка',
            'slug' => 'other-nav-root-'.uniqid(),
            'sort_order' => 11,
            'is_active' => true,
        ]);

        $searchProduct = Product::query()->create([
            'title' => 'ZXSearchOnlyWidget '.uniqid(),
            'slug' => 'zx-search-only-'.uniqid(),
            'product_type' => OptionGroup::CATALOG_PRODUCT_TYPE,
            'category_parent_value_id' => $searchRoot->id,
            'price' => 100,
            'is_available' => true,
            'stock_status' => Product::STOCK_IN_STOCK,
            'variant_options' => [
                [
                    'option_group_id' => $categoryGroup->id,
                    'option_value_ids' => [$searchRoot->id],
                ],
            ],
        ]);
        $branchProduct = Product::query()->create([
            'title' => 'ZXBranchOnlyGadget '.uniqid(),
            'slug' => 'zx-branch-only-'.uniqid(),
            'product_type' => OptionGroup::CATALOG_PRODUCT_TYPE,
            'category_parent_value_id' => $otherRoot->id,
            'price' => 200,
            'is_available' => true,
            'stock_status' => Product::STOCK_IN_STOCK,
            'variant_options' => [
                [
                    'option_group_id' => $categoryGroup->id,
                    'option_value_ids' => [$otherRoot->id],
                ],
            ],
        ]);

        $searchPage = $this->get(route('catalog.index', ['q' => $searchProduct->title]));
        $searchPage->assertOk();
        $searchPage->assertSee($searchProduct->title, false);
        $searchPage->assertDontSee($branchProduct->title, false);
        $searchPage->assertSee(__('shop.catalog_filter_search_storewide').' '.$searchProduct->title, false);

        $categoryHref = route('catalog.index', ['category' => $otherRoot->id]);
        $searchPage->assertSee($categoryHref, false);

        preg_match_all(
            '/href="([^"]+)"\s+class="[^"]*catalog-category-list__link/',
            (string) $searchPage->getContent(),
            $categoryHrefs
        );
        $this->assertNotSame([], $categoryHrefs[1]);
        foreach ($categoryHrefs[1] as $href) {
            $this->assertStringNotContainsString('q=', html_entity_decode($href, ENT_QUOTES));
        }

        $categoryPage = $this->get($categoryHref);
        $categoryPage->assertOk();
        $categoryPage->assertSee($branchProduct->title, false);
        $categoryPage->assertDontSee($searchProduct->title, false);
        $categoryPage->assertDontSee(__('shop.catalog_filter_search_storewide'), false);
        $categoryPage->assertSee(mt($otherRoot->name), false);
    }

    public function test_guest_cart_merges_into_saved_user_cart(): void
    {
        [$product, , $size] = $this->makeSellableProduct(quantity: 4);
        $user = User::factory()->create([
            'saved_cart' => [
                'saved' => [
                    'line_kind' => 'product',
                    'product_id' => $product->id,
                    'qty' => 1,
                    'option_value_ids' => [$size->id],
                ],
            ],
        ]);

        $guest = [
            'guest' => [
                'line_kind' => 'product',
                'product_id' => $product->id,
                'qty' => 2,
                'option_value_ids' => [$size->id],
            ],
        ];
        $merged = ShopCart::merge($guest, is_array($user->saved_cart) ? $user->saved_cart : []);
        $user->forceFill(['saved_cart' => $merged])->save();

        $this->assertCount(2, $merged);
        $this->assertSame(2, (int) $merged['guest']['qty']);
        $this->assertSame(1, (int) $merged['saved']['qty']);
        $this->assertSame($merged, $user->fresh()->saved_cart);
    }

    public function test_pdp_price_matches_cart_line_total(): void
    {
        [$product, $variant, $size] = $this->makeSellableProduct(quantity: 2, price: 1000);

        $this->post(route('cart.store'), [
            'product_id' => $product->id,
            'option_value_ids' => json_encode([$size->id], JSON_THROW_ON_ERROR),
            'variant_id' => $variant->id,
        ])->assertRedirect(route('cart.index'));

        $this->get(route('cart.index'))
            ->assertOk()
            ->assertSee('1 000,00');
    }

    public function test_admin_bundle_create_uses_component_deferral(): void
    {
        [$product, , $size] = $this->makeSellableProduct(quantity: 2);
        $product->forceFill(['stock_status' => Product::STOCK_PREORDER])->save();

        $bundle = Bundle::query()->create([
            'title' => 'Deferred bundle',
            'slug' => 'deferred-bundle',
            'is_visible' => true,
            'is_active' => true,
        ]);
        BundleItem::query()->create([
            'bundle_id' => $bundle->id,
            'product_id' => $product->id,
            'qty' => 1,
            'option_value_ids' => [$size->id],
        ]);

        $prepared = app(AdminOrderItemEditor::class)->prepareBundleCreate([
            'bundle_id' => $bundle->id,
            'qty' => 1,
        ]);

        $this->assertTrue((bool) $prepared['line_defers_online_payment']);
        $this->assertNotEmpty($prepared['bundle_snapshot']['items'] ?? []);
    }

    public function test_shop_cart_merge_adds_matching_keys(): void
    {
        $merged = ShopCart::merge(
            ['a' => ['product_id' => 1, 'qty' => 2, 'option_value_ids' => []]],
            ['a' => ['product_id' => 1, 'qty' => 3, 'option_value_ids' => []]]
        );

        $this->assertSame(5, $merged['a']['qty']);
    }

    /**
     * @return array{0: Product, 1: ProductVariant, 2: OptionValue}
     */
    private function makeSellableProduct(int $quantity = 2, float $price = 1000): array
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
        $root = OptionValue::query()->firstOrCreate(
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
        $sizeGroup = OptionGroup::query()->create([
            'product_type' => OptionGroup::CATALOG_PRODUCT_TYPE,
            'name' => 'Розмір',
            'slug' => 'size-audit-'.uniqid(),
            'selection_mode' => 'single',
            'value_type' => 'text',
            'is_active' => true,
        ]);
        $size = OptionValue::query()->create([
            'option_group_id' => $sizeGroup->id,
            'name' => 'S',
            'slug' => 's-audit-'.uniqid(),
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $product = Product::query()->create([
            'title' => 'Audit product '.uniqid(),
            'slug' => 'audit-product-'.uniqid(),
            'product_type' => OptionGroup::CATALOG_PRODUCT_TYPE,
            'category_parent_value_id' => $root->id,
            'price' => $price,
            'is_available' => true,
            'stock_status' => Product::STOCK_IN_STOCK,
            'variant_options' => [
                [
                    'option_group_id' => $categoryGroup->id,
                    'option_value_ids' => [$root->id],
                ],
                [
                    'option_group_id' => $sizeGroup->id,
                    'option_value_ids' => [$size->id],
                ],
            ],
        ]);

        $variant = ProductVariant::query()->create([
            'product_id' => $product->id,
            'price' => $price,
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

    /**
     * @return array<string, string>
     */
    private function checkoutPayload(): array
    {
        return [
            'customer_name' => 'Audit Buyer',
            'customer_phone' => '380501112233',
            'customer_email' => 'audit@example.test',
            'payment_method' => 'cod',
            'delivery_type' => Order::DELIVERY_PICKUP,
        ];
    }
}
