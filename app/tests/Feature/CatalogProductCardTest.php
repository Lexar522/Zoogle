<?php

namespace Tests\Feature;

use App\Models\Bundle;
use App\Models\BundleItem;
use App\Models\OptionGroup;
use App\Models\OptionValue;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CatalogProductCardTest extends TestCase
{
    use RefreshDatabase;

    public function test_card_price_drops_zero_kopecks_and_keeps_real_ones(): void
    {
        $root = $this->catalogRoot();
        $this->product($root, 'Керамічна миска', 'ceramic-bowl', 310);
        $this->product($root, 'Поїлка', 'drinker', 249.5);

        $html = $this->catalogHtml($root);

        $this->assertStringContainsString('product-card__price-amount">310</span>', $html);
        $this->assertStringNotContainsString('310,00', $html);
        $this->assertStringContainsString('product-card__price-amount">249,50</span>', $html);
    }

    public function test_card_shows_color_dots_for_active_colors(): void
    {
        $root = $this->catalogRoot();
        $colors = OptionGroup::query()->create([
            'product_type' => OptionGroup::CATALOG_PRODUCT_TYPE,
            'name' => 'Колір',
            'slug' => 'card-color',
            'selection_mode' => 'single',
            'value_type' => 'color',
            'is_active' => true,
        ]);
        $coral = $this->color($colors, 'Кораловий', '#f0997b', 1);
        $mint = $this->color($colors, "М'ятний", '#5dcaa5', 2);
        $hidden = $this->color($colors, 'Сірий', '#d3d1c7', 3, active: false);

        $this->product($root, 'Керамічна миска', 'ceramic-bowl', 310, [
            ['option_group_id' => $colors->id, 'option_value_ids' => [$coral->id, $mint->id, $hidden->id]],
        ]);

        $html = $this->catalogHtml($root);

        $this->assertSame(2, substr_count($html, 'class="product-card__color-dot"'));
        $this->assertStringContainsString('--dot: #f0997b', $html);
        $this->assertStringContainsString('--dot: #5dcaa5', $html);
        $this->assertStringNotContainsString('#d3d1c7', $html);
        $this->assertStringContainsString(trans_choice('shop.card_colors_count', 2), $html);
    }

    public function test_card_hides_zero_like_count_and_draws_outline_heart(): void
    {
        $root = $this->catalogRoot();
        $this->product($root, 'Керамічна миска', 'ceramic-bowl', 310);

        $html = $this->catalogHtml($root);

        $this->assertMatchesRegularExpression('/data-product-like-count\s+hidden\s*>0</', $html);
        $this->assertMatchesRegularExpression('/class="product-card__heart-icon".*?<path[^>]*fill="none"/s', $html);
    }

    public function test_color_dot_carries_photo_of_that_color(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('products/bowl.jpg', 'bowl');
        Storage::disk('public')->put('products/options/bowl-coral.jpg', 'coral');

        $root = $this->catalogRoot();
        $colors = OptionGroup::query()->create([
            'product_type' => OptionGroup::CATALOG_PRODUCT_TYPE,
            'name' => 'Колір',
            'slug' => 'card-color',
            'selection_mode' => 'single',
            'value_type' => 'color',
            'is_active' => true,
        ]);
        $coral = $this->color($colors, 'Кораловий', '#f0997b', 1);
        $mint = $this->color($colors, "М'ятний", '#5dcaa5', 2);

        $product = $this->product($root, 'Керамічна миска', 'ceramic-bowl', 310, [[
            'option_group_id' => $colors->id,
            'option_value_ids' => [$coral->id, $mint->id],
            'value_photos' => [['option_value_id' => $coral->id, 'photos' => ['products/options/bowl-coral.jpg']]],
        ]]);
        $product->forceFill(['photos' => ['products/bowl.jpg']])->save();

        $html = $this->catalogHtml($root);

        $this->assertMatchesRegularExpression('#--dot: \#f0997b"[^>]*data-card-photo="[^"]*products/options/bowl-coral\.jpg"#', $html);
        $this->assertDoesNotMatchRegularExpression('#--dot: \#5dcaa5"[^>]*data-card-photo=#', $html);
    }

    public function test_card_has_favorite_on_photo_and_labelled_cart_button(): void
    {
        $root = $this->catalogRoot();
        $this->product($root, 'Керамічна миска', 'ceramic-bowl', 310);

        $html = $this->catalogHtml($root);

        $this->assertLessThan(
            strpos($html, 'class="product-card__body"'),
            strpos($html, 'class="product-card__like-react'),
        );
        $this->assertStringContainsString('<span class="product-card__cart-label">'.__('shop.card_add_cart').'</span>', $html);
    }

    public function test_bundle_card_shows_item_count_and_single_cart_button(): void
    {
        $root = $this->catalogRoot();
        $food = $this->product($root, 'Корм', 'food', 100);
        $hay = $this->product($root, 'Сіно', 'hay', 50);
        $bundle = Bundle::query()->create([
            'title' => 'Набір кормів для кроликів',
            'slug' => 'rabbit-food-set',
            'is_visible' => true,
            'is_active' => true,
        ]);
        foreach ([$food, $hay] as $i => $item) {
            BundleItem::query()->create(['bundle_id' => $bundle->id, 'product_id' => $item->id, 'qty' => 1, 'sort_order' => $i]);
        }

        $html = (string) $this->get(route('catalog.index', ['bundles' => 1]))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('class="product-card__meta-text">'.trans_choice('shop.card_bundle_items_count', 2).'<', $html);
        $this->assertStringNotContainsString('product-card__btn--icon', $html);
        $this->assertStringNotContainsString('product-card__eyebrow', $html);
    }

    public function test_default_listing_heading_reads_recommended_category(): void
    {
        $root = $this->catalogRoot();
        $this->product($root, 'Поїлка', 'drinker', 100);

        $html = $this->catalogHtml($root);
        $this->assertMatchesRegularExpression(
            '#catalog-toolbar__title-label">Рекомендовані</span>\s*<span class="catalog-toolbar__title catalog-toolbar__title-sep" aria-hidden="true">/</span>\s*<h1 class="catalog-toolbar__title">Каталог</h1>#u',
            $html
        );
        $this->assertStringNotContainsString('>Радимо<', $html);

        $sorted = (string) $this->get(route('catalog.index', ['category' => $root->id, 'sort' => 'price_asc']))->getContent();
        $this->assertStringNotContainsString('catalog-toolbar__title-label', $sorted);
    }

    private function catalogHtml(OptionValue $root): string
    {
        return (string) $this->get(route('catalog.index', ['category' => $root->id]))
            ->assertOk()
            ->getContent();
    }

    /**
     * @param  list<array<string, mixed>>  $extraOptions
     */
    private function product(OptionValue $root, string $title, string $slug, float $price, array $extraOptions = []): Product
    {
        return Product::query()->create([
            'title' => $title,
            'slug' => $slug,
            'product_type' => OptionGroup::CATALOG_PRODUCT_TYPE,
            'category_parent_value_id' => $root->id,
            'category_value_id' => null,
            'price' => $price,
            'is_available' => true,
            'variant_options' => array_merge([
                ['option_group_id' => $root->option_group_id, 'option_value_ids' => [$root->id]],
            ], $extraOptions),
        ]);
    }

    private function color(OptionGroup $group, string $name, string $hex, int $sort, bool $active = true): OptionValue
    {
        return OptionValue::query()->create([
            'option_group_id' => $group->id,
            'name' => $name,
            'slug' => 'card-color-'.$sort,
            'color_hex' => $hex,
            'sort_order' => $sort,
            'is_active' => $active,
        ]);
    }

    private function catalogRoot(): OptionValue
    {
        $categoryGroup = OptionGroup::query()->firstOrCreate(
            ['product_type' => OptionGroup::CATALOG_PRODUCT_TYPE, 'slug' => 'category'],
            ['name' => 'Категорія', 'selection_mode' => 'single', 'value_type' => 'text', 'is_active' => true]
        );

        return OptionValue::query()->firstOrCreate(
            ['option_group_id' => $categoryGroup->id, 'slug' => 'catalog-root'],
            ['name' => 'Каталог', 'sort_order' => 1, 'is_active' => true]
        );
    }
}
