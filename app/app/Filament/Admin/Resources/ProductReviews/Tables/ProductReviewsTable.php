<?php

namespace App\Filament\Admin\Resources\ProductReviews\Tables;

use App\Models\ProductReview;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class ProductReviewsTable
{
    private const BODY_PREVIEW = 120;

    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('product:id,title,slug'))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('rating')
                    ->label('Оцінка')
                    ->formatStateUsing(fn (int $state): string => str_repeat('★', $state).str_repeat('☆', ProductReview::RATING_MAX - $state))
                    ->color('warning')
                    ->sortable(),
                TextColumn::make('author_name')
                    ->label('Автор')
                    ->description(fn (ProductReview $record): string => $record->created_at?->format('d.m.Y H:i') ?? '')
                    ->searchable(),
                TextColumn::make('product.title')
                    ->label('Товар')
                    ->formatStateUsing(fn (ProductReview $record): string => mt((string) $record->product?->title))
                    ->url(fn (ProductReview $record): ?string => $record->product ? route('catalog.show', $record->product->slug).'#reviews' : null, shouldOpenInNewTab: true)
                    ->wrap(),
                TextColumn::make('body')
                    ->label('Відгук')
                    ->formatStateUsing(fn (string $state): string => Str::limit($state, self::BODY_PREVIEW))
                    ->wrap()
                    ->searchable(),
                IconColumn::make('is_verified_buyer')
                    ->label('Купував')
                    ->boolean()
                    ->toggleable(),
                TextColumn::make('status')
                    ->label('Статус')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => ProductReview::STATUS_LABELS[$state] ?? $state)
                    ->color(fn (string $state): string => match ($state) {
                        ProductReview::STATUS_APPROVED => 'success',
                        ProductReview::STATUS_PENDING => 'warning',
                        default => 'gray',
                    }),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Статус')
                    ->options(ProductReview::STATUS_LABELS),
                SelectFilter::make('rating')
                    ->label('Оцінка')
                    ->options(collect(range(ProductReview::RATING_MAX, ProductReview::RATING_MIN))->mapWithKeys(fn (int $r): array => [$r => str_repeat('★', $r)])->all()),
            ])
            ->recordActions([
                ProductReviewActions::approve(),
                ActionGroup::make([
                    ProductReviewActions::reject(),
                    EditAction::make(),
                    DeleteAction::make(),
                ]),
            ]);
    }
}
