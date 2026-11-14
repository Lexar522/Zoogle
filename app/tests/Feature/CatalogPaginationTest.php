<?php

namespace Tests\Feature;

use App\Models\OptionGroup;
use App\Models\OptionValue;
use App\Models\Product;
use App\Models\ShopCatalogPaginationSetting;
use App\Support\CatalogPageWindow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogPaginationTest extends TestCase
{
    use RefreshDatabase;

    public function test_page_window_keeps_at_least_five_numbers_around_the_current_page(): void
    {
        $this->assertSame(['start' => 1, 'end' => 5], CatalogPageWindow::range(1, 6, 5));
        $this->assertSame(['start' => 2, 'end' => 6], CatalogPageWindow::range(6, 6, 5));
        $this->assertSame(['start' => 1, 'end' => 3], CatalogPageWindow::range(2, 3, 5));
        $this->assertSame(['start' => 1, 'end' => 7], CatalogPageWindow::range(1, 10, 7));
    }

    public function test_catalog_shows_five_page_numbers_last_arrow_and_show_all(): void
    {
        $root = $this->catalogRoot();
        $this->seedProducts($root, 72);

        $response = $this->get(route('catalog.index', [
            'category' => $root->id,
            'per_page' => 12,
        ]));

        $response->assertOk();
        $html = $response->getContent();

        foreach ([1, 2, 3, 4, 5] as $page) {
            if ($page === 1) {
                $this->assertStringContainsString('aria-current="page"', $html);
                continue;
            }
            $this->assertStringContainsString('data-catalog-page="'.$page.'"', $html);
        }
        $this->assertStringContainsString('data-catalog-page="6"', $html);
        $this->assertStringContainsString('shop-pagination__last', $html);
        $this->assertStringContainsString('aria-label="Остання сторінка"', $html);
        $this->assertStringContainsString('page=6', $html);
        $this->assertStringContainsString('Показати всі', $html);

        $all = $this->get(route('catalog.index', [
            'category' => $root->id,
            'per_page' => 12,
            'all' => 1,
        ]));
        $all->assertOk();
        $allHtml = $all->getContent();
        $this->assertStringContainsString('PagItem 01', $allHtml);
        $this->assertStringContainsString('PagItem 72', $allHtml);
        $this->assertStringContainsString('По сторінках', $allHtml);
        $this->assertStringNotContainsString('data-catalog-page="2"', $allHtml);
    }

    public function test_admin_settings_change_page_size_and_can_hide_controls(): void
    {
        $root = $this->catalogRoot();
        $this->seedProducts($root, 40);

        ShopCatalogPaginationSetting::query()->create([
            'visible_pages' => 5,
            'show_last_arrow' => false,
            'show_all_enabled' => false,
            'per_page' => 16,
            'show_all_limit' => 1000,
        ]);
        ShopCatalogPaginationSetting::forgetResolvedCache();

        $response = $this->get(route('catalog.index', [
            'category' => $root->id,
        ]));
        $response->assertOk();
        $response->assertViewHas('listings', fn ($listings) => $listings->perPage() === 16);
        $html = $response->getContent();
        $this->assertStringNotContainsString('Остання сторінка', $html);
        $this->assertStringNotContainsString('Показати всі', $html);

        $ignored = $this->get(route('catalog.index', [
            'category' => $root->id,
            'all' => 1,
        ]));
        $ignored->assertOk();
        $ignored->assertViewHas('listings', fn ($listings) => $listings->perPage() === 16 && $listings->count() === 16);
    }

    private function seedProducts(OptionValue $root, int $count): void
    {
        $variantOptions = [
            [
                'option_group_id' => $root->option_group_id,
                'option_value_ids' => [$root->id],
            ],
        ];

        for ($i = 1; $i <= $count; $i++) {
            $published = now()->subMinutes($count - $i);
            Product::query()->create([
                'title' => sprintf('PagItem %02d', $i),
                'slug' => 'pag-item-'.$i,
                'product_type' => OptionGroup::CATALOG_PRODUCT_TYPE,
                'category_parent_value_id' => $root->id,
                'category_value_id' => null,
                'price' => 100 + $i,
                'is_available' => true,
                'short_description' => 'P',
                'published_at' => $published,
                'variant_options' => $variantOptions,
            ]);
        }
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
