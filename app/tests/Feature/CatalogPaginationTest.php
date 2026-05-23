<?php

namespace Tests\Feature;

use App\Models\OptionGroup;
use App\Models\OptionValue;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogPaginationTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_page_two_shows_different_products(): void
    {
        $root = $this->catalogRoot();
        $variantOptions = [
            [
                'option_group_id' => $root->option_group_id,
                'option_value_ids' => [$root->id],
            ],
        ];

        foreach (range(1, 15) as $i) {
            Product::query()->create([
                'title' => 'PagProduct '.str_pad((string) $i, 2, '0', STR_PAD_LEFT),
                'slug' => 'pag-product-'.$i,
                'product_type' => OptionGroup::CATALOG_PRODUCT_TYPE,
                'category_parent_value_id' => $root->id,
                'category_value_id' => null,
                'price' => 100 + $i,
                'is_available' => true,
                'short_description' => (string) $i,
                'variant_options' => $variantOptions,
            ]);
        }

        $page1 = $this->get(route('catalog.index', [
            'category' => $root->id,
            'per_page' => 12,
        ]));
        $page1->assertOk();
        $page1->assertSee('PagProduct 01', false);
        $page1->assertSee('data-catalog-load-more', false);

        $page2 = $this->get(route('catalog.index', [
            'category' => $root->id,
            'per_page' => 12,
            'page' => 2,
        ]));
        $page2->assertOk();
        $page2->assertSee('PagProduct 13', false);
        $page2->assertDontSee('PagProduct 01', false);
    }

    public function test_catalog_fragment_returns_partial_with_pagination_links(): void
    {
        $root = $this->catalogRoot();
        $variantOptions = [
            [
                'option_group_id' => $root->option_group_id,
                'option_value_ids' => [$root->id],
            ],
        ];

        foreach (range(1, 15) as $i) {
            Product::query()->create([
                'title' => 'FragProduct '.$i,
                'slug' => 'frag-product-'.$i,
                'product_type' => OptionGroup::CATALOG_PRODUCT_TYPE,
                'category_parent_value_id' => $root->id,
                'category_value_id' => null,
                'price' => 50,
                'is_available' => true,
                'short_description' => (string) $i,
                'variant_options' => $variantOptions,
            ]);
        }

        $response = $this->get(route('catalog.index', [
            'category' => $root->id,
            'per_page' => 12,
        ]), [
            'X-Catalog-Fragment' => '1',
        ]);

        $response->assertOk();
        $html = $response->getContent();
        $this->assertStringContainsString('data-catalog-product-grid', $html);
        $this->assertStringContainsString('catalog-listing-footer', $html);
        $this->assertStringContainsString('shop-pagination', $html);
        $this->assertStringContainsString('page=2', $html);
        $this->assertStringNotContainsString('<!DOCTYPE', $html);
    }

    public function test_single_product_still_shows_pagination_bar(): void
    {
        $root = $this->catalogRoot();
        $variantOptions = [
            [
                'option_group_id' => $root->option_group_id,
                'option_value_ids' => [$root->id],
            ],
        ];

        Product::query()->create([
            'title' => 'SoloProduct',
            'slug' => 'solo-product',
            'product_type' => OptionGroup::CATALOG_PRODUCT_TYPE,
            'category_parent_value_id' => $root->id,
            'category_value_id' => null,
            'price' => 100,
            'is_available' => true,
            'short_description' => 'solo',
            'variant_options' => $variantOptions,
        ]);

        $response = $this->get(route('catalog.index', ['category' => $root->id]));
        $response->assertOk();
        $html = $response->getContent();
        $this->assertStringContainsString('catalog-listing-footer', $html);
        $this->assertStringContainsString('shop-pagination', $html);
        $this->assertStringContainsString('aria-current="page">1<', $html);
        $this->assertStringNotContainsString('data-catalog-load-more', $html);
    }

    public function test_progressive_pagination_shows_only_pages_up_to_current_plus_one(): void
    {
        $root = $this->catalogRoot();
        $variantOptions = [
            [
                'option_group_id' => $root->option_group_id,
                'option_value_ids' => [$root->id],
            ],
        ];

        foreach (range(1, 60) as $i) {
            Product::query()->create([
                'title' => 'ProgProduct '.$i,
                'slug' => 'prog-product-'.$i,
                'product_type' => OptionGroup::CATALOG_PRODUCT_TYPE,
                'category_parent_value_id' => $root->id,
                'category_value_id' => null,
                'price' => 10,
                'is_available' => true,
                'short_description' => (string) $i,
                'variant_options' => $variantOptions,
            ]);
        }

        $page1 = $this->get(route('catalog.index', [
            'category' => $root->id,
            'per_page' => 12,
        ]));
        $page1->assertOk();
        $html1 = $page1->getContent();
        $this->assertMatchesRegularExpression('/shop-pagination__link[^>]+page=2/', $html1);
        $this->assertDoesNotMatchRegularExpression('/shop-pagination__link[^>]+page=5/', $html1);

        $page4 = $this->get(route('catalog.index', [
            'category' => $root->id,
            'per_page' => 12,
            'page' => 4,
        ]));
        $page4->assertOk();
        $html4 = $page4->getContent();
        $this->assertMatchesRegularExpression('/shop-pagination__link[^>]+page=5/', $html4);
        $this->assertDoesNotMatchRegularExpression('/shop-pagination__link[^>]+page=6/', $html4);
        $this->assertStringContainsString('aria-current="page">4<', $html4);
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
