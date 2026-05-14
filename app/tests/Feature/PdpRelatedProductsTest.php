<?php

namespace Tests\Feature;

use App\Models\OptionGroup;
use App\Models\OptionValue;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PdpRelatedProductsTest extends TestCase
{
    use RefreshDatabase;

    public function test_pdp_prioritizes_more_identical_tags_over_fewer(): void
    {
        $root = $this->catalogRoot();

        $variantOptions = [
            [
                'option_group_id' => $root->option_group_id,
                'option_value_ids' => [$root->id],
            ],
        ];

        $primary = Product::query()->create([
            'title' => 'Primary multi-tag',
            'slug' => 'pdp-multi-primary',
            'product_type' => OptionGroup::CATALOG_PRODUCT_TYPE,
            'category_parent_value_id' => $root->id,
            'category_value_id' => null,
            'price' => 100,
            'is_available' => true,
            'search_tags' => ['alfa', 'beta', 'gamma'],
            'short_description' => 'P',
            'variant_options' => $variantOptions,
        ]);

        $threeShared = Product::query()->create([
            'title' => 'Match three tags',
            'slug' => 'pdp-multi-three',
            'product_type' => OptionGroup::CATALOG_PRODUCT_TYPE,
            'category_parent_value_id' => $root->id,
            'category_value_id' => null,
            'price' => 200,
            'is_available' => true,
            'search_tags' => ['alfa', 'beta', 'gamma'],
            'short_description' => '3',
            'variant_options' => $variantOptions,
        ]);

        $oneShared = Product::query()->create([
            'title' => 'Match one tag',
            'slug' => 'pdp-multi-one',
            'product_type' => OptionGroup::CATALOG_PRODUCT_TYPE,
            'category_parent_value_id' => $root->id,
            'category_value_id' => null,
            'price' => 300,
            'is_available' => true,
            'search_tags' => ['alfa'],
            'short_description' => '1',
            'variant_options' => $variantOptions,
        ]);

        $response = $this->get(route('catalog.show', $primary->slug));

        $response->assertOk();
        $html = (string) $response->getContent();
        $posThree = strpos($html, route('catalog.show', $threeShared->slug));
        $posOne = strpos($html, route('catalog.show', $oneShared->slug));
        $this->assertNotFalse($posThree);
        $this->assertNotFalse($posOne);
        $this->assertLessThan($posOne, $posThree);
    }

    public function test_pdp_shows_related_products_by_shared_tags_in_category(): void
    {
        $root = $this->catalogRoot();

        $variantOptions = [
            [
                'option_group_id' => $root->option_group_id,
                'option_value_ids' => [$root->id],
            ],
        ];

        $primary = Product::query()->create([
            'title' => 'PDP Primary',
            'slug' => 'pdp-primary',
            'product_type' => OptionGroup::CATALOG_PRODUCT_TYPE,
            'category_parent_value_id' => $root->id,
            'category_value_id' => null,
            'price' => 100,
            'is_available' => true,
            'search_tags' => ['корм', 'premium'],
            'short_description' => 'Primary',
            'variant_options' => $variantOptions,
        ]);

        $strongMatch = Product::query()->create([
            'title' => 'PDP Strong Match',
            'slug' => 'pdp-strong-match',
            'product_type' => OptionGroup::CATALOG_PRODUCT_TYPE,
            'category_parent_value_id' => $root->id,
            'category_value_id' => null,
            'price' => 200,
            'is_available' => true,
            'search_tags' => ['корм', 'premium', 'дріб'],
            'short_description' => 'Strong',
            'variant_options' => $variantOptions,
        ]);

        $weakMatch = Product::query()->create([
            'title' => 'PDP Weak Match',
            'slug' => 'pdp-weak-match',
            'product_type' => OptionGroup::CATALOG_PRODUCT_TYPE,
            'category_parent_value_id' => $root->id,
            'category_value_id' => null,
            'price' => 300,
            'is_available' => true,
            'search_tags' => ['корм'],
            'short_description' => 'Weak',
            'variant_options' => $variantOptions,
        ]);

        $response = $this->get(route('catalog.show', $primary->slug));

        $response->assertOk();
        $response->assertSee('Схожі товари', false);
        $response->assertSee(route('catalog.show', $strongMatch->slug), false);
        $response->assertSee(route('catalog.show', $weakMatch->slug), false);

        $html = (string) $response->getContent();
        $posStrong = strpos($html, route('catalog.show', $strongMatch->slug));
        $posWeak = strpos($html, route('catalog.show', $weakMatch->slug));
        $this->assertNotFalse($posStrong);
        $this->assertNotFalse($posWeak);
        $this->assertLessThan($posWeak, $posStrong);
    }

    public function test_pdp_related_includes_products_with_shared_title_words(): void
    {
        $root = $this->catalogRoot();

        $variantOptions = [
            [
                'option_group_id' => $root->option_group_id,
                'option_value_ids' => [$root->id],
            ],
        ];

        $primary = Product::query()->create([
            'title' => 'Блок Zoogle хомяк тест',
            'slug' => 'pdp-title-primary',
            'product_type' => OptionGroup::CATALOG_PRODUCT_TYPE,
            'category_parent_value_id' => $root->id,
            'category_value_id' => null,
            'price' => 100,
            'is_available' => true,
            'search_tags' => [],
            'short_description' => 'P',
            'variant_options' => $variantOptions,
        ]);

        $byTitle = Product::query()->create([
            'title' => 'Інший товар Zoogle хомяк',
            'slug' => 'pdp-title-match',
            'product_type' => OptionGroup::CATALOG_PRODUCT_TYPE,
            'category_parent_value_id' => $root->id,
            'category_value_id' => null,
            'price' => 200,
            'is_available' => true,
            'search_tags' => [],
            'short_description' => 'T',
            'variant_options' => $variantOptions,
        ]);

        Product::query()->create([
            'title' => 'Поводок шкіряний простий',
            'slug' => 'pdp-title-unrelated',
            'product_type' => OptionGroup::CATALOG_PRODUCT_TYPE,
            'category_parent_value_id' => $root->id,
            'category_value_id' => null,
            'price' => 300,
            'is_available' => true,
            'search_tags' => [],
            'short_description' => 'U',
            'variant_options' => $variantOptions,
        ]);

        $response = $this->get(route('catalog.show', $primary->slug));

        $response->assertOk();
        $response->assertSee(route('catalog.show', $byTitle->slug), false);
    }

    public function test_pdp_tag_on_one_product_matches_substring_in_other_title(): void
    {
        $root = $this->catalogRoot();

        $variantOptions = [
            [
                'option_group_id' => $root->option_group_id,
                'option_value_ids' => [$root->id],
            ],
        ];

        $primary = Product::query()->create([
            'title' => 'Товар А',
            'slug' => 'pdp-tag-in-title-a',
            'product_type' => OptionGroup::CATALOG_PRODUCT_TYPE,
            'category_parent_value_id' => $root->id,
            'category_value_id' => null,
            'price' => 100,
            'is_available' => true,
            'search_tags' => ['hills'],
            'short_description' => 'A',
            'variant_options' => $variantOptions,
        ]);

        $titleMatch = Product::query()->create([
            'title' => 'Сухий корм Hills Science Plan',
            'slug' => 'pdp-tag-in-title-b',
            'product_type' => OptionGroup::CATALOG_PRODUCT_TYPE,
            'category_parent_value_id' => $root->id,
            'category_value_id' => null,
            'price' => 200,
            'is_available' => true,
            'search_tags' => [],
            'short_description' => 'B',
            'variant_options' => $variantOptions,
        ]);

        $response = $this->get(route('catalog.show', $primary->slug));

        $response->assertOk();
        $response->assertSee(route('catalog.show', $titleMatch->slug), false);
    }

    public function test_pdp_related_tags_ignore_case_in_overlap(): void
    {
        $root = $this->catalogRoot();

        $variantOptions = [
            [
                'option_group_id' => $root->option_group_id,
                'option_value_ids' => [$root->id],
            ],
        ];

        $primary = Product::query()->create([
            'title' => 'Case PDP',
            'slug' => 'pdp-case-primary',
            'product_type' => OptionGroup::CATALOG_PRODUCT_TYPE,
            'category_parent_value_id' => $root->id,
            'category_value_id' => null,
            'price' => 100,
            'is_available' => true,
            'search_tags' => ['Premium', 'КОРМ'],
            'short_description' => 'P',
            'variant_options' => $variantOptions,
        ]);

        $caseMatch = Product::query()->create([
            'title' => 'Case Match',
            'slug' => 'pdp-case-match',
            'product_type' => OptionGroup::CATALOG_PRODUCT_TYPE,
            'category_parent_value_id' => $root->id,
            'category_value_id' => null,
            'price' => 200,
            'is_available' => true,
            'search_tags' => ['premium', 'корм'],
            'short_description' => 'M',
            'variant_options' => $variantOptions,
        ]);

        $response = $this->get(route('catalog.show', $primary->slug));

        $response->assertOk();
        $response->assertSee(route('catalog.show', $caseMatch->slug), false);
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
