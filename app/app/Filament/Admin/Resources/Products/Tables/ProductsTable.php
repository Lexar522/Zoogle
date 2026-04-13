<?php

namespace App\Filament\Admin\Resources\Products\Tables;

use App\Models\OptionGroup;
use App\Models\OptionValue;
use App\Models\Product;
use App\Models\ProductVariant;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class ProductsTable
{
    /**
     * Перший збережений шлях до файлу: спочатку галерея товару, потім фото варіантів (як на вітрині в картках).
     */
    public static function firstStoredPhotoPath(?Product $record): ?string
    {
        if (! $record) {
            return null;
        }

        $lists = [];
        if (is_array($record->photos ?? null)) {
            $lists[] = $record->photos;
        }
        foreach ($record->variants ?? [] as $variant) {
            if (is_array($variant->photos ?? null)) {
                $lists[] = $variant->photos;
            }
        }

        foreach ($lists as $photos) {
            foreach ($photos as $item) {
                if (is_string($item) && $item !== '') {
                    return ltrim($item, '/');
                }
            }
        }

        return null;
    }

    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('variants'))
            ->columns([
                ImageColumn::make('photo_preview')
                    ->label('Фото')
                    ->disk('public')
                    ->checkFileExistence(false)
                    ->imageSize(40)
                    ->square()
                    ->state(fn (Product $record): ?string => self::firstStoredPhotoPath($record))
                    ->sortable(false),
                TextColumn::make('title')
                    ->label('Назва')
                    ->searchable(),
                TextColumn::make('category_name')
                    ->label('Категорія')
                    ->state(function ($record): string {
                        static $categoryGroupId = null;
                        static $valueNameCache = [];

                        if ($categoryGroupId === null) {
                            $categoryGroupId = OptionGroup::systemCategoryGroupIdForCatalog();
                        }

                        if (! $categoryGroupId) {
                            return '—';
                        }

                        $categoryValueId = null;
                        foreach (($record->variant_options ?? []) as $row) {
                            if ((int) ($row['option_group_id'] ?? 0) !== (int) $categoryGroupId) {
                                continue;
                            }

                            $categoryValueId = (int) collect($row['option_value_ids'] ?? [])
                                ->map(fn ($id) => (int) $id)
                                ->first();
                            break;
                        }

                        if (! $categoryValueId) {
                            return '—';
                        }

                        if (array_key_exists($categoryValueId, $valueNameCache)) {
                            return $valueNameCache[$categoryValueId];
                        }

                        $valueNameCache[$categoryValueId] = (string) (OptionValue::query()
                            ->whereKey($categoryValueId)
                            ->value('name') ?? '—');

                        return $valueNameCache[$categoryValueId];
                    })
                    ->badge(),
                TextColumn::make('variant_price')
                    ->label('Ціна (від)')
                    ->state(function (Product $record): ?float {
                        $variants = $record->variants ?? collect();

                        $visible = $variants->filter(fn (ProductVariant $v): bool => $v->isVisibleOnStorefront());
                        $minFrom = static function ($coll): ?float {
                            if ($coll->isEmpty()) {
                                return null;
                            }

                            return (float) $coll->min(fn (ProductVariant $v): float => (float) $v->price);
                        };

                        $min = $minFrom($visible);
                        if ($min !== null) {
                            return $min;
                        }

                        if ($record->price !== null && $record->price !== '') {
                            return (float) $record->price;
                        }

                        return $minFrom($variants);
                    })
                    ->money('UAH', locale: 'uk')
                    ->sortable(false)
                    ->placeholder('—'),
                ToggleColumn::make('is_available')
                    ->label('В наявності'),
                TextColumn::make('published_at')
                    ->label('Опубліковано')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Створено')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label('Оновлено')
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
