<?php

namespace Tests\Feature;

use App\Models\OptionGroup;
use App\Models\OptionValue;
use App\Models\Product;
use App\Services\ProductCatalogCategoriesService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductMultiCategoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_appears_in_additional_category_catalog(): void
    {
        [$dogs, $cats, $dogToys, $catToys] = $this->seedAnimalToyCategories();

        $product = Product::query()->create([
            'title' => 'MultiCat Soft Toy',
            'slug' => 'multicat-soft-toy',
            'product_type' => OptionGroup::CATALOG_PRODUCT_TYPE,
            'category_parent_value_id' => $dogs->id,
            'category_value_id' => $dogToys->id,
            'price' => 150,
            'is_available' => true,
            'short_description' => 'Toy',
            'variant_options' => [
                [
                    'option_group_id' => $dogs->option_group_id,
                    'option_value_ids' => [$dogToys->id],
                ],
            ],
        ]);

        ProductCatalogCategoriesService::syncForProduct($product, [$catToys->id]);

        $this->assertDatabaseHas('product_categories', [
            'product_id' => $product->id,
            'category_value_id' => $dogToys->id,
        ]);
        $this->assertDatabaseHas('product_categories', [
            'product_id' => $product->id,
            'category_value_id' => $catToys->id,
        ]);

        $primaryListing = $this->get(route('catalog.index', ['category' => $dogToys->id]));
        $primaryListing->assertOk();
        $primaryListing->assertSee('MultiCat Soft Toy', false);

        $additionalListing = $this->get(route('catalog.index', ['category' => $catToys->id]));
        $additionalListing->assertOk();
        $additionalListing->assertSee('MultiCat Soft Toy', false);

        $additionalRoot = $this->get(route('catalog.index', ['category' => $cats->id]));
        $additionalRoot->assertOk();
        $additionalRoot->assertSee('MultiCat Soft Toy', false);
    }

    public function test_additional_categories_form_excludes_primary(): void
    {
        [$dogs, $cats, $dogToys, $catToys] = $this->seedAnimalToyCategories();

        $product = Product::query()->create([
            'title' => 'Primary Only Check',
            'slug' => 'primary-only-check',
            'product_type' => OptionGroup::CATALOG_PRODUCT_TYPE,
            'category_parent_value_id' => $dogs->id,
            'category_value_id' => $dogToys->id,
            'price' => 99,
            'is_available' => true,
            'variant_options' => [],
        ]);

        ProductCatalogCategoriesService::syncForProduct($product, [$dogToys->id, $catToys->id]);

        $ids = ProductCatalogCategoriesService::additionalCategoryIdsForForm($product->fresh());

        $this->assertSame([(string) $catToys->id], $ids);
        $this->assertSame($dogToys->id, ProductCatalogCategoriesService::primaryCategoryNodeId($product));
        unset($cats);
    }

    /**
     * @return array{0: OptionValue, 1: OptionValue, 2: OptionValue, 3: OptionValue}
     */
    private function seedAnimalToyCategories(): array
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

        $dogs = OptionValue::query()->firstOrCreate(
            [
                'option_group_id' => $categoryGroup->id,
                'slug' => 'dogs-root',
            ],
            [
                'name' => 'Собаки',
                'sort_order' => 1,
                'is_active' => true,
            ]
        );

        $cats = OptionValue::query()->firstOrCreate(
            [
                'option_group_id' => $categoryGroup->id,
                'slug' => 'cats-root',
            ],
            [
                'name' => 'Коти',
                'sort_order' => 2,
                'is_active' => true,
            ]
        );

        $dogToys = OptionValue::query()->firstOrCreate(
            [
                'option_group_id' => $categoryGroup->id,
                'slug' => 'dogs-toys',
            ],
            [
                'name' => 'Іграшки',
                'parent_id' => $dogs->id,
                'sort_order' => 1,
                'is_active' => true,
            ]
        );
        $dogToys->parent_id = $dogs->id;
        $dogToys->save();

        $catToys = OptionValue::query()->firstOrCreate(
            [
                'option_group_id' => $categoryGroup->id,
                'slug' => 'cats-toys',
            ],
            [
                'name' => 'Іграшки',
                'parent_id' => $cats->id,
                'sort_order' => 1,
                'is_active' => true,
            ]
        );
        $catToys->parent_id = $cats->id;
        $catToys->save();

        return [$dogs, $cats, $dogToys, $catToys];
    }
}
