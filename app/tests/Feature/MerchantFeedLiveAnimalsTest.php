<?php

namespace Tests\Feature;

use App\Models\OptionGroup;
use App\Models\OptionValue;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Живі тварини й живий корм не йдуть у Google Merchant, але лишаються на сайті й у sitemap.
 * Разом із цим — розмітка, що її читає звіт «Картки товарів» у Search Console.
 */
class MerchantFeedLiveAnimalsTest extends TestCase
{
    use RefreshDatabase;

    private OptionGroup $categoryGroup;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->categoryGroup = OptionGroup::query()->firstOrCreate(
            ['product_type' => OptionGroup::CATALOG_PRODUCT_TYPE, 'slug' => 'category'],
            ['name' => 'Категорія', 'selection_mode' => 'single', 'value_type' => 'text', 'is_active' => true]
        );
    }

    public function test_subcategory_flag_hides_live_food_from_feed_but_keeps_it_in_sitemap(): void
    {
        $food = $this->category('Корм');
        $liveFood = $this->category('Живий корм', $food, excluded: true);
        $dryFood = $this->category('Сухий корм', $food);
        $worms = $this->product('Борошняний черв\'як', $food, $liveFood);
        $kibble = $this->product('Сухий корм для хом\'яків', $food, $dryFood);

        $this->assertTrue($liveFood->fresh()->exclude_from_merchant_feed, 'Позначка підкатегорії має зберігатися');

        $feed = (string) $this->get(route('seo.merchant-feed'))->assertOk()->getContent();
        $this->assertStringContainsString($kibble->slug, $feed);
        $this->assertStringNotContainsString($worms->slug, $feed);

        $this->get(route('seo.sitemap'))
            ->assertOk()
            ->assertSee(route('catalog.show', $worms->slug), false)
            ->assertSee(route('catalog.index', ['category' => $liveFood->id]), false);
    }

    public function test_product_flag_hides_only_that_product_and_keeps_its_page_indexable(): void
    {
        $rodents = $this->category('Гризуни');
        $hamster = $this->product('Джунгарський хом\'ячок', $rodents);
        $hamster->update(['exclude_from_merchant_feed' => true]);
        $cage = $this->product('Клітка для хом\'яка', $rodents);

        $feed = (string) $this->get(route('seo.merchant-feed'))->assertOk()->getContent();
        $this->assertStringContainsString($cage->slug, $feed);
        $this->assertStringNotContainsString($hamster->slug, $feed);

        $this->get(route('catalog.show', $hamster->slug))
            ->assertOk()
            ->assertSee('<meta name="robots" content="index,follow">', false)
            ->assertSee('"@type":"Product"', false);
        $this->get(route('seo.sitemap'))->assertSee(route('catalog.show', $hamster->slug), false);
    }

    public function test_additional_category_in_excluded_branch_hides_product(): void
    {
        $cages = $this->category('Клітки');
        $animals = $this->category('Тварини', excluded: true);
        $fish = $this->category('Рибки', $animals);
        $guppy = $this->product('Гупі', $cages);
        $guppy->categories()->attach($fish->id, ['sort_order' => 1]);

        $feed = (string) $this->get(route('seo.merchant-feed'))->assertOk()->getContent();

        $this->assertStringNotContainsString($guppy->slug, $feed);
    }

    public function test_product_with_price_range_gets_single_offer_matching_feed_price(): void
    {
        $cages = $this->category('Клітки');
        $product = $this->product('Клітка з розмірами', $cages);
        $sizes = OptionGroup::query()->create(['product_type' => OptionGroup::CATALOG_PRODUCT_TYPE, 'name' => 'Розмір', 'slug' => 'seo-size', 'selection_mode' => 'single', 'value_type' => 'text', 'is_active' => true]);
        foreach ([['S', 500], ['L', 800]] as $index => [$name, $price]) {
            $size = OptionValue::query()->create(['option_group_id' => $sizes->id, 'name' => $name, 'slug' => 'seo-size-'.$name, 'sort_order' => $index, 'is_active' => true]);
            ProductVariant::query()->create([
                'product_id' => $product->id,
                'price' => $price,
                'quantity' => 3,
                'is_available' => true,
                'is_low_stock' => false,
                'is_visible' => true,
                'allows_preorder' => false,
                'is_sold' => false,
                'sort_order' => $index,
                'options' => [['option_group_id' => $sizes->id, 'option_value_id' => $size->id]],
            ]);
        }

        $html = (string) $this->get(route('catalog.show', $product->slug))->assertOk()->getContent();
        $schema = $this->jsonLd($html, 'Product');

        $this->assertSame('Offer', $schema['offers']['@type']);
        $this->assertSame('500.00', $schema['offers']['price']);
        $this->assertSame('UAH', $schema['offers']['priceCurrency']);
        $this->assertSame('https://schema.org/InStock', $schema['offers']['availability']);
        $this->assertSame(route('catalog.show', $product->slug), $schema['offers']['url']);
        $this->assertNotEmpty($schema['image']);
        $this->assertNotEmpty($schema['sku']);

        $xml = simplexml_load_string((string) $this->get(route('seo.merchant-feed'))->getContent());
        $this->assertSame('500.00 UAH', (string) $xml->channel->item[0]->children('http://base.google.com/ns/1.0')->price);
    }

    public function test_category_page_has_breadcrumbs_single_h1_and_clean_canonical(): void
    {
        $rodents = $this->category('Гризуни');
        $rabbits = $this->category('Кролики', $rodents);
        $this->product('Сіно для кроликів', $rodents, $rabbits);
        $clean = route('catalog.index', ['category' => $rabbits->id]);

        $html = (string) $this->get($clean)->assertOk()->getContent();
        $crumbs = $this->jsonLd($html, 'BreadcrumbList')['itemListElement'];
        $this->assertSame(['ZOOGLE', 'Гризуни', 'Кролики'], array_column($crumbs, 'name'));
        $this->assertSame($clean, $crumbs[2]['item']);

        foreach ([['sort' => 'price_asc'], ['page' => 2], ['sort' => 'price_desc', 'page' => 2]] as $params) {
            $html = (string) $this->get(route('catalog.index', ['category' => $rabbits->id, ...$params]))->assertOk()->getContent();

            $this->assertStringContainsString('<link rel="canonical" href="'.e($clean).'">', $html);
            $this->assertStringContainsString('<meta name="robots" content="noindex,follow">', $html);
            $this->assertSame(1, substr_count($html, '<h1'), 'На сторінці каталогу має бути рівно один h1');
        }
    }

    public function test_sitemap_lists_intermediate_and_additional_categories(): void
    {
        $rodents = $this->category('Гризуни');
        $rabbits = $this->category('Кролики', $rodents);
        $lopEared = $this->category('Висловухі', $rabbits);
        $toys = $this->category('Іграшки');
        $product = $this->product('Кролик висловухий', $rodents, $lopEared);
        $product->categories()->attach($toys->id, ['sort_order' => 1]);

        $sitemap = (string) $this->get(route('seo.sitemap'))->assertOk()->getContent();

        foreach ([$rodents, $rabbits, $lopEared, $toys] as $category) {
            $this->assertStringContainsString(e(route('catalog.index', ['category' => $category->id])), $sitemap);
        }
    }

    private function category(string $name, ?OptionValue $parent = null, bool $excluded = false): OptionValue
    {
        return OptionValue::query()->create([
            'option_group_id' => $this->categoryGroup->id,
            'parent_id' => $parent?->id,
            'slug' => 'live-'.uniqid(),
            'name' => $name,
            'sort_order' => 1,
            'is_active' => true,
            'exclude_from_merchant_feed' => $excluded,
        ]);
    }

    private function product(string $title, OptionValue $root, ?OptionValue $leaf = null): Product
    {
        $leaf ??= $root;

        return Product::query()->create([
            'title' => $title,
            'slug' => 'live-product-'.uniqid(),
            'description' => '<p>Опис</p>',
            'product_type' => OptionGroup::CATALOG_PRODUCT_TYPE,
            'category_parent_value_id' => $root->id,
            'category_value_id' => $leaf->id,
            'price' => 900,
            'is_available' => true,
            'stock_status' => Product::STOCK_IN_STOCK,
            'photos' => [UploadedFile::fake()->image('photo.jpg', 800, 800)->store('products/live', 'public')],
            'variant_options' => [
                ['option_group_id' => $this->categoryGroup->id, 'option_value_ids' => [$leaf->id]],
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function jsonLd(string $html, string $type): array
    {
        preg_match_all('~<script type="application/ld\+json">(.*?)</script>~s', $html, $matches);
        foreach ($matches[1] as $json) {
            $data = json_decode(trim($json), true);
            if (is_array($data) && ($data['@type'] ?? null) === $type) {
                return $data;
            }
        }

        $this->fail('На сторінці немає JSON-LD типу '.$type);
    }
}
