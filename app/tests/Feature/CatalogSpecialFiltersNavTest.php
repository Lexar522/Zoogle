<?php

namespace Tests\Feature;

use App\Models\OptionGroup;
use App\Models\OptionValue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogSpecialFiltersNavTest extends TestCase
{
    use RefreshDatabase;

    public function test_category_links_leave_promotions_and_bundles_mode(): void
    {
        $group = OptionGroup::query()->firstOrCreate(
            ['product_type' => OptionGroup::CATALOG_PRODUCT_TYPE, 'slug' => 'category'],
            ['name' => 'Категорія', 'selection_mode' => 'single', 'value_type' => 'text', 'is_active' => true]
        );
        $category = OptionValue::query()->create([
            'option_group_id' => $group->id,
            'name' => 'Тварини',
            'slug' => 'animals-nav',
            'sort_order' => 1,
            'is_active' => true,
        ]);
        $plainLink = 'href="'.e(route('catalog.index', ['category' => $category->id])).'"';

        foreach (['on_sale', 'bundles'] as $mode) {
            $this->get(route('catalog.index', [$mode => 1]))
                ->assertOk()
                ->assertSee($plainLink, false)
                ->assertDontSee(e(route('catalog.index', [$mode => 1, 'category' => $category->id])), false);
        }
    }

    public function test_promo_link_ignores_current_category(): void
    {
        $group = OptionGroup::query()->firstOrCreate(
            ['product_type' => OptionGroup::CATALOG_PRODUCT_TYPE, 'slug' => 'category'],
            ['name' => 'Категорія', 'selection_mode' => 'single', 'value_type' => 'text', 'is_active' => true]
        );
        $category = OptionValue::query()->create([
            'option_group_id' => $group->id,
            'name' => 'Тварини',
            'slug' => 'animals-promo',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $this->get(route('catalog.index', ['category' => $category->id]))
            ->assertOk()
            ->assertSee('href="'.e(route('catalog.index', ['on_sale' => 1])).'"', false)
            ->assertDontSee(e(route('catalog.index', ['category' => $category->id, 'on_sale' => 1])), false);
    }

    public function test_random_sort_prefixes_category_title_with_recommended(): void
    {
        $group = OptionGroup::query()->firstOrCreate(
            ['product_type' => OptionGroup::CATALOG_PRODUCT_TYPE, 'slug' => 'category'],
            ['name' => 'Категорія', 'selection_mode' => 'single', 'value_type' => 'text', 'is_active' => true]
        );
        $category = OptionValue::query()->create([
            'option_group_id' => $group->id,
            'name' => 'Гризуни',
            'slug' => 'rodents-title',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $this->get(route('catalog.index', ['category' => $category->id]))
            ->assertOk()
            ->assertSee('catalog-toolbar__title-label">Рекомендовані</span>', false)
            ->assertSee('<h1 class="catalog-toolbar__title">Гризуни</h1>', false);

        $this->get(route('catalog.index', ['category' => $category->id, 'sort' => 'price_asc']))
            ->assertOk()
            ->assertSee('<h1 class="catalog-toolbar__title">Гризуни</h1>', false)
            ->assertDontSee('catalog-toolbar__title-label', false);
    }
}
