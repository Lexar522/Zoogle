<?php

namespace App\Filament\Admin\Resources\Bundles\Tables;

use App\Models\Bundle;
use App\Services\BundlePricingService;
use App\Support\PublicStorageUrl;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Number;

class BundlesTable
{
    /**
     * Галерея комплекту, інакше перше фото з товарів у складі (товар → варіанти).
     */
    public static function firstStoredPhotoPath(?Bundle $record): ?string
    {
        if (! $record) {
            return null;
        }

        if (is_array($record->photos ?? null)) {
            foreach ($record->photos as $item) {
                if (is_string($item) && $item !== '') {
                    return ltrim($item, '/');
                }
            }
        }

        foreach ($record->items ?? [] as $bundleItem) {
            $product = $bundleItem->product;
            if (! $product) {
                continue;
            }
            if (is_array($product->photos ?? null)) {
                foreach ($product->photos as $path) {
                    if (is_string($path) && $path !== '') {
                        return ltrim($path, '/');
                    }
                }
            }
            foreach ($product->variants ?? [] as $variant) {
                if (is_array($variant->photos ?? null)) {
                    foreach ($variant->photos as $path) {
                        if (is_string($path) && $path !== '') {
                            return ltrim($path, '/');
                        }
                    }
                }
            }
        }

        return null;
    }

    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['items.product.variants']))
            ->columns([
                ImageColumn::make('photo_preview')
                    ->label('Фото')
                    ->checkFileExistence(false)
                    ->imageSize(40)
                    ->square()
                    ->state(fn (Bundle $record): ?string => PublicStorageUrl::forPath(self::firstStoredPhotoPath($record)))
                    ->sortable(false),
                TextColumn::make('title')
                    ->label('Назва')
                    ->searchable(),
                TextColumn::make('slug')
                    ->label('Slug')
                    ->searchable(),
                IconColumn::make('is_visible')
                    ->label('Вітрина')
                    ->boolean(),
                IconColumn::make('is_active')
                    ->label('Активний')
                    ->boolean(),
                TextColumn::make('computed_price')
                    ->label('Сума товарів')
                    ->state(function (Bundle $record): float {
                        return (float) app(BundlePricingService::class)->quote($record)['subtotal'];
                    })
                    ->money('UAH', locale: 'uk')
                    ->description(function (Bundle $record): ?string {
                        $q = app(BundlePricingService::class)->quote($record);
                        if (($q['discount'] ?? 0) < 0.001) {
                            return null;
                        }

                        return 'Після акції на комплект: '.Number::currency(
                            (float) $q['total'],
                            'UAH',
                            'uk',
                        );
                    })
                    ->sortable(false),
                TextColumn::make('updated_at')
                    ->label('Оновлено')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
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
