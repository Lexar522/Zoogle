<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Версію сайту й дату останньої заливки видно в адмінці (низ бокового меню), щоб перевірити, чи оновлення доїхало
 * на хостинг. На самому сайті покупці її не бачать.
 */
class SiteReleaseAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_sidebar_shows_version_and_deploy_date(): void
    {
        $this->assertFileDoesNotExist(base_path('bootstrap/cache/config.php'), 'Локально конфіг не кешовано: дата береться з VERSION');
        $version = trim((string) file_get_contents(base_path('VERSION')));
        $deployedAt = date('d.m.Y H:i', (int) filemtime(base_path('VERSION')));
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->actingAs(User::query()->where('email', 'admin@sitezoo.local')->firstOrFail());

        $this->get('/admin')
            ->assertOk()
            ->assertSee('Версія '.$version)
            ->assertSee('оновлено '.$deployedAt);
    }

    public function test_storefront_does_not_show_version(): void
    {
        $version = trim((string) file_get_contents(base_path('VERSION')));

        $this->get(route('home'))
            ->assertOk()
            ->assertDontSee('Версія '.$version);
    }
}
