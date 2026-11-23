<?php

namespace Tests\Feature;

use App\Filament\Admin\Pages\ManageHomeSeo;
use App\Models\ShopSeoSetting;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class HomeSeoSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_uses_default_texts_until_admin_saves_own(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('<title>'.__('shop.home_page_title').'</title>', false)
            ->assertSee('content="'.e(__('shop.home_meta_description')).'"', false);
    }

    public function test_admin_texts_replace_title_and_description_for_google(): void
    {
        $this->actingAsAdmin();

        $this->get('/admin/home-seo')->assertOk();

        Livewire::test(ManageHomeSeo::class)
            ->set('data.home_title', 'Зоомагазин ZOOGLE — екзотичні тварини та аксесуари')
            ->set('data.home_description', 'Клітки, корм і аксесуари для гризунів, птахів і рептилій. Доставка по Україні.')
            ->call('save')
            ->assertHasNoErrors();

        auth()->logout();
        ShopSeoSetting::forgetResolvedCache();

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('<title>Зоомагазин ZOOGLE — екзотичні тварини та аксесуари</title>', false)
            ->assertSee('<meta name="description" content="Клітки, корм і аксесуари для гризунів, птахів і рептилій. Доставка по Україні.">', false)
            ->assertSee('property="og:description" content="Клітки, корм і аксесуари для гризунів, птахів і рептилій. Доставка по Україні."', false);
    }

    public function test_too_long_description_is_rejected(): void
    {
        $this->actingAsAdmin();

        Livewire::test(ManageHomeSeo::class)
            ->set('data.home_description', str_repeat('а', 400))
            ->call('save')
            ->assertHasErrors(['data.home_description']);
    }

    private function actingAsAdmin(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $this->actingAs($admin);
    }
}
