<?php

namespace Tests\Feature\Admin;

use App\Filament\Admin\Pages\ManageHomeProductLists;
use App\Filament\Admin\Resources\Products\Pages\ListProducts;
use App\Filament\Support\ProductAdminSelectSearch;
use App\Models\OptionGroup;
use App\Models\OptionValue;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class AdminListSpeedTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_list_uses_stored_photo_and_variant_price_without_loading_descriptions(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::query()->where('email', 'admin@sitezoo.local')->firstOrFail();

        $category = $this->category('Для папуг');
        $product = Product::query()->create([
            'title' => 'Клітка для папуги',
            'slug' => 'cage-admin-list',
            'product_type' => OptionGroup::CATALOG_PRODUCT_TYPE,
            'category_value_id' => $category->id,
            'description' => '<p>секретний-опис-якого-немає-в-списку</p>',
            'short_description' => '<p>короткий-секрет</p>',
            'price' => 999,
            'is_available' => true,
            'photos' => [],
            'variant_options' => [
                [
                    'option_group_id' => $category->option_group_id,
                    'option_value_ids' => [$category->id],
                ],
            ],
        ]);
        ProductVariant::query()->create([
            'product_id' => $product->id,
            'price' => 321,
            'quantity' => 1,
            'is_available' => true,
            'is_visible' => true,
            'is_sold' => false,
            'photos' => ['products/1/photos/variant-only.webp'],
            'sort_order' => 1,
        ]);
        DB::table('products')->where('id', $product->id)->update([
            'photos' => json_encode(['products/1/photos/cage.jpg']),
        ]);

        $this->actingAs($admin);
        DB::flushQueryLog();
        DB::enableQueryLog();

        Livewire::test(ListProducts::class)
            ->assertSuccessful()
            ->assertSee('Клітка для папуги')
            ->assertSee('Для папуг')
            ->assertSee('cage.jpg')
            ->assertSee('321')
            ->assertDontSee('секретний-опис-якого-немає-в-списку');

        $perIdLookups = collect(DB::getQueryLog())->pluck('query')->filter(function (string $sql): bool {
            return str_contains($sql, 'where "option_values"."id"');
        })->values()->all();
        $this->assertSame([], $perIdLookups);

        $listSelects = collect(DB::getQueryLog())->filter(function (array $query): bool {
            return str_contains($query['query'], 'products')
                && str_contains($query['query'], 'photos');
        });
        $this->assertNotEmpty($listSelects);
        foreach ($listSelects as $query) {
            $this->assertStringNotContainsString('description', $query['query']);
        }
    }

    public function test_list_toggle_does_not_erase_description(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::query()->where('email', 'admin@sitezoo.local')->firstOrFail();
        $category = $this->category('Гризуни');
        $product = Product::query()->create([
            'title' => 'Клітка для гризуна',
            'slug' => 'rodent-cage-admin',
            'product_type' => OptionGroup::CATALOG_PRODUCT_TYPE,
            'category_value_id' => $category->id,
            'description' => '<p>опис-має-лишитись</p>',
            'price' => 100,
            'is_available' => true,
            'variant_options' => [
                [
                    'option_group_id' => $category->option_group_id,
                    'option_value_ids' => [$category->id],
                ],
            ],
        ]);

        $this->actingAs($admin);

        Livewire::test(ListProducts::class)
            ->call('updateTableColumnState', 'is_available', (string) $product->id, false)
            ->assertHasNoErrors();

        $product->refresh();
        $this->assertFalse($product->is_available);
        $this->assertStringContainsString('опис-має-лишитись', (string) $product->description);
    }

    public function test_home_lists_and_photo_search_skip_descriptions_and_missing_files(): void
    {
        $product = Product::query()->create([
            'title' => 'Пошукова клітка',
            'slug' => 'search-cage-admin',
            'product_type' => OptionGroup::CATALOG_PRODUCT_TYPE,
            'description' => '<p>дуже-довгий-опис</p>',
            'price' => 10,
            'is_available' => true,
            'photos' => [],
        ]);
        ProductVariant::query()->create([
            'product_id' => $product->id,
            'price' => 10,
            'quantity' => 1,
            'is_available' => true,
            'is_visible' => true,
            'is_sold' => false,
            'photos' => ['products/2/photos/from-variant.jpg'],
            'sort_order' => 1,
        ]);

        DB::flushQueryLog();
        DB::enableQueryLog();
        $choices = ProductAdminSelectSearch::titleChoices();
        $logged = DB::getQueryLog();
        $this->assertSame('Пошукова клітка', $choices[(string) $product->id] ?? null);
        $this->assertNotEmpty($logged);
        foreach ($logged as $query) {
            $this->assertStringNotContainsString('description', $query['query']);
        }

        $html = ProductAdminSelectSearch::searchWithPhoto('Пошукова');
        $this->assertStringContainsString('from-variant.jpg', implode(' ', $html));
    }

    public function test_admin_shell_navigates_without_waiting_for_google_fonts(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::query()->where('email', 'admin@sitezoo.local')->firstOrFail();

        $this->actingAs($admin)
            ->get('/admin')
            ->assertSuccessful()
            ->assertSee('wire:navigate', false)
            ->assertSee('media="print"', false)
            ->assertSee("this.media='all'", false);
    }

    public function test_home_product_lists_page_opens(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::query()->where('email', 'admin@sitezoo.local')->firstOrFail();
        $this->actingAs($admin);

        Livewire::test(ManageHomeProductLists::class)
            ->assertSuccessful();
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
            'slug' => 'admin-cat-'.substr(md5($name), 0, 8),
            'sort_order' => 1,
            'is_active' => true,
        ]);
    }
}
