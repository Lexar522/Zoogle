<?php

namespace App\Filament\Admin\Resources\Orders\RelationManagers;

use App\Filament\Admin\Resources\Bundles\BundleResource;
use App\Filament\Admin\Resources\Products\ProductResource;
use App\Models\OrderItem;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class OrderItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    protected static ?string $title = 'Позиції';

    protected static ?string $modelLabel = 'Позиція';

    protected static ?string $pluralModelLabel = 'Позиції';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with([
                'product',
                'bundle.items.product.variants',
            ]))
            ->columns([
                ImageColumn::make('catalog_photo')
                    ->label('')
                    ->getStateUsing(fn (OrderItem $record): ?string => $record->adminCatalogPhotoUrl())
                    ->checkFileExistence(false)
                    ->imageWidth(48)
                    ->imageHeight(48)
                    ->square(),
                TextColumn::make('title_snapshot')
                    ->label('Товар / комплект')
                    ->url(function (OrderItem $record): ?string {
                        if ($record->bundle_id !== null && (int) $record->bundle_id > 0) {
                            return BundleResource::getUrl('edit', ['record' => $record->bundle_id]);
                        }

                        $productId = (int) ($record->product_id ?? 0);
                        if ($productId > 0) {
                            return ProductResource::getUrl('edit', ['record' => $productId]);
                        }

                        return null;
                    })
                    ->openUrlInNewTab(),
                TextColumn::make('admin_options')
                    ->label('Опції')
                    ->getStateUsing(fn (OrderItem $record): string => $record->adminOptionsSummary()),
                TextColumn::make('qty')
                    ->label('К-сть')
                    ->alignEnd(),
                TextColumn::make('price')
                    ->label('Ціна')
                    ->money('UAH')
                    ->alignEnd(),
                TextColumn::make('line_total')
                    ->label('Разом')
                    ->money('UAH')
                    ->alignEnd(),
            ])
            ->defaultSort('id')
            ->paginated([10, 25, 50]);
    }
}
