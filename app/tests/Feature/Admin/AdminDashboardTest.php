<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_shows_quick_links_and_order_summary(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->actingAs(User::query()->where('email', 'admin@sitezoo.local')->firstOrFail());

        $this->get('/admin')
            ->assertOk()
            ->assertSee('Що будемо робити сьогодні?')
            ->assertSee('Обробити й відправити')
            ->assertSee('Чекають на обробку')
            ->assertDontSee('filamentphp.com');
    }

    public function test_admin_tables_stack_on_phones(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->actingAs(User::query()->where('email', 'admin@sitezoo.local')->firstOrFail());

        $this->get('/admin/users')
            ->assertOk()
            ->assertSee('fi-ta-table-stacked-on-mobile', false);
    }
}
