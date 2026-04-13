<?php

namespace App\Filament\Admin\Resources\Users\Pages;

use App\Filament\Admin\Resources\Users\UserResource;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected static ?string $title = 'Новий користувач';

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        unset($data['password_confirmation'], $data['roles']);
        $data['email_verified_at'] ??= now();

        if (blank($data['name'] ?? null) && filled($data['email'] ?? null)) {
            $email = (string) $data['email'];
            $data['name'] = strstr($email, '@', true) ?: $email;
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        $roles = $this->form->getRawState()['roles'] ?? [];
        $this->record->syncRoles(is_array($roles) ? $roles : []);
    }
}
