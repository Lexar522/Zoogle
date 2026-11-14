<?php

namespace App\Filament\Admin\Resources\Orders\RelationManagers;

use App\Models\OrderUpdate;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class OrderUpdatesRelationManager extends RelationManager
{
    protected static string $relationship = 'updates';

    protected static ?string $title = 'Історія для покупця';

    protected static ?string $modelLabel = 'Оновлення';

    protected static ?string $pluralModelLabel = 'Оновлення';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->label('Коли')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Статус')
                    ->formatStateUsing(fn (OrderUpdate $record): string => $record->statusLabel() ?? '—'),
                TextColumn::make('message')
                    ->label('Коментар покупцю')
                    ->wrap()
                    ->limit(80)
                    ->placeholder('—'),
                IconColumn::make('email_sent_at')
                    ->label('Лист')
                    ->boolean()
                    ->getStateUsing(fn (OrderUpdate $record): bool => $record->email_sent_at !== null),
                TextColumn::make('author.name')
                    ->label('Хто')
                    ->placeholder('Система'),
            ])
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading('Ще немає повідомлень покупцю')
            ->emptyStateDescription('Змініть статус або напишіть коментар у блоці вище — запис з’явиться тут.');
    }
}
