<?php

namespace App\Filament\Admin\Resources\Users\Schemas;

use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\CreateRecord;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rules\Password;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label("Ім'я")
                    ->maxLength(255)
                    ->helperText('Необов’язково: якщо порожньо, буде використано частину email до символу @.'),
                TextInput::make('email')
                    ->label('Логін (email)')
                    ->email()
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true),
                TextInput::make('password')
                    ->label(fn ($livewire): string => $livewire instanceof CreateRecord ? 'Пароль' : 'Новий пароль')
                    ->password()
                    ->revealable()
                    ->rule(Password::default())
                    ->dehydrated(fn ($state): bool => filled($state))
                    ->required(fn ($livewire): bool => $livewire instanceof CreateRecord)
                    ->helperText(fn ($livewire): ?string => $livewire instanceof CreateRecord
                        ? null
                        : 'Залиште порожнім, якщо не змінюєте пароль.'),
                TextInput::make('password_confirmation')
                    ->label('Підтвердження пароля')
                    ->password()
                    ->revealable()
                    ->dehydrated(false)
                    ->same('password')
                    ->required(fn (Get $get, $livewire): bool => $livewire instanceof CreateRecord || filled($get('password'))),
                CheckboxList::make('roles')
                    ->label('Доступ до адмінки')
                    ->helperText('Без ролей — лише покупець на вітрині. «Менеджер» та «Адміністратор» можуть заходити в /admin.')
                    ->options([
                        'manager' => 'Менеджер',
                        'admin' => 'Адміністратор',
                    ])
                    ->descriptions([
                        'manager' => 'Доступ до панелі без повних прав',
                        'admin' => 'Повний доступ',
                    ])
                    ->columns(1)
                    ->dehydrated(false),
            ]);
    }
}
