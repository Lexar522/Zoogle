<?php

namespace Tests\Feature;

use App\Models\OptionGroup;
use App\Models\OptionValue;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShopSeoTest extends TestCase
{
    use RefreshDatabase;

    public function test_category_page_is_indexable_for_product_queries(): void
    {
        $category = $this->category('Клітки');
        $product = Product::query()->create([
            'title' => 'Клітка для папуги',
            'slug' => 'seo-parrot-cage',
            'product_type' => OptionGroup::CATALOG_PRODUCT_TYPE,
            'category_parent_value_id' => $category->id,
            'category_value_id' => $category->id,
            'price' => 900,
            'is_available' => true,
            'short_description' => 'Металева клітка',
            'variant_options' => [
                [
                    'option_group_id' => $category->option_group_id,
                    'option_value_ids' => [$category->id],
                ],
            ],
        ]);

        $page = $this->get(route('catalog.index', ['category' => $category->id]));
        $page->assertOk();
        $page->assertSee('<title>Клітки — купити в ZOOGLE</title>', false);
        $page->assertSee('name="description" content="Купити Клітки в зоомагазині ZOOGLE', false);
        $page->assertSee('name="robots" content="index,follow"', false);
        $page->assertSee('rel="canonical" href="'.e(route('catalog.index', ['category' => $category->id])).'"', false);
        $page->assertSee('Клітки в зоомагазині ZOOGLE', false);
        $page->assertSee('Клітка для папуги', false);
        $page->assertSee('ItemList', false);

        $this->get(route('catalog.index', ['q' => 'клітка']))
            ->assertOk()
            ->assertSee('name="robots" content="noindex,follow"', false);

        $this->get(route('catalog.index', ['category' => $category->id, 'sort' => 'price_asc']))
            ->assertOk()
            ->assertSee('name="robots" content="noindex,follow"', false);

        $productPage = $this->get(route('catalog.show', $product->slug));
        $productPage->assertOk();
        $productPage->assertSee('<title>Клітка для папуги — ZOOGLE</title>', false);
        $productPage->assertSee('property="og:title" content="Клітка для папуги — ZOOGLE"', false);
        $productPage->assertSee('property="og:type" content="product"', false);
        $productPage->assertSee('name="robots" content="index,follow"', false);
        $productPage->assertSee('content="Металева клітка', false);
        $productPage->assertDontSee('Клітки. Металева клітка', false);
        $productPage->assertSee('"category":"Клітки"', false);
        $productPage->assertDontSee('t.me/share/url', false);
        $productPage->assertDontSee('facebook.com/sharer', false);

        $sitemap = $this->get(route('seo.sitemap'));
        $sitemap->assertOk();
        $sitemap->assertSee(route('catalog.show', $product->slug), false);
        $sitemap->assertSee(route('catalog.index', ['category' => $category->id]), false);

        $this->get(route('seo.robots'))
            ->assertOk()
            ->assertSee('Allow: /catalog?category=', false)
            ->assertSee('Disallow: /catalog?', false);
    }

    private function category(string $name): OptionValue
    {
        $group = OptionGroup::query()->firstOrCreate(
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

        return OptionValue::query()->create([
            'option_group_id' => $group->id,
            'name' => $name,
            'slug' => 'seo-'.substr(md5($name), 0, 8),
            'sort_order' => 1,
            'is_active' => true,
        ]);
    }
}
