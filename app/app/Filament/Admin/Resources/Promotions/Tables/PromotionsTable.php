<?php

namespace App\Filament\Admin\Resources\Promotions\Tables;

use App\Models\Promotion;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PromotionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Назва')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('slug')
                    ->label('Slug')
                    ->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('is_active')
                    ->label('Активна')
                    ->boolean(),
                TextColumn::make('storefront_status')
                    ->label('На вітрині')
                    ->state(function (Promotion $record): string {
                        if (! $record->is_active) {
                            return 'Вимкнено';
                        }

                        $now = now();

                        if ($record->ends_at && $record->ends_at->lessThan($now)) {
                            return 'Завершена';
                        }

                        return 'Діє зараз';
                    }),
                TextColumn::make('starts_at')
                    ->label('Початок')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('ends_at')
                    ->label('Кінець')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('priority')
                    ->label('Пріоритет')
                    ->sortable(),
                TextColumn::make('targets_count')
                    ->counts('targets')
                    ->label('Варіантів'),
                TextColumn::make('updated_at')
                    ->label('Оновлено')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('priority', 'desc')
            ->recordActions([
                EditAction::make()->label('Редагувати'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
