<?php

namespace Tests\Feature;

use App\Filament\Admin\Pages\ManageCatalogGrid;
use App\Models\OptionGroup;
use App\Models\OptionValue;
use App\Models\Product;
use App\Models\ShopCatalogGridSetting;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CatalogGridSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_defaults_match_current_storefront_and_values_are_clamped(): void
    {
        $this->assertSame([
            'columns_desktop' => 4,
            'columns_tablet' => 2,
            'columns_mobile' => 2,
            'subtitle_mode' => ShopCatalogGridSetting::SUBTITLE_COLORS_OR_CATEGORY,
            'title_lines' => 2,
            'photo_ratio' => '1/1',
        ], ShopCatalogGridSetting::resolved());

        ShopCatalogGridSetting::query()->create([
            'columns_desktop' => 9,
            'columns_tablet' => 0,
            'columns_mobile' => 3,
            'subtitle_mode' => 'nonsense',
            'title_lines' => 7,
            'photo_ratio' => '16/9',
        ]);
        ShopCatalogGridSetting::forgetResolvedCache();

        $this->assertSame([
            'columns_desktop' => 5,
            'columns_tablet' => 2,
            'columns_mobile' => 2,
            'subtitle_mode' => ShopCatalogGridSetting::SUBTITLE_COLORS_OR_CATEGORY,
            'title_lines' => 3,
            'photo_ratio' => '1/1',
        ], ShopCatalogGridSetting::resolved());
    }

    public function test_catalog_grid_receives_layout_variables(): void
    {
        $this->saveSettings(columns_desktop: 5, columns_tablet: 3, columns_mobile: 1, title_lines: 3, photo_ratio: '4/5');
        $root = $this->catalogRoot();
        $this->product($root, 'Поїлка', 'drinker');

        $html = $this->catalogHtml($root);

        $this->assertMatchesRegularExpression(
            '#class="grid catalog-results__grid"[^>]*style="[^"]*--grid-cols-desktop: 5;[^"]*--grid-cols-medium: 4;[^"]*--grid-cols-tablet: 3;[^"]*--grid-cols-mobile: 1;[^"]*--product-card-title-lines: 3;[^"]*--product-card-photo-ratio: 4 / 5;#',
            $html
        );
    }

    public function test_subtitle_row_follows_the_chosen_mode(): void
    {
        $root = $this->catalogRoot();
        $colors = OptionGroup::query()->create([
            'product_type' => OptionGroup::CATALOG_PRODUCT_TYPE,
            'name' => 'Колір',
            'slug' => 'grid-color',
            'selection_mode' => 'single',
            'value_type' => 'color',
            'is_active' => true,
        ]);
        $red = OptionValue::query()->create([
            'option_group_id' => $colors->id,
            'name' => 'Червоний',
            'slug' => 'grid-red',
            'color_hex' => '#e24b4a',
            'sort_order' => 1,
            'is_active' => true,
        ]);
        $this->product($root, 'Поїлка', 'drinker', [
            ['option_group_id' => $colors->id, 'option_value_ids' => [$red->id]],
        ]);
        $this->product($root, 'Корм', 'food');

        $html = $this->catalogHtml($root);
        $this->assertSame(2, substr_count($html, 'class="product-card__meta"'));
        $this->assertSame(1, substr_count($html, 'class="product-card__color-dot"'));
        $this->assertSame(1, substr_count($html, 'class="product-card__meta-text">Каталог<'));

        $this->saveSettings(subtitle_mode: ShopCatalogGridSetting::SUBTITLE_CATEGORY);
        $html = $this->catalogHtml($root);
        $this->assertSame(0, substr_count($html, 'class="product-card__color-dot"'));
        $this->assertSame(2, substr_count($html, 'class="product-card__meta-text">Каталог<'));

        $this->saveSettings(subtitle_mode: ShopCatalogGridSetting::SUBTITLE_COLORS);
        $html = $this->catalogHtml($root);
        $this->assertSame(1, substr_count($html, 'class="product-card__color-dot"'));
        $this->assertSame(0, substr_count($html, 'product-card__meta-text'));
        // Рядок лишається і без кольорів — ціна й кошик стоять на одній лінії в усьому ряду.
        $this->assertSame(2, substr_count($html, 'class="product-card__meta"'));

        $this->saveSettings(subtitle_mode: ShopCatalogGridSetting::SUBTITLE_NONE);
        $html = $this->catalogHtml($root);
        $this->assertSame(0, substr_count($html, 'class="product-card__meta"'));
    }

    public function test_admin_saves_grid_settings(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->actingAs(User::query()->where('email', 'admin@sitezoo.local')->firstOrFail());

        Livewire::test(ManageCatalogGrid::class)
            ->assertSuccessful()
            ->fillForm([
                'columns_desktop' => 3,
                'columns_tablet' => 4,
                'columns_mobile' => 1,
                'subtitle_mode' => ShopCatalogGridSetting::SUBTITLE_CATEGORY,
                'title_lines' => 1,
                'photo_ratio' => '4/3',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        ShopCatalogGridSetting::forgetResolvedCache();
        $this->assertSame([
            'columns_desktop' => 3,
            'columns_tablet' => 4,
            'columns_mobile' => 1,
            'subtitle_mode' => ShopCatalogGridSetting::SUBTITLE_CATEGORY,
            'title_lines' => 1,
            'photo_ratio' => '4/3',
        ], ShopCatalogGridSetting::resolved());
    }

    private function saveSettings(
        int $columns_desktop = 4,
        int $columns_tablet = 2,
        int $columns_mobile = 2,
        string $subtitle_mode = ShopCatalogGridSetting::SUBTITLE_COLORS_OR_CATEGORY,
        int $title_lines = 2,
        string $photo_ratio = '1/1',
    ): void {
        ShopCatalogGridSetting::query()->updateOrCreate(['id' => 1], compact(
            'columns_desktop', 'columns_tablet', 'columns_mobile', 'subtitle_mode', 'title_lines', 'photo_ratio'
        ));
        ShopCatalogGridSetting::forgetResolvedCache();
    }

    private function catalogHtml(OptionValue $root): string
    {
        return (string) $this->get(route('catalog.index', ['category' => $root->id]))->assertOk()->getContent();
    }

    /**
     * @param  list<array<string, mixed>>  $extraOptions
     */
    private function product(OptionValue $root, string $title, string $slug, array $extraOptions = []): Product
    {
        return Product::query()->create([
            'title' => $title,
            'slug' => $slug,
            'product_type' => OptionGroup::CATALOG_PRODUCT_TYPE,
            'category_parent_value_id' => $root->id,
            'category_value_id' => null,
            'price' => 100,
            'is_available' => true,
            'variant_options' => array_merge([
                ['option_group_id' => $root->option_group_id, 'option_value_ids' => [$root->id]],
            ], $extraOptions),
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
