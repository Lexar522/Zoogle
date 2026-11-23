<?php

namespace Tests\Feature;

use App\Filament\Admin\Pages\ManageMarketing;
use App\Models\ShopMarketingSetting;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MarketingSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_saves_pixel_analytics_and_verification_codes(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->actingAs(User::query()->where('email', 'admin@sitezoo.local')->firstOrFail());

        $this->get('/admin/marketing')->assertOk()->assertSee(route('seo.merchant-feed'));

        Livewire::test(ManageMarketing::class)
            ->fillForm([
                'ga4_id' => 'g-abc123',
                'meta_pixel_id' => '123456789012345',
                'facebook_domain_verification' => 'fbverify123abc',
                'google_site_verification' => '',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('G-ABC123', ShopMarketingSetting::query()->sole()->ga4_id);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('<meta name="facebook-domain-verification" content="fbverify123abc">', false)
            ->assertSee('G-ABC123', false)
            ->assertSee('123456789012345', false)
            ->assertSee('data-cookie-banner', false);
    }

    public function test_bad_values_are_rejected(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->actingAs(User::query()->where('email', 'admin@sitezoo.local')->firstOrFail());

        Livewire::test(ManageMarketing::class)
            ->fillForm([
                'meta_pixel_id' => 'abc',
                'facebook_domain_verification' => '"><script>',
            ])
            ->call('save')
            ->assertHasFormErrors(['meta_pixel_id', 'facebook_domain_verification']);

        $this->assertSame(0, ShopMarketingSetting::query()->count());
    }

    public function test_env_values_still_work_when_admin_fields_are_empty(): void
    {
        config(['shop.marketing.ga4_id' => 'G-FROMENV1', 'shop.google_site_verification' => 'envverify123']);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('G-FROMENV1', false)
            ->assertSee('<meta name="google-site-verification" content="envverify123">', false);
    }
}
