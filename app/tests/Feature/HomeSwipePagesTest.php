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

    /**
     * Хіти й рекомендовані: на телефоні сторінка — 4 товари (2×2), на комп'ютері — дві такі сторінки разом (8 товарів).
     * 10 товарів: телефону 3 сторінки й 3 точки, комп'ютеру — 2 сторінки, третя точка на ньому прихована.
     */
    public function test_home_lists_have_pages_of_four_on_phone_and_eight_on_desktop(): void
    {
        $this->bestsellers(10);

        $html = (string) $this->get(route('home'))->assertOk()->getContent();
        $hits = $this->between($html, 'home-hits-heading', '</section>');

        $this->assertStringContainsString('home-product-carousel--paged', $hits);
        $this->assertStringContainsString('data-desktop-group="2"', $hits);
        $this->assertSame(3, substr_count($hits, 'class="home-product-carousel__page"'));
        $this->assertSame(10, substr_count($hits, 'class="home-product-carousel__cell"'));
        $this->assertSame(3, substr_count($hits, 'data-carousel-page-dot='));
        $this->assertSame(1, substr_count($hits, 'data-desktop-extra'), 'Третя точка — лише для телефона');
        $this->assertStringNotContainsString('home-product-carousel__dots--phone-only', $hits);
        $this->assertStringContainsString('data-carousel-page-next', $hits);
        $firstPage = explode('class="home-product-carousel__page"', $hits)[1];
        $this->assertStringContainsString('Хіт 4', $firstPage);
        $this->assertStringNotContainsString('Хіт 5', $firstPage);
    }

    public function test_five_to_eight_products_page_only_on_phone(): void
    {
        $this->bestsellers(6);

        $hits = $this->between((string) $this->get(route('home'))->getContent(), 'home-hits-heading', '</section>');

        $this->assertSame(2, substr_count($hits, 'class="home-product-carousel__page"'));
        $this->assertStringContainsString('home-product-carousel__dots--phone-only', $hits);
    }

    public function test_four_or_fewer_products_have_no_page_dots(): void
    {
        $this->bestsellers(4);

        $hits = $this->between((string) $this->get(route('home'))->getContent(), 'home-hits-heading', '</section>');

        $this->assertSame(1, substr_count($hits, 'class="home-product-carousel__page"'));
        $this->assertStringNotContainsString('data-carousel-page-dot', $hits);
        $this->assertStringNotContainsString('data-carousel-page-next', $hits);
    }

    private function bestsellers(int $count): void
    {
        foreach (range(1, $count) as $i) {
            ShopHomeListItem::query()->create([
                'list' => ShopHomeListItem::LIST_BESTSELLERS,
                'product_id' => $this->product("Хіт {$i}")->id,
                'sort_order' => $i,
            ]);
        }
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
