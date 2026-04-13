<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class EnsureAdminUser extends Command
{
    protected $signature = 'shop:ensure-admin
                            {--email= : Admin email (за замовчуванням SHOP_ADMIN_EMAIL або admin@sitezoo.local)}
                            {--password= : Пароль у відкритому вигляді; якщо не вказано — SHOP_ADMIN_PASSWORD або Admin0000}';

    protected $description = 'Create or update admin user, assign admin role, flush permission cache (recovery after DB issues)';

    public function handle(): int
    {
        $emailOpt = $this->option('email');
        $email = strtolower(trim($emailOpt !== null && $emailOpt !== '' ? (string) $emailOpt : (string) env('SHOP_ADMIN_EMAIL', 'admin@sitezoo.local')));

        $passwordOpt = $this->option('password');
        $plain = $passwordOpt !== null && $passwordOpt !== ''
            ? (string) $passwordOpt
            : (string) env('SHOP_ADMIN_PASSWORD', 'Admin0000');

        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        Role::firstOrCreate(['name' => 'manager']);

        $user = User::query()->firstOrNew(['email' => $email]);
        $user->name = $user->exists ? $user->name : 'Admin';
        $user->password = $plain;
        $user->email_verified_at = $user->email_verified_at ?? now();
        $user->save();

        if (! $user->hasRole($adminRole)) {
            $user->assignRole($adminRole);
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $this->info("OK: {$email} — пароль оновлено, роль admin призначено. Відкрийте /admin з того ж хосту, що в APP_URL.");

        return self::SUCCESS;
    }
}
