<?php

namespace App\Filament\Admin\Resources\Sponsorships\Tables;

use App\Models\Sponsorship;
use App\Support\PublicStorageUrl;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class SponsorshipsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('animal'))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('id')
                    ->label('Заявка')
                    ->formatStateUsing(fn (Sponsorship $record): string => $record->reference())
                    ->description(fn (Sponsorship $record): string => $record->created_at?->format('d.m.Y H:i') ?? '')
                    ->sortable(),
                ImageColumn::make('animal_cover')
                    ->label('')
                    ->state(fn (Sponsorship $record): ?string => $record->animal?->coverImageUrl())
                    ->square()
                    ->imageSize(44),
                TextColumn::make('animal_name')
                    ->label('Тварина')
                    ->description(fn (Sponsorship $record): ?string => $record->animal?->species)
                    ->searchable(),
                TextColumn::make('sponsor_name')
                    ->label('Опікун')
                    ->description(fn (Sponsorship $record): string => trim($record->email.' '.($record->phone ?? '')))
                    ->searchable(['sponsor_name', 'email', 'phone']),
                TextColumn::make('term')
                    ->label('Термін')
                    ->formatStateUsing(fn (Sponsorship $record): string => $record->termLabel())
                    ->description(fn (Sponsorship $record): string => number_format((float) $record->amount, 0, ',', ' ').' ₴'),
                TextColumn::make('status')
                    ->label('Статус')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => Sponsorship::STATUS_LABELS[$state] ?? $state)
                    ->color(fn (string $state): string => match ($state) {
                        Sponsorship::STATUS_ACTIVE => 'success',
                        Sponsorship::STATUS_PENDING => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('ends_at')
                    ->label('Діє до')
                    ->date('d.m.Y')
                    ->placeholder('—')
                    ->sortable()
                    ->color(fn (Sponsorship $record): ?string => $record->ends_at?->isPast() ? 'danger' : null),
                ImageColumn::make('photo_path')
                    ->label('Фото')
                    ->state(fn (Sponsorship $record): ?string => filled($record->photo_path) ? PublicStorageUrl::forPath($record->photo_path) : null)
                    ->circular()
                    ->imageSize(40),
                TextColumn::make('photo_status')
                    ->label('Стан фото')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => Sponsorship::PHOTO_LABELS[$state] ?? $state)
                    ->color(fn (string $state): string => match ($state) {
                        Sponsorship::PHOTO_APPROVED => 'success',
                        Sponsorship::PHOTO_PENDING => 'warning',
                        Sponsorship::PHOTO_REJECTED => 'danger',
                        default => 'gray',
                    })
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Статус')
                    ->options(Sponsorship::STATUS_LABELS),
                SelectFilter::make('photo_status')
                    ->label('Фото')
                    ->options(Sponsorship::PHOTO_LABELS),
                SelectFilter::make('sponsor_animal_id')
                    ->label('Тварина')
                    ->relationship('animal', 'name'),
            ])
            ->recordActions([
                SponsorshipActions::confirmPayment(),
                ActionGroup::make([
                    SponsorshipActions::approvePhoto(),
                    SponsorshipActions::rejectPhoto(),
                    EditAction::make(),
                    SponsorshipActions::cancel(),
                ]),
            ]);
    }
}
