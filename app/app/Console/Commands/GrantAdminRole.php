<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class GrantAdminRole extends Command
{
    protected $signature = 'shop:grant-admin {email : Email користувача (наприклад той самий, що після Google)}';

    protected $description = 'Призначити роль admin існуючому користувачу (без зміни пароля). Потрібно для доступу до /admin.';

    public function handle(): int
    {
        $email = strtolower(trim((string) $this->argument('email')));

        Role::firstOrCreate(['name' => 'admin']);
        Role::firstOrCreate(['name' => 'manager']);

        $user = User::query()->where('email', $email)->first();
        if ($user === null) {
            $this->error("Користувача з email «{$email}» не знайдено. Створіть адміна: php artisan shop:ensure-admin --email={$email}");

            return self::FAILURE;
        }

        if ($user->hasRole('admin')) {
            $this->info("У «{$email}» вже є роль admin. Кеш прав оновлено.");
            app()[PermissionRegistrar::class]->forgetCachedPermissions();

            return self::SUCCESS;
        }

        $user->assignRole('admin');
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $this->info("OK: роль admin призначено для {$email}. Оновіть сторінку /admin (інколи потрібен вихід і повторний вхід).");

        return self::SUCCESS;
    }
}
