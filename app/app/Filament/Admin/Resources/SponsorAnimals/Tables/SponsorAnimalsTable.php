<?php

namespace App\Filament\Admin\Resources\SponsorAnimals\Tables;

use App\Models\SponsorAnimal;
use App\Models\Sponsorship;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SponsorAnimalsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->withCount([
                'sponsorships as active_sponsors_count' => fn (Builder $q) => $q
                    ->where('status', Sponsorship::STATUS_ACTIVE)
                    ->whereDate('ends_at', '>=', today()),
            ]))
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->columns([
                ImageColumn::make('cover')
                    ->label('')
                    ->state(fn (SponsorAnimal $record): ?string => $record->coverImageUrl())
                    ->square()
                    ->imageSize(48),
                TextColumn::make('name')
                    ->label('Ім\'я')
                    ->description(fn (SponsorAnimal $record): ?string => $record->species)
                    ->searchable()
                    ->sortable(),
                TextColumn::make('price_month')->label('1 міс.')->money('UAH'),
                TextColumn::make('price_half_year')->label('6 міс.')->money('UAH'),
                TextColumn::make('price_year')->label('1 рік')->money('UAH'),
                TextColumn::make('active_sponsors_count')
                    ->label('Опікунів зараз')
                    ->badge()
                    ->color(fn (int $state): string => $state > 0 ? 'success' : 'gray'),
                IconColumn::make('is_active')
                    ->label('На сайті')
                    ->boolean(),
            ])
            ->filters([
                TernaryFilter::make('is_active')->label('На сайті'),
            ])
            ->recordActions([
                Action::make('open')
                    ->label('На сайті')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn (SponsorAnimal $record): string => route('sponsorship.show', $record))
                    ->openUrlInNewTab()
                    ->visible(fn (SponsorAnimal $record): bool => $record->is_active),
                EditAction::make(),
            ]);
    }
}
