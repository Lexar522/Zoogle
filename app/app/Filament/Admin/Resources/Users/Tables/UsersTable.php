<?php

namespace App\Filament\Admin\Resources\Users\Tables;

use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('roles'))
            ->columns([
                TextColumn::make('email')
                    ->label('Логін (email)')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('name')
                    ->label("Ім'я")
                    ->searchable()
                    ->placeholder('—'),
                TextColumn::make('roles_display')
                    ->label('Ролі')
                    ->state(fn (User $record): string => $record->roles->pluck('name')->join(', ') ?: '—')
                    ->toggleable(),
                TextColumn::make('google_id')
                    ->label('Google')
                    ->formatStateUsing(fn (?string $state): string => filled($state) ? 'Так' : '—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label('Створено')
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('id', 'desc')
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
