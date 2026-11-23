<?php

namespace Tests\Feature\Admin;

use App\Models\OptionGroup;
use App\Models\OptionValue;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Сторінка «Категорії» рендерить усе дерево однією формою; на хостингу з 128 МБ вона падала з 500.
 */
class CatalogCategoriesPageMemoryTest extends TestCase
{
    use RefreshDatabase;

    private string $originalMemoryLimit;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalMemoryLimit = (string) ini_get('memory_limit');
    }

    protected function tearDown(): void
    {
        ini_set('memory_limit', $this->originalMemoryLimit);

        parent::tearDown();
    }

    public function test_page_raises_hosting_memory_limit_for_large_category_tree(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->actingAs(User::query()->where('email', 'admin@sitezoo.local')->firstOrFail());
        $group = OptionGroup::query()->firstOrCreate(
            ['product_type' => OptionGroup::CATALOG_PRODUCT_TYPE, 'slug' => 'category'],
            ['name' => 'Категорія', 'selection_mode' => 'single', 'value_type' => 'text', 'is_active' => true]
        );
        foreach (['Тварини', 'Зоотовари'] as $rootIndex => $rootName) {
            $root = $this->category($group, $rootName, null);
            foreach (range(1, 6) as $childIndex) {
                $child = $this->category($group, $rootName.' '.$childIndex, $root);
                $this->category($group, $rootName.' '.$childIndex.'.1', $child, excluded: $rootIndex === 0);
            }
        }
        ini_set('memory_limit', '128M');

        $this->get('/admin/catalog-categories')
            ->assertOk()
            ->assertSee('Не передавати в Google Merchant');

        $this->assertSame((string) (512 * 1024 * 1024), ini_get('memory_limit'));
    }

    public function test_page_keeps_unlimited_memory_as_is(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->actingAs(User::query()->where('email', 'admin@sitezoo.local')->firstOrFail());
        OptionGroup::query()->firstOrCreate(
            ['product_type' => OptionGroup::CATALOG_PRODUCT_TYPE, 'slug' => 'category'],
            ['name' => 'Категорія', 'selection_mode' => 'single', 'value_type' => 'text', 'is_active' => true]
        );
        ini_set('memory_limit', '-1');

        $this->get('/admin/catalog-categories')->assertOk();

        $this->assertSame('-1', ini_get('memory_limit'));
    }

    private function category(OptionGroup $group, string $name, ?OptionValue $parent, bool $excluded = false): OptionValue
    {
        return OptionValue::query()->create([
            'option_group_id' => $group->id,
            'parent_id' => $parent?->id,
            'slug' => 'mem-'.uniqid(),
            'name' => $name,
            'sort_order' => 1,
            'is_active' => true,
            'exclude_from_merchant_feed' => $excluded,
        ]);
    }
}
