<?php

namespace Tests\Feature;

use App\Filament\Admin\Pages\ManageMobileMenu;
use App\Models\ShopIntegrationSetting;
use App\Models\ShopMobileMenuSetting;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MobileMenuSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_builds_own_menu_with_order_labels_and_custom_link(): void
    {
        $this->actingAsAdmin();
        ShopIntegrationSetting::record()->forceFill(['contact_phone' => '+380991112233'])->save();

        $this->get('/admin/mobile-menu')->assertOk();

        Livewire::test(ManageMobileMenu::class)
            ->set('data.items', [
                ['target' => 'on_sale', 'label' => 'Знижки тижня', 'color' => 'pink', 'group' => 'main', 'url' => null],
                ['target' => 'custom', 'label' => 'Наш Instagram', 'color' => 'default', 'group' => 'info', 'url' => 'https://instagram.com/zoogle.ua'],
                ['target' => 'catalog', 'label' => '', 'color' => 'default', 'group' => 'main', 'url' => null],
            ])
            ->set('data.show_contacts', false)
            ->set('data.show_login', false)
            ->call('save')
            ->assertHasNoErrors();

        auth()->logout();
        ShopMobileMenuSetting::forgetResolvedCache();

        $menu = $this->menuHtml();
        $this->assertStringContainsString('Знижки тижня', $menu);
        $this->assertStringContainsString('mobile-menu__link--pink', $menu);
        $this->assertStringContainsString('href="https://instagram.com/zoogle.ua"', $menu);
        $this->assertStringContainsString('>Каталог<', $menu);
        $this->assertLessThan(strpos($menu, '>Каталог<'), strpos($menu, 'Знижки тижня'));
        $this->assertStringNotContainsString(route('care.index'), $menu);
        $this->assertStringNotContainsString('tel:+380991112233', $menu);
        $this->assertStringNotContainsString('mobile-menu__login', $menu);
    }

    public function test_unsafe_custom_links_are_rejected(): void
    {
        $this->actingAsAdmin();

        Livewire::test(ManageMobileMenu::class)
            ->set('data.items', [
                ['target' => 'custom', 'label' => 'Пастка', 'color' => 'default', 'group' => 'main', 'url' => 'javascript:alert(1)'],
            ])
            ->call('save')
            ->assertHasErrors();

        $this->assertFalse(ShopMobileMenuSetting::query()->exists());
    }

    public function test_sections_that_are_switched_off_stay_hidden_in_custom_menu(): void
    {
        ShopMobileMenuSetting::query()->create([
            'items' => [
                ['target' => 'sponsorship', 'label' => 'Опіка', 'color' => 'green', 'group' => 'main', 'url' => null],
                ['target' => 'catalog', 'label' => null, 'color' => 'default', 'group' => 'main', 'url' => null],
            ],
            'show_contacts' => true,
            'show_login' => true,
        ]);

        $menu = $this->menuHtml();

        $this->assertStringNotContainsString(route('sponsorship.index'), $menu);
        $this->assertStringContainsString('href="'.route('catalog.index').'"', $menu);
    }

    private function menuHtml(): string
    {
        $html = (string) $this->get(route('home'))->assertOk()->getContent();
        $from = strpos($html, 'id="mobile-menu"');
        $this->assertNotFalse($from);

        return substr($html, $from, strpos($html, '</nav>', $from) - $from);
    }

    private function actingAsAdmin(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $this->actingAs($admin);
    }
}
