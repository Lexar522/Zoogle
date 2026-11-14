<?php

namespace Tests\Feature;

use App\Models\OptionGroup;
use App\Models\OptionValue;
use App\Models\Product;
use App\Models\ProductCareArticle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogSortingTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_accepts_sort_and_per_page_and_orders_by_title_asc(): void
    {
        $root = $this->catalogRoot();

        $variantOptions = [
            [
                'option_group_id' => $root->option_group_id,
                'option_value_ids' => [$root->id],
            ],
        ];

        Product::query()->create([
            'title' => 'CatalogSort ZZZ',
            'slug' => 'catalog-sort-zzz',
            'product_type' => OptionGroup::CATALOG_PRODUCT_TYPE,
            'category_parent_value_id' => $root->id,
            'category_value_id' => null,
            'price' => 200,
            'is_available' => true,
            'short_description' => 'Z',
            'variant_options' => $variantOptions,
        ]);

        Product::query()->create([
            'title' => 'CatalogSort AAA',
            'slug' => 'catalog-sort-aaa',
            'product_type' => OptionGroup::CATALOG_PRODUCT_TYPE,
            'category_parent_value_id' => $root->id,
            'category_value_id' => null,
            'price' => 100,
            'is_available' => true,
            'short_description' => 'A',
            'variant_options' => $variantOptions,
        ]);

        $response = $this->get(route('catalog.index', [
            'category' => $root->id,
            'sort' => 'title_asc',
            'per_page' => 12,
        ]));

        $response->assertOk();
        $response->assertViewHas('listings', fn ($listings) => $listings->perPage() === 12);

        $r16 = $this->get(route('catalog.index', [
            'category' => $root->id,
            'per_page' => 16,
        ]));
        $r16->assertOk();
        $r16->assertViewHas('listings', fn ($listings) => $listings->perPage() === 16);

        $html = $response->getContent();
        $posA = strpos($html, 'CatalogSort AAA');
        $posZ = strpos($html, 'CatalogSort ZZZ');
        $this->assertNotFalse($posA);
        $this->assertNotFalse($posZ);
        $this->assertLessThan($posZ, $posA);
    }

    public function test_catalog_search_matches_title_and_ignores_description(): void
    {
        $root = $this->catalogRoot();
        $variantOptions = [
            [
                'option_group_id' => $root->option_group_id,
                'option_value_ids' => [$root->id],
            ],
        ];

        Product::query()->create([
            'title' => 'Клітка для папуги',
            'slug' => 'search-cage-'.uniqid(),
            'product_type' => OptionGroup::CATALOG_PRODUCT_TYPE,
            'category_parent_value_id' => $root->id,
            'price' => 100,
            'is_available' => true,
            'short_description' => 'Вольєр',
            'description' => 'Міцна клітка',
            'variant_options' => $variantOptions,
        ]);
        Product::query()->create([
            'title' => 'Кріль декоративний',
            'slug' => 'search-rabbit-'.uniqid(),
            'product_type' => OptionGroup::CATALOG_PRODUCT_TYPE,
            'category_parent_value_id' => $root->id,
            'price' => 200,
            'is_available' => true,
            'short_description' => 'Живе у клітці',
            'description' => 'Потрібна простора клітка',
            'variant_options' => $variantOptions,
        ]);

        $html = $this->get(route('catalog.index', ['q' => 'клітка']))->assertOk()->getContent();

        $this->assertStringContainsString('Клітка для папуги', $html);
        $this->assertStringNotContainsString('Кріль декоративний', $html);
    }

    public function test_catalog_search_finds_a_title_despite_close_misspellings(): void
    {
        $root = $this->catalogRoot();
        $variantOptions = [
            [
                'option_group_id' => $root->option_group_id,
                'option_value_ids' => [$root->id],
            ],
        ];

        Product::query()->create([
            'title' => 'Папуга піррура',
            'slug' => 'search-pirrura-'.uniqid(),
            'product_type' => OptionGroup::CATALOG_PRODUCT_TYPE,
            'category_parent_value_id' => $root->id,
            'price' => 100,
            'is_available' => true,
            'short_description' => 'Птах',
            'description' => 'Птах',
            'variant_options' => $variantOptions,
        ]);
        Product::query()->create([
            'title' => 'Корм для котів',
            'slug' => 'search-cat-food-'.uniqid(),
            'product_type' => OptionGroup::CATALOG_PRODUCT_TYPE,
            'category_parent_value_id' => $root->id,
            'price' => 80,
            'is_available' => true,
            'short_description' => 'Корм',
            'description' => 'Корм',
            'variant_options' => $variantOptions,
        ]);

        foreach (['перура', 'пірера', 'пирура'] as $query) {
            $html = $this->get(route('catalog.index', ['q' => $query]))->assertOk()->getContent();
            $this->assertStringContainsString('Папуга піррура', $html, $query);
            $this->assertStringNotContainsString('Корм для котів', $html, $query);

            $suggest = $this->getJson(route('catalog.suggest', ['q' => $query]));
            $suggest->assertOk();
            $this->assertContains('Папуга піррура', array_column($suggest->json('searches'), 'label'));
        }
    }

    public function test_search_suggest_lists_categories_products_and_articles_by_title(): void
    {
        $root = $this->catalogRoot();
        $root->forceFill(['name' => 'Клітка для птахів'])->save();

        Product::query()->create([
            'title' => 'Клітка для папуги',
            'slug' => 'suggest-cage',
            'product_type' => OptionGroup::CATALOG_PRODUCT_TYPE,
            'category_parent_value_id' => $root->id,
            'price' => 150,
            'is_available' => true,
            'description' => 'Вольєр',
            'variant_options' => [[
                'option_group_id' => $root->option_group_id,
                'option_value_ids' => [$root->id],
            ]],
        ]);
        Product::query()->create([
            'title' => 'Кріль декоративний',
            'slug' => 'suggest-rabbit',
            'product_type' => OptionGroup::CATALOG_PRODUCT_TYPE,
            'category_parent_value_id' => $root->id,
            'price' => 200,
            'is_available' => true,
            'short_description' => 'Живе у клітці',
            'description' => 'Потрібна клітка',
            'variant_options' => [[
                'option_group_id' => $root->option_group_id,
                'option_value_ids' => [$root->id],
            ]],
        ]);
        ProductCareArticle::query()->create([
            'title' => 'Клітка: як доглядати',
            'slug' => 'suggest-care-cage',
            'excerpt' => 'Як мити',
            'body' => '<p>Порада</p>',
            'is_published' => true,
            'published_at' => now(),
            'category_value_id' => $root->id,
        ]);

        $response = $this->getJson(route('catalog.suggest', ['q' => 'клітка']));
        $response->assertOk();
        $suggested = array_column($response->json('searches'), 'label');

        $this->assertContains('Клітка для папуги', $suggested);
        $this->assertNotContains('Кріль декоративний', $suggested);
        $this->assertNotContains('Клітка для птахів', $suggested);
        $this->assertNotContains('Клітка: як доглядати', $suggested);
        $this->assertSame('Клітка для папуги', $response->json('search_label'));

        OptionValue::query()->create([
            'option_group_id' => $root->option_group_id,
            'parent_id' => $root->id,
            'name' => 'Клітки / Вольєри',
            'slug' => 'suggest-cages-'.uniqid(),
            'sort_order' => 2,
            'is_active' => true,
        ]);

        $categorySuggest = $this->getJson(route('catalog.suggest', ['q' => 'клітки']));
        $categorySuggest->assertOk();
        $categoryGroup = collect($categorySuggest->json('groups'))->firstWhere('key', 'categories');
        $this->assertNotNull($categoryGroup);
        $cage = collect($categoryGroup['items'])->firstWhere('title', 'Клітки / Вольєри');
        $this->assertNotNull($cage);
        $this->assertSame('Клітка для птахів', $cage['meta']);
        $this->assertNotSame('Категорія', $cage['meta']);

        $prefix = $this->getJson(route('catalog.suggest', ['q' => 'клі']));
        $prefix->assertOk();
        $prefixTitles = array_column($prefix->json('searches'), 'label');
        $this->assertSame('клітка', $prefixTitles[0] ?? null);
        $this->assertSame('word', $prefix->json('searches.0.kind'));
        $this->assertContains('Клітка для папуги', $prefixTitles);
        $this->assertNotContains('Кріль декоративний', $prefixTitles);

        $typo = $this->getJson(route('catalog.suggest', ['q' => 'клетка']));
        $typo->assertOk();
        $this->assertSame('клітка', $typo->json('searches.0.label'));
        $this->assertSame('word', $typo->json('searches.0.kind'));

        $empty = $this->getJson(route('catalog.suggest'));
        $empty->assertOk();
        $categoryGroup = collect($empty->json('groups'))->firstWhere('key', 'categories');
        $this->assertNotNull($categoryGroup);
        $rootItem = collect($categoryGroup['items'])->firstWhere('title', 'Клітка для птахів');
        $this->assertNotNull($rootItem);
        $this->assertSame('', $rootItem['meta']);
    }

    public function test_category_tree_cache_shows_a_renamed_category(): void
    {
        $root = $this->catalogRoot();
        $root->forceFill(['name' => 'Каталог кеш'])->save();

        $this->get(route('home'))->assertOk()->assertSee('Каталог кеш', false);

        $root->forceFill(['name' => 'Каталог після зміни'])->save();

        $fresh = $this->get(route('home'));
        $fresh->assertOk();
        $fresh->assertSee('Каталог після зміни', false);
        $fresh->assertDontSee('Каталог кеш', false);
    }

    public function test_default_catalog_order_is_stable_for_one_shuffle_and_new_on_reload(): void
    {
        $root = $this->catalogRoot();
        $variantOptions = [
            [
                'option_group_id' => $root->option_group_id,
                'option_value_ids' => [$root->id],
            ],
        ];

        for ($i = 1; $i <= 18; $i++) {
            Product::query()->create([
                'title' => sprintf('ShuffleItem %02d', $i),
                'slug' => 'shuffle-item-'.$i,
                'product_type' => OptionGroup::CATALOG_PRODUCT_TYPE,
                'category_parent_value_id' => $root->id,
                'category_value_id' => null,
                'price' => 10 + $i,
                'is_available' => true,
                'short_description' => 'S',
                'published_at' => now()->subMinutes(18 - $i),
                'variant_options' => $variantOptions,
            ]);
        }

        $headers = ['X-Catalog-Fragment' => '1'];
        $base = ['category' => $root->id, 'mix' => 424242];
        $first = $this->withHeaders($headers)->get(route('catalog.index', $base + ['per_page' => 24]));
        $second = $this->withHeaders($headers)->get(route('catalog.index', $base + ['per_page' => 24]));
        $other = $this->withHeaders($headers)->get(route('catalog.index', [
            'category' => $root->id,
            'per_page' => 24,
            'mix' => 7,
        ]));
        $pageOne = $this->withHeaders($headers)->get(route('catalog.index', $base + ['per_page' => 12, 'page' => 1]));
        $pageTwo = $this->withHeaders($headers)->get(route('catalog.index', $base + ['per_page' => 12, 'page' => 2]));

        $orderA = $this->shuffleTitles($first->getContent());
        $this->assertSame($orderA, $this->shuffleTitles($second->getContent()));
        $this->assertCount(18, $orderA);
        $this->assertNotSame($orderA, $this->shuffleTitles($other->getContent()));
        $this->assertSame(
            $orderA,
            array_merge($this->shuffleTitles($pageOne->getContent()), $this->shuffleTitles($pageTwo->getContent()))
        );

        $fresh = $this->flushHeaders()->get(route('catalog.index', $base + ['per_page' => 24]));
        $fresh->assertOk();
        $this->assertNotSame($orderA, $this->shuffleTitles($fresh->getContent()));
    }

    /**
     * @return list<string>
     */
    private function shuffleTitles(string $html): array
    {
        preg_match_all('/product-card__title-text">([^<]+)/', $html, $matches);

        return $matches[1];
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
