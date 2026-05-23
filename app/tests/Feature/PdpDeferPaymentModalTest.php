<?php

namespace Tests\Feature;

use App\Models\OptionGroup;
use App\Models\OptionValue;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\CategoryCheckoutRulesService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PdpDeferPaymentModalTest extends TestCase
{
    use RefreshDatabase;

    public function test_defer_rules_apply_when_product_is_assigned_to_root_category_only(): void
    {
        [$product, $root] = $this->makeProductInDeferRootCategory(leafInChild: false);

        $rules = app(CategoryCheckoutRulesService::class)->aggregateForProductIds([(int) $product->id]);

        $this->assertTrue($rules['defers_online_payment']);
        $this->assertSame($root->id, $product->resolvedCatalogCategoryNodeId());
    }

    public function test_pdp_includes_defer_modal_for_root_only_category_assignment(): void
    {
        [$product] = $this->makeProductInDeferRootCategory(leafInChild: false);

        $response = $this->get(route('catalog.show', $product->slug));

        $response->assertOk();
        $response->assertSee('id="pdp-defer-payment-modal"', false);
        $response->assertSee('"pdpDefersOnlinePayment":true', false);
    }

    public function test_pdp_includes_defer_modal_for_product_in_child_category(): void
    {
        [$product] = $this->makeProductInDeferRootCategory(leafInChild: true);

        $response = $this->get(route('catalog.show', $product->slug));

        $response->assertOk();
        $response->assertSee('id="pdp-defer-payment-modal"', false);
        $response->assertSee('"pdpDefersOnlinePayment":true', false);
    }

    /**
     * @return array{Product, OptionValue, OptionValue|null}
     */
    private function makeProductInDeferRootCategory(bool $leafInChild): array
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

        $root = OptionValue::query()->create([
            'option_group_id' => $categoryGroup->id,
            'parent_id' => null,
            'name' => 'Defer root',
            'slug' => 'defer-root-'.uniqid(),
            'sort_order' => 1,
            'is_active' => true,
            'defer_online_payment' => true,
        ]);

        $child = null;
        $categoryNodeId = $root->id;
        $categoryParentId = $root->id;
        $storedLeafId = null;

        if ($leafInChild) {
            $child = OptionValue::query()->create([
                'option_group_id' => $categoryGroup->id,
                'parent_id' => $root->id,
                'name' => 'Defer child',
                'slug' => 'defer-child-'.uniqid(),
                'sort_order' => 1,
                'is_active' => true,
            ]);
            $categoryNodeId = $child->id;
            $storedLeafId = $child->id;
        }

        $variantOptions = [
            [
                'option_group_id' => $categoryGroup->id,
                'option_value_ids' => [$categoryNodeId],
            ],
        ];

        $product = Product::query()->create([
            'title' => 'Defer PDP product',
            'slug' => 'defer-pdp-'.uniqid(),
            'product_type' => OptionGroup::CATALOG_PRODUCT_TYPE,
            'category_parent_value_id' => $categoryParentId,
            'category_value_id' => $storedLeafId,
            'description' => 'd',
            'short_description' => 's',
            'is_available' => true,
            'variant_options' => $variantOptions,
        ]);

        ProductVariant::query()->create([
            'product_id' => $product->id,
            'price' => 100,
            'quantity' => 2,
            'is_available' => true,
            'is_visible' => true,
            'sort_order' => 1,
            'options' => [],
        ]);

        return [$product, $root, $child];
    }
}
