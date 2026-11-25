<?php

namespace Tests\Feature;

use App\Models\OptionGroup;
use App\Models\OptionValue;
use App\Models\Product;
use App\Models\ShopHomeListItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomeSwipePagesTest extends TestCase
{
    use RefreshDatabase;

    /** Хіти й рекомендовані: по 8 товарів на сторінку (телефон і комп'ютер), далі — стрілки й точки. */
    public function test_home_lists_are_split_into_pages_of_eight_with_dots(): void
    {
        foreach (range(1, 10) as $i) {
            ShopHomeListItem::query()->create([
                'list' => ShopHomeListItem::LIST_BESTSELLERS,
                'product_id' => $this->product("Хіт {$i}")->id,
                'sort_order' => $i,
            ]);
        }

        $html = (string) $this->get(route('home'))->assertOk()->getContent();
        $hits = $this->between($html, 'home-hits-heading', '</section>');

        $this->assertStringContainsString('home-product-carousel--paged', $hits);
        $this->assertSame(2, substr_count($hits, 'class="home-product-carousel__page"'));
        $this->assertSame(10, substr_count($hits, 'class="home-product-carousel__cell"'));
        $this->assertSame(2, substr_count($hits, 'data-carousel-page-dot'));
        $this->assertStringContainsString('data-carousel-page-prev', $hits);
        $this->assertStringContainsString('data-carousel-page-next', $hits);
        $firstPage = explode('class="home-product-carousel__page"', $hits)[1];
        $this->assertStringContainsString('Хіт 8', $firstPage);
        $this->assertStringNotContainsString('Хіт 9', $firstPage);
    }

    public function test_eight_or_fewer_products_have_no_page_dots(): void
    {
        foreach (range(1, 8) as $i) {
            ShopHomeListItem::query()->create([
                'list' => ShopHomeListItem::LIST_BESTSELLERS,
                'product_id' => $this->product("Хіт {$i}")->id,
                'sort_order' => $i,
            ]);
        }

        $hits = $this->between((string) $this->get(route('home'))->getContent(), 'home-hits-heading', '</section>');

        $this->assertSame(1, substr_count($hits, 'class="home-product-carousel__page"'));
        $this->assertStringNotContainsString('data-carousel-page-dot', $hits);
        $this->assertStringNotContainsString('data-carousel-page-next', $hits);
    }

    public function test_product_page_related_items_use_the_same_swipe_pages(): void
    {
        $main = $this->product('Основний');
        foreach (range(1, 5) as $i) {
            $main->recommendedProducts()->attach($this->product("Супутній {$i}")->id, ['sort_order' => $i]);
        }

        $html = (string) $this->get(route('catalog.show', $main->slug))->assertOk()->getContent();
        $related = $this->between($html, 'pdp-related-heading', '</section>');

        $this->assertSame(2, substr_count($related, 'class="home-product-carousel__page"'));
        $this->assertSame(5, substr_count($related, 'class="home-product-carousel__cell"'));
        $this->assertStringContainsString('data-carousel-page-next', $related);
    }

    private function product(string $title): Product
    {
        $group = OptionGroup::query()->firstOrCreate(
            ['product_type' => OptionGroup::CATALOG_PRODUCT_TYPE, 'slug' => 'category'],
            ['name' => 'Категорія', 'selection_mode' => 'single', 'value_type' => 'text', 'is_active' => true]
        );
        $category = OptionValue::query()->firstOrCreate(
            ['option_group_id' => $group->id, 'slug' => 'home-swipe'],
            ['name' => 'Іграшки', 'sort_order' => 1, 'is_active' => true]
        );

        return Product::query()->create([
            'title' => $title,
            'slug' => 'home-swipe-'.uniqid(),
            'product_type' => OptionGroup::CATALOG_PRODUCT_TYPE,
            'category_parent_value_id' => $category->id,
            'category_value_id' => $category->id,
            'price' => 100,
            'is_available' => true,
            'stock_status' => Product::STOCK_IN_STOCK,
            'variant_options' => [
                ['option_group_id' => $group->id, 'option_value_ids' => [$category->id]],
            ],
        ]);
    }

    private function between(string $html, string $start, string $end): string
    {
        $from = strpos($html, $start);
        $this->assertNotFalse($from);
        $to = strpos($html, $end, $from);

        return substr($html, $from, $to === false ? null : $to - $from);
    }
}
