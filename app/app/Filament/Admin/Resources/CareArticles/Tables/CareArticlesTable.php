<?php

namespace App\Filament\Admin\Resources\CareArticles\Tables;

use App\Models\ProductCareArticle;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class CareArticlesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('category'))
            ->defaultSort('updated_at', 'desc')
            ->columns([
                TextColumn::make('title')
                    ->label('Заголовок')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('category_path')
                    ->label('Напрямок')
                    ->state(fn (ProductCareArticle $record): string => $record->categoryPathLabel() ?: '—'),
                TextColumn::make('slug')
                    ->label('Slug')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('is_published')
                    ->label('Опубліковано')
                    ->boolean(),
                TextColumn::make('updated_at')
                    ->label('Оновлено')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                TernaryFilter::make('is_published')
                    ->label('Опубліковано'),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
