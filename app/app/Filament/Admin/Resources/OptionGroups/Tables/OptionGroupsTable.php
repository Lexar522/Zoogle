<?php

namespace App\Filament\Admin\Resources\OptionGroups\Tables;

use App\Models\OptionGroup;
use App\Models\OptionValue;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class OptionGroupsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query
                ->with('values')
                ->where('slug', '!=', 'category'))
            ->columns([
                TextColumn::make('product_type')
                    ->label('Категорія')
                    ->formatStateUsing(function (?string $state): string {
                        static $categoryGroupId = null;
                        static $labelBySlug = null;

                        if ($categoryGroupId === null) {
                            $categoryGroupId = OptionGroup::query()
                                ->where('slug', 'category')
                                ->where('is_active', true)
                                ->value('id');
                        }

                        if ($labelBySlug === null) {
                            $labelBySlug = $categoryGroupId
                                ? OptionValue::query()
                                    ->where('option_group_id', (int) $categoryGroupId)
                                    ->get(['slug', 'name'])
                                    ->mapWithKeys(fn (OptionValue $v): array => [(string) $v->slug => (string) $v->name])
                                    ->all()
                                : [];
                        }

                        if (in_array($state, [OptionGroup::CATALOG_PRODUCT_TYPE, 'accessory'], true)) {
                            return 'Усі товари';
                        }

                        if (is_string($state) && $state !== '' && isset($labelBySlug[$state])) {
                            return $labelBySlug[$state];
                        }

                        return (string) $state;
                    })
                    ->searchable(),
                TextColumn::make('name')
                    ->label('Назва')
                    ->searchable(),
                TextColumn::make('slug')
                    ->label('Слаг')
                    ->searchable(),
                TextColumn::make('selection_mode')
                    ->label('Вибір')
                    ->formatStateUsing(fn (?string $state): string => $state === 'multiple' ? 'Кілька' : 'Один')
                    ->badge(),
                TextColumn::make('value_type')
                    ->label('Формат значення')
                    ->formatStateUsing(fn (?string $state): string => $state === 'color' ? 'Колір' : 'Текст')
                    ->badge(),
                TextColumn::make('values_preview')
                    ->label('Значення')
                    ->html()
                    ->state(function ($record): string {
                        $items = $record->values
                            ->take(6)
                            ->map(function ($value): string {
                                $name = e($value->name);

                                if (! filled($value->color_hex)) {
                                    return $name;
                                }

                                $dot = '<span style="display:inline-block;width:10px;height:10px;border-radius:9999px;border:1px solid #d1d5db;background:'.e((string) $value->color_hex).';margin-right:6px;vertical-align:middle;"></span>';

                                return $dot.$name;
                            })
                            ->implode('<br>');

                        if ($items === '') {
                            return '—';
                        }

                        $remaining = max(0, $record->values->count() - 6);

                        if ($remaining > 0) {
                            $items .= '<br><span style="color:#6b7280">+ ще '.$remaining.'</span>';
                        }

                        return $items;
                    }),
                IconColumn::make('is_active')
                    ->label('Активна')
                    ->boolean(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
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
