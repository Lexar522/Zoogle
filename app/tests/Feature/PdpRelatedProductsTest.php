<?php

namespace Tests\Feature;

use App\Models\OptionGroup;
use App\Models\OptionValue;
use App\Models\Product;
use App\Services\ProductRecommendationsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PdpRelatedProductsTest extends TestCase
{
    use RefreshDatabase;

    public function test_pdp_shows_manually_selected_recommended_products_in_order(): void
    {
        $root = $this->catalogRoot();
        $variantOptions = $this->variantOptions($root);

        $primary = Product::query()->create([
            'title' => 'Primary product',
            'slug' => 'pdp-manual-primary',
            'product_type' => OptionGroup::CATALOG_PRODUCT_TYPE,
            'category_parent_value_id' => $root->id,
            'category_value_id' => null,
            'price' => 100,
            'is_available' => true,
            'short_description' => 'P',
            'variant_options' => $variantOptions,
        ]);

        $first = Product::query()->create([
            'title' => 'Recommended first',
            'slug' => 'pdp-manual-first',
            'sku' => 'SKU-FIRST',
            'product_type' => OptionGroup::CATALOG_PRODUCT_TYPE,
            'category_parent_value_id' => $root->id,
            'category_value_id' => null,
            'price' => 200,
            'is_available' => true,
            'short_description' => '1',
            'variant_options' => $variantOptions,
        ]);

        $second = Product::query()->create([
            'title' => 'Recommended second',
            'slug' => 'pdp-manual-second',
            'sku' => 'SKU-SECOND',
            'product_type' => OptionGroup::CATALOG_PRODUCT_TYPE,
            'category_parent_value_id' => $root->id,
            'category_value_id' => null,
            'price' => 300,
            'is_available' => true,
            'short_description' => '2',
            'variant_options' => $variantOptions,
        ]);

        ProductRecommendationsService::syncForProduct($primary, [
            ['product_id' => $first->id],
            ['product_id' => $second->id],
        ]);

        $response = $this->get(route('catalog.show', $primary->slug));
        $response->assertOk();

        $html = (string) $response->getContent();
        $posFirst = strpos($html, route('catalog.show', $first->slug));
        $posSecond = strpos($html, route('catalog.show', $second->slug));

        $this->assertNotFalse($posFirst);
        $this->assertNotFalse($posSecond);
        $this->assertLessThan($posSecond, $posFirst);
    }

    public function test_pdp_hides_unavailable_manual_recommendations(): void
    {
        $root = $this->catalogRoot();
        $variantOptions = $this->variantOptions($root);

        $primary = Product::query()->create([
            'title' => 'Primary availability',
            'slug' => 'pdp-avail-primary',
            'product_type' => OptionGroup::CATALOG_PRODUCT_TYPE,
            'category_parent_value_id' => $root->id,
            'category_value_id' => null,
            'price' => 100,
            'is_available' => true,
            'short_description' => 'P',
            'variant_options' => $variantOptions,
        ]);

        $visible = Product::query()->create([
            'title' => 'Visible recommended',
            'slug' => 'pdp-avail-visible',
            'product_type' => OptionGroup::CATALOG_PRODUCT_TYPE,
            'category_parent_value_id' => $root->id,
            'category_value_id' => null,
            'price' => 200,
            'is_available' => true,
            'short_description' => 'V',
            'variant_options' => $variantOptions,
        ]);

        $hidden = Product::query()->create([
            'title' => 'Hidden recommended',
            'slug' => 'pdp-avail-hidden',
            'product_type' => OptionGroup::CATALOG_PRODUCT_TYPE,
            'category_parent_value_id' => $root->id,
            'category_value_id' => null,
            'price' => 300,
            'is_available' => false,
            'short_description' => 'H',
            'variant_options' => $variantOptions,
        ]);

        ProductRecommendationsService::syncForProduct($primary, [
            ['product_id' => $hidden->id],
            ['product_id' => $visible->id],
        ]);

        $response = $this->get(route('catalog.show', $primary->slug));
        $response->assertOk();
        $response->assertSee(route('catalog.show', $visible->slug), false);
        $response->assertDontSee(route('catalog.show', $hidden->slug), false);
    }

    public function test_pdp_without_manual_recommendations_hides_related_block(): void
    {
        $root = $this->catalogRoot();
        $variantOptions = $this->variantOptions($root);

        $primary = Product::query()->create([
            'title' => 'Primary empty related',
            'slug' => 'pdp-empty-related',
            'product_type' => OptionGroup::CATALOG_PRODUCT_TYPE,
            'category_parent_value_id' => $root->id,
            'category_value_id' => null,
            'price' => 100,
            'is_available' => true,
            'short_description' => 'P',
            'variant_options' => $variantOptions,
        ]);

        $response = $this->get(route('catalog.show', $primary->slug));
        $response->assertOk();
        $response->assertDontSee('id="pdp-recommended"', false);
    }

    /**
     * @return list<array{option_group_id: int, option_value_ids: list<int>}>
     */
    private function variantOptions(OptionValue $root): array
    {
        return [
            [
                'option_group_id' => $root->option_group_id,
                'option_value_ids' => [$root->id],
            ],
        ];
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
}
