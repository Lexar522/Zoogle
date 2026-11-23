<?php

namespace Tests\Feature\Admin;

use App\Filament\Admin\Pages\ManageCatalogCategories;
use App\Models\OptionGroup;
use App\Models\OptionValue;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Сторінка «Категорії» показує корені й лише одну гілку: усе дерево в одній формі вантажилося секундами.
 * Міграції вже створюють корені «Товар» (sort 1) і «Аксесуар», тому «Тварини» тут з sort 0 — перша гілка.
 */
class CatalogCategoriesBranchPageTest extends TestCase
{
    use RefreshDatabase;

    private OptionGroup $group;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->actingAs(User::query()->where('email', 'admin@sitezoo.local')->firstOrFail());
        $this->group = OptionGroup::query()->firstOrCreate(
            ['product_type' => OptionGroup::CATALOG_PRODUCT_TYPE, 'slug' => 'category'],
            ['name' => 'Категорія', 'selection_mode' => 'single', 'value_type' => 'text', 'is_active' => true]
        );
    }

    public function test_page_lists_all_roots_but_renders_only_first_branch(): void
    {
        $animals = $this->category('Тварини', sortOrder: 0);
        $goods = $this->category('Зоотовари', sortOrder: 5);
        $this->category('Гризуни', $animals);
        $this->category('Клітки', $goods);

        $this->get(ManageCatalogCategories::getUrl())
            ->assertOk()
            ->assertSee('Тварини')
            ->assertSee('Зоотовари')
            ->assertSee('Гризуни')
            ->assertDontSee('Клітки');
    }

    public function test_branch_query_switches_branch_and_unknown_branch_falls_back_to_first(): void
    {
        $animals = $this->category('Тварини', sortOrder: 0);
        $goods = $this->category('Зоотовари', sortOrder: 5);
        $this->category('Гризуни', $animals);
        $this->category('Клітки', $goods);

        $this->get(ManageCatalogCategories::getUrl(['branch' => $goods->id]))
            ->assertOk()
            ->assertSee('Клітки')
            ->assertDontSee('Гризуни');

        $this->get(ManageCatalogCategories::getUrl(['branch' => 999999]))
            ->assertOk()
            ->assertSee('Гризуни')
            ->assertDontSee('Клітки');
    }

    public function test_choosing_another_branch_reloads_page_with_that_branch(): void
    {
        $this->category('Тварини', sortOrder: 0);
        $goods = $this->category('Зоотовари', sortOrder: 5);

        Livewire::test(ManageCatalogCategories::class)
            ->set('data.branch_switch', $goods->id)
            ->assertRedirect(ManageCatalogCategories::getUrl(['branch' => $goods->id]));
    }

    public function test_new_subcategory_is_saved_under_selected_branch_and_other_branches_stay_intact(): void
    {
        $animals = $this->category('Тварини', sortOrder: 0);
        $goods = $this->category('Зоотовари', sortOrder: 5);
        $rodents = $this->category('Гризуни', $animals);
        $cages = $this->category('Клітки', $goods);

        $page = Livewire::withQueryParams(['branch' => $goods->id])->test(ManageCatalogCategories::class);
        $branch = $page->get('data.branch');
        $branch['new-item'] = [
            'option_group_id' => $this->group->id,
            'name' => 'Поїлки',
            'slug' => 'poilky',
            'exclude_from_merchant_feed' => false,
            'children' => [],
        ];
        $page->set('data.branch', $branch)
            ->call('save')
            ->assertHasNoErrors();

        $drinkers = OptionValue::query()->where('slug', 'poilky')->firstOrFail();
        $this->assertSame($goods->id, (int) $drinkers->parent_id);
        $this->assertSame($this->group->id, (int) $drinkers->option_group_id);
        $this->assertNotNull($rodents->fresh(), 'Підкатегорії інших гілок не видаляються');
        $this->assertNotNull($cages->fresh());
        $this->assertNull($animals->fresh()->parent_id);
    }

    private function category(string $name, ?OptionValue $parent = null, int $sortOrder = 1): OptionValue
    {
        return OptionValue::query()->create([
            'option_group_id' => $this->group->id,
            'parent_id' => $parent?->id,
            'slug' => 'branch-'.uniqid(),
            'name' => $name,
            'sort_order' => $sortOrder,
            'is_active' => true,
        ]);
    }
}
