<?php

namespace Tests\Feature;

use App\Models\Bundle;
use App\Models\BundleItem;
use App\Models\OptionGroup;
use App\Models\OptionValue;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogBundlesTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_shows_active_visible_bundle_as_catalog_card(): void
    {
        $root = $this->catalogRoot();

        $product = Product::query()->create([
            'title' => 'Товар для комплекту',
            'slug' => 'bundle-product',
            'product_type' => OptionGroup::CATALOG_PRODUCT_TYPE,
            'category_parent_value_id' => $root->id,
            'category_value_id' => null,
            'price' => 1200,
            'is_available' => true,
            'short_description' => 'Товар усередині комплекту',
            'variant_options' => [
                [
                    'option_group_id' => $root->option_group_id,
                    'option_value_ids' => [$root->id],
                ],
            ],
        ]);

        $bundle = Bundle::query()->create([
            'title' => 'Тестовий комплект',
            'slug' => 'test-bundle',
            'short_description' => 'Опис комплекту для каталогу',
            'category_parent_value_id' => $root->id,
            'category_value_id' => null,
            'is_visible' => true,
            'is_active' => true,
        ]);

        BundleItem::query()->create([
            'bundle_id' => $bundle->id,
            'product_id' => $product->id,
            'qty' => 1,
            'sort_order' => 1,
        ]);

        $response = $this->get(route('catalog.index'));

        $response
            ->assertOk()
            ->assertSee('Тестовий комплект')
            ->assertSee(route('bundles.show', $bundle->slug))
            ->assertSee(route('bundles.add-to-cart', $bundle))
            ->assertSee('Комплект');
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
