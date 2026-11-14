<?php

namespace Tests\Feature;

use App\Models\Bundle;
use App\Models\BundleItem;
use App\Models\OptionGroup;
use App\Models\OptionValue;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Support\StorefrontMemo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Кількість запитів сторінки каталогу не має рости разом із кількістю карток:
 * раніше кожна картка робила окремі запити (схема таблиць, варіанти, опції, акції).
 */
class StorefrontQueryBudgetTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_category_queries_do_not_grow_with_cards(): void
    {
        [$root, $size] = $this->catalogFixtures();
        $this->makeProducts($root, $size, 2);
        $few = $this->countQueries(route('catalog.index', ['category' => $root->id, 'per_page' => 24]));

        $this->makeProducts($root, $size, 10, offset: 2);
        $many = $this->countQueries(route('catalog.index', ['category' => $root->id, 'per_page' => 24]));

        $this->assertLessThanOrEqual($few + 2, $many, "Каталог: {$few} запитів на 2 картки, {$many} на 12.");
    }

    public function test_bundle_listing_queries_do_not_grow_with_bundles(): void
    {
        [$root, $size] = $this->catalogFixtures();
        $products = $this->makeProducts($root, $size, 3);
        $this->makeBundles($root, $products, 2);
        $few = $this->countQueries(route('catalog.index', ['bundles' => 1]));

        $this->makeBundles($root, $products, 8, offset: 2);
        $many = $this->countQueries(route('catalog.index', ['bundles' => 1]));

        $this->assertLessThanOrEqual($few + 2, $many, "Комплекти: {$few} запитів на 2 картки, {$many} на 10.");
    }

    private function countQueries(string $url): int
    {
        StorefrontMemo::flush();
        $count = 0;
        DB::listen(function () use (&$count): void {
            $count++;
        });

        $this->get($url)->assertOk();

        DB::getEventDispatcher()->forget(\Illuminate\Database\Events\QueryExecuted::class);

        return $count;
    }

    /**
     * @return array{OptionValue, OptionValue}
     */
    private function catalogFixtures(): array
    {
        $categoryGroup = OptionGroup::query()->firstOrCreate(
            ['product_type' => OptionGroup::CATALOG_PRODUCT_TYPE, 'slug' => 'category'],
            ['name' => 'Категорія', 'selection_mode' => 'single', 'value_type' => 'text', 'is_active' => true],
        );
        $root = OptionValue::query()->create([
            'option_group_id' => $categoryGroup->id,
            'name' => 'Корм',
            'slug' => 'budget-root',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $sizeGroup = OptionGroup::query()->create([
            'product_type' => OptionGroup::CATALOG_PRODUCT_TYPE,
            'name' => 'Розмір',
            'slug' => 'budget-size',
            'selection_mode' => 'single',
            'value_type' => 'text',
            'is_active' => true,
        ]);
        $size = OptionValue::query()->create([
            'option_group_id' => $sizeGroup->id,
            'name' => 'M',
            'slug' => 'budget-size-m',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        return [$root, $size];
    }

    /**
     * @return list<Product>
     */
    private function makeProducts(OptionValue $root, OptionValue $size, int $count, int $offset = 0): array
    {
        $products = [];
        for ($i = $offset; $i < $offset + $count; $i++) {
            $product = Product::query()->create([
                'title' => 'Бюджет '.$i,
                'slug' => 'budget-'.$i,
                'product_type' => OptionGroup::CATALOG_PRODUCT_TYPE,
                'category_parent_value_id' => $root->id,
                'category_value_id' => null,
                'price' => 100 + $i,
                'is_available' => true,
                'variant_options' => [
                    ['option_group_id' => $root->option_group_id, 'option_value_ids' => [$root->id]],
                    ['option_group_id' => $size->option_group_id, 'option_value_ids' => [$size->id]],
                ],
            ]);
            ProductVariant::query()->create([
                'product_id' => $product->id,
                'price' => 100 + $i,
                'quantity' => 5,
                'is_available' => true,
                'is_low_stock' => false,
                'is_visible' => true,
                'allows_preorder' => false,
                'is_sold' => false,
                'sort_order' => 1,
                'options' => [['option_group_id' => $size->option_group_id, 'option_value_id' => $size->id]],
            ]);
            $products[] = $product;
        }

        return $products;
    }

    /**
     * @param  list<Product>  $products
     */
    private function makeBundles(OptionValue $root, array $products, int $count, int $offset = 0): void
    {
        for ($i = $offset; $i < $offset + $count; $i++) {
            $bundle = Bundle::query()->create([
                'title' => 'Комплект '.$i,
                'slug' => 'budget-bundle-'.$i,
                'category_parent_value_id' => $root->id,
                'category_value_id' => null,
                'is_visible' => true,
                'is_active' => true,
            ]);
            foreach ($products as $n => $product) {
                BundleItem::query()->create([
                    'bundle_id' => $bundle->id,
                    'product_id' => $product->id,
                    'qty' => 1,
                    'sort_order' => $n + 1,
                ]);
            }
        }
    }
}
