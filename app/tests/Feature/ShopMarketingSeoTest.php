<?php

namespace Tests\Feature;

use App\Models\OptionGroup;
use App\Models\OptionValue;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ShopMarketingSeoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    public function test_product_page_is_indexable_even_if_old_env_disables_it(): void
    {
        $this->assertTrue(config('shop.seo_index_product_pages'));

        $product = $this->product();

        $this->get(route('catalog.show', $product->slug))
            ->assertOk()
            ->assertSee('<meta name="robots" content="index,follow">', false)
            ->assertSee('"@type":"Offer"', false)
            ->assertSee('"price":"900.00"', false)
            ->assertSee('product:price:amount', false);
    }

    public function test_product_meta_description_keeps_price_and_skips_bare_category(): void
    {
        $product = $this->product();
        $product->update(['description' => '<p>'.str_repeat('Дуже довгий опис клітки. ', 20).'</p>']);

        $html = (string) $this->get(route('catalog.show', $product->slug))->assertOk()->getContent();
        preg_match('/<meta name="description" content="([^"]*)"/u', $html, $match);
        $description = html_entity_decode($match[1] ?? '', ENT_QUOTES);

        $this->assertStringStartsWith('Дуже довгий опис', $description);
        $this->assertStringEndsWith('від 900 ₴', $description);
        $this->assertLessThanOrEqual(160, mb_strlen($description));
        $this->assertStringContainsString('<meta property="product:retailer_item_id" content="'.$product->id.'">', $html);
    }

    public function test_new_product_appears_in_sitemap_with_image(): void
    {
        $product = $this->product();

        $this->get(route('seo.sitemap'))
            ->assertOk()
            ->assertSee(route('catalog.show', $product->slug), false)
            ->assertSee('<image:loc>', false);
    }

    public function test_merchant_feed_lists_products_with_price_and_stock(): void
    {
        $product = $this->product();
        $withoutPhoto = $this->product(photo: false);

        $response = $this->get(route('seo.merchant-feed'));

        $response->assertOk()->assertHeader('Content-Type', 'application/xml; charset=UTF-8');
        $xml = simplexml_load_string((string) $response->getContent());
        $this->assertNotFalse($xml);

        $items = $xml->channel->item;
        $this->assertCount(1, $items);
        $g = $items[0]->children('http://base.google.com/ns/1.0');
        $this->assertSame((string) $product->id, (string) $g->id);
        $this->assertSame('900.00 UAH', (string) $g->price);
        $this->assertSame('in_stock', (string) $g->availability);
        $this->assertSame('Клітка & корм', (string) $g->title);
        $this->assertSame('Опис товару', (string) $g->description);
        $this->assertSame('ZOOGLE', (string) $g->brand);
        $this->assertStringNotContainsString((string) $withoutPhoto->slug, (string) $response->getContent());
    }

    public function test_merchant_feed_has_category_and_extra_photos_for_shopping_tab(): void
    {
        $product = $this->product();
        $child = OptionValue::query()->create([
            'option_group_id' => OptionValue::query()->findOrFail($product->category_parent_value_id)->option_group_id,
            'parent_id' => $product->category_parent_value_id,
            'slug' => 'seo-child',
            'name' => 'Для гризунів',
            'sort_order' => 1,
            'is_active' => true,
        ]);
        $extra = UploadedFile::fake()->image('cage-2.jpg', 800, 800)->store('products/seo', 'public');
        $product->update(['category_value_id' => $child->id, 'photos' => [...$product->photos, $extra]]);

        $xml = simplexml_load_string((string) $this->get(route('seo.merchant-feed'))->getContent());
        $g = $xml->channel->item[0]->children('http://base.google.com/ns/1.0');

        $this->assertSame('2', (string) $g->google_product_category, 'Pet Supplies, не загальна категорія з живими тваринами');
        $this->assertSame('Клітки > Для гризунів', (string) $g->product_type);
        $this->assertCount(1, $g->additional_image_link);
        $this->assertStringContainsString(basename($extra), (string) $g->additional_image_link);
    }

    public function test_merchant_feed_skips_categories_excluded_from_google(): void
    {
        $kept = $this->product();
        $animal = $this->product();
        $group = OptionValue::query()->findOrFail($kept->category_parent_value_id)->option_group_id;
        $animalsRoot = OptionValue::query()->create([
            'option_group_id' => $group,
            'slug' => 'seo-animals',
            'name' => 'Тварини',
            'sort_order' => 2,
            'is_active' => true,
            'exclude_from_merchant_feed' => true,
        ]);
        $rabbits = OptionValue::query()->create([
            'option_group_id' => $group,
            'parent_id' => $animalsRoot->id,
            'slug' => 'seo-rabbits',
            'name' => 'Кролики',
            'sort_order' => 1,
            'is_active' => true,
        ]);
        $animal->update(['category_parent_value_id' => $animalsRoot->id, 'category_value_id' => $rabbits->id]);

        $content = (string) $this->get(route('seo.merchant-feed'))->getContent();
        $this->assertStringContainsString((string) $kept->slug, $content);
        $this->assertStringNotContainsString((string) $animal->slug, $content);
    }

    public function test_missing_page_shows_branded_error(): void
    {
        $this->get('/no-such-page')
            ->assertNotFound()
            ->assertSee('Сторінку не знайдено')
            ->assertSee('До каталогу');
    }

    public function test_marketing_tags_are_off_without_ids(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertDontSee('data-cookie-banner', false)
            ->assertDontSee('googletagmanager.com/gtag', false);
    }

    public function test_marketing_tags_wait_for_consent_when_configured(): void
    {
        config(['shop.marketing.ga4_id' => 'G-TEST123', 'shop.marketing.meta_pixel_id' => '12345']);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('data-cookie-banner', false)
            ->assertSee('G-TEST123', false)
            ->assertDontSee('<script async src="https://www.googletagmanager.com', false);

        $this->get(route('privacy'))->assertSee('Google Analytics', false);
    }

    public function test_invalid_marketing_id_is_ignored(): void
    {
        config(['shop.marketing.ga4_id' => '"><script>alert(1)</script>']);

        $this->get(route('home'))->assertOk()->assertDontSee('alert(1)', false);
    }

    public function test_static_robots_file_lists_sitemap(): void
    {
        $robots = (string) file_get_contents(public_path('robots.txt'));

        $this->assertStringContainsString('Sitemap: https://zoogle.in.ua/sitemap.xml', $robots);
        $this->assertStringNotContainsString('Disallow: /catalog/', $robots);
    }

    private function product(bool $photo = true): Product
    {
        $group = OptionGroup::query()->firstOrCreate(
            ['product_type' => OptionGroup::CATALOG_PRODUCT_TYPE, 'slug' => 'category'],
            ['name' => 'Категорія', 'selection_mode' => 'single', 'value_type' => 'text', 'is_active' => true]
        );
        $category = OptionValue::query()->firstOrCreate(
            ['option_group_id' => $group->id, 'slug' => 'seo-root'],
            ['name' => 'Клітки', 'sort_order' => 1, 'is_active' => true]
        );
        $photos = [];
        if ($photo) {
            $photos[] = UploadedFile::fake()->image('cage.jpg', 800, 800)->store('products/seo', 'public');
        }

        return Product::query()->create([
            'title' => 'Клітка & корм',
            'slug' => 'seo-product-'.uniqid(),
            'description' => '<p>Опис <b>товару</b></p>',
            'product_type' => OptionGroup::CATALOG_PRODUCT_TYPE,
            'category_parent_value_id' => $category->id,
            'category_value_id' => $category->id,
            'price' => 900,
            'is_available' => true,
            'stock_status' => Product::STOCK_IN_STOCK,
            'photos' => $photos,
            'variant_options' => [
                ['option_group_id' => $group->id, 'option_value_ids' => [$category->id]],
            ],
        ]);
    }
}
