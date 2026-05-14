<?php

namespace Tests\Feature;

use App\Models\OptionGroup;
use App\Models\OptionValue;
use App\Models\Product;
use App\Models\ProductCareArticle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductCareArticleTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_page_shows_care_button_when_published_articles_exist(): void
    {
        $product = $this->makeProduct();
        ProductCareArticle::query()->create([
            'product_id' => $product->id,
            'title' => 'Догляд за собакою',
            'slug' => 'dog-care',
            'excerpt' => 'Коротко про догляд',
            'body' => '<p>Порада</p>',
            'is_published' => true,
        ]);

        $this->get(route('catalog.show', $product->slug))
            ->assertOk()
            ->assertSee('Поради по догляду')
            ->assertSee(route('catalog.care.index', $product->slug), false);
    }

    public function test_care_article_page_embeds_youtube_links_and_removes_raw_iframes(): void
    {
        $product = $this->makeProduct();
        $article = ProductCareArticle::query()->create([
            'product_id' => $product->id,
            'title' => 'Відеопорада',
            'slug' => 'video-care',
            'body' => '<p><a href="https://www.youtube.com/watch?v=dQw4w9WgXcQ">відео</a></p><iframe src="https://evil.test"></iframe>',
            'is_published' => true,
        ]);

        $this->get(route('catalog.care.show', [$product->slug, $article->slug]))
            ->assertOk()
            ->assertSee('https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ', false)
            ->assertDontSee('https://evil.test', false);
    }

    private function makeProduct(): Product
    {
        $categoryGroup = OptionGroup::query()->firstOrCreate([
            'slug' => 'category',
            'product_type' => OptionGroup::CATALOG_PRODUCT_TYPE,
        ], [
            'name' => 'Категорія',
            'selection_mode' => 'single',
            'value_type' => 'text',
            'is_active' => true,
        ]);

        $root = OptionValue::query()->firstOrCreate([
            'option_group_id' => $categoryGroup->id,
            'slug' => 'dogs',
        ], [
            'name' => 'Собаки',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        return Product::query()->create([
            'title' => 'Товар для собак',
            'slug' => 'dog-product',
            'product_type' => OptionGroup::CATALOG_PRODUCT_TYPE,
            'category_parent_value_id' => $root->id,
            'price' => 100,
            'is_available' => true,
            'published_at' => now(),
        ]);
    }
}
