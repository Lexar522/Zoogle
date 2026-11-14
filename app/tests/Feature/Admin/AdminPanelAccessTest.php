<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminPanelAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_shop_customer_is_sent_to_admin_login_instead_of_forbidden(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $customer = User::factory()->create();

        $this->actingAs($customer)
            ->get('/admin')
            ->assertRedirect('/admin/login');

        $this->assertGuest();
    }

    public function test_admin_can_open_the_panel(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $admin = User::query()->where('email', 'admin@sitezoo.local')->firstOrFail();

        $this->actingAs($admin)
            ->get('/admin')
            ->assertSuccessful();
    }
}
