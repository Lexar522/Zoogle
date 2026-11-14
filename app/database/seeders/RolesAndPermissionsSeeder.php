<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        Role::firstOrCreate(['name' => 'manager']);

        $adminEmail = strtolower(trim((string) env('SHOP_ADMIN_EMAIL', 'admin@sitezoo.local')));
        $configuredPassword = (string) env('SHOP_ADMIN_PASSWORD', '');
        $adminPassword = $configuredPassword !== '' ? $configuredPassword : Str::password(20);

        $admin = User::firstOrCreate(
            ['email' => $adminEmail],
            [
                'name' => 'Admin',
                'password' => $adminPassword,
                'email_verified_at' => now(),
            ]
        );

        if ($admin->wasRecentlyCreated && $configuredPassword === '') {
            $this->command?->warn("SHOP_ADMIN_PASSWORD не задано. Згенерований пароль {$adminEmail}: {$adminPassword}");
        }

        if (! $admin->hasRole($adminRole)) {
            $admin->assignRole($adminRole);
        }
    }
}
