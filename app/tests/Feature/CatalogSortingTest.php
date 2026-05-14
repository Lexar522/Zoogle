<?php

namespace Tests\Feature;

use App\Models\OptionGroup;
use App\Models\OptionValue;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogSortingTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_accepts_sort_and_per_page_and_orders_by_title_asc(): void
    {
        $root = $this->catalogRoot();

        $variantOptions = [
            [
                'option_group_id' => $root->option_group_id,
                'option_value_ids' => [$root->id],
            ],
        ];

        Product::query()->create([
            'title' => 'CatalogSort ZZZ',
            'slug' => 'catalog-sort-zzz',
            'product_type' => OptionGroup::CATALOG_PRODUCT_TYPE,
            'category_parent_value_id' => $root->id,
            'category_value_id' => null,
            'price' => 200,
            'is_available' => true,
            'short_description' => 'Z',
            'variant_options' => $variantOptions,
        ]);

        Product::query()->create([
            'title' => 'CatalogSort AAA',
            'slug' => 'catalog-sort-aaa',
            'product_type' => OptionGroup::CATALOG_PRODUCT_TYPE,
            'category_parent_value_id' => $root->id,
            'category_value_id' => null,
            'price' => 100,
            'is_available' => true,
            'short_description' => 'A',
            'variant_options' => $variantOptions,
        ]);

        $response = $this->get(route('catalog.index', [
            'category' => $root->id,
            'sort' => 'title_asc',
            'per_page' => 12,
        ]));

        $response->assertOk();
        $response->assertViewHas('listings', fn ($listings) => $listings->perPage() === 12);

        $r16 = $this->get(route('catalog.index', [
            'category' => $root->id,
            'per_page' => 16,
        ]));
        $r16->assertOk();
        $r16->assertViewHas('listings', fn ($listings) => $listings->perPage() === 16);

        $html = $response->getContent();
        $posA = strpos($html, 'CatalogSort AAA');
        $posZ = strpos($html, 'CatalogSort ZZZ');
        $this->assertNotFalse($posA);
        $this->assertNotFalse($posZ);
        $this->assertLessThan($posZ, $posA);
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
