<?php

namespace App\Filament\Admin\Resources\Sponsorships\Schemas;

use App\Filament\Admin\Resources\SponsorAnimals\SponsorAnimalResource;
use App\Models\ShopSponsorshipSetting;
use App\Models\Sponsorship;
use App\Support\PublicStorageUrl;
use App\Support\ShopPrice;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class SponsorshipForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                self::animalSection(),
                Section::make('Опікун')
                    ->columns(['default' => 1, 'md' => 3])
                    ->schema([
                        TextInput::make('sponsor_name')->label('Ім\'я')->required()->maxLength(120),
                        TextInput::make('email')->label('Email')->email()->required()->maxLength(160),
                        TextInput::make('phone')->label('Телефон')->maxLength(50),
                        Textarea::make('message')->label('Побажання')->rows(2)->maxLength(1000)->columnSpanFull(),
                        Toggle::make('show_publicly')->label('Показувати ім\'я та фото на сайті'),
                    ]),
                Section::make('Опікунство')
                    ->columns(['default' => 1, 'md' => 3])
                    ->schema([
                        TextInput::make('animal_name')->label('Тварина')->disabled()->dehydrated(false),
                        Select::make('term')
                            ->label('Термін')
                            ->options(collect(array_keys(Sponsorship::TERMS))->mapWithKeys(fn (string $t): array => [$t => ShopSponsorshipSetting::termLabel($t)])->all())
                            ->disabled()
                            ->dehydrated(false),
                        TextInput::make('amount')->label('Сума')->suffix('₴')->disabled()->dehydrated(false),
                        Select::make('status')
                            ->label('Статус')
                            ->options(Sponsorship::STATUS_LABELS)
                            ->required(),
                        DatePicker::make('starts_at')->label('Початок'),
                        DatePicker::make('ends_at')->label('Діє до')->helperText('Можна продовжити вручну.'),
                    ]),
                Section::make('Фото опікуна')
                    ->visible(fn (?Sponsorship $record): bool => filled($record?->photo_path))
                    ->schema([
                        Grid::make(['default' => 1, 'md' => 2])->schema([
                            ImageEntry::make('photo_preview')
                                ->hiddenLabel()
                                ->state(fn (?Sponsorship $record): ?string => PublicStorageUrl::forPath($record?->photo_path))
                                ->imageHeight(220),
                            Select::make('photo_status')
                                ->label('Стан фото')
                                ->options(Sponsorship::PHOTO_LABELS)
                                ->helperText('На сторінці тварини показується лише схвалене фото.'),
                        ]),
                    ]),
            ]);
    }

    /**
     * Яку тварину обрав опікун: фото, ім'я, вид, опис, ціни й посилання.
     */
    private static function animalSection(): Section
    {
        return Section::make('Тварина')
            ->schema([
                Grid::make(['default' => 1, 'md' => 4])->schema([
                    ImageEntry::make('animal_cover')
                        ->hiddenLabel()
                        ->state(fn (?Sponsorship $record): ?string => $record?->animal?->coverImageUrl())
                        ->imageHeight(150)
                        ->square()
                        ->visible(fn (?Sponsorship $record): bool => filled($record?->animal?->coverImageUrl())),
                    Grid::make(1)
                        ->columnSpan(['default' => 1, 'md' => 3])
                        ->schema([
                            TextEntry::make('animal_title')
                                ->hiddenLabel()
                                ->state(fn (?Sponsorship $record): string => trim(($record?->animal_name ?? '').($record?->animal?->species ? ' · '.$record->animal->species : '')))
                                ->weight('bold')
                                ->size('lg'),
                            TextEntry::make('animal_excerpt')
                                ->hiddenLabel()
                                ->state(fn (?Sponsorship $record): ?string => $record?->animal?->excerpt)
                                ->color('gray')
                                ->visible(fn (?Sponsorship $record): bool => filled($record?->animal?->excerpt)),
                            TextEntry::make('animal_prices')
                                ->label('Ціни опікунства')
                                ->state(fn (?Sponsorship $record): string => self::pricesLine($record))
                                ->visible(fn (?Sponsorship $record): bool => $record?->animal !== null),
                            TextEntry::make('animal_links')
                                ->hiddenLabel()
                                ->state(fn (?Sponsorship $record): string => $record?->animal ? 'Відкрити на сайті' : 'Тварину видалено — збережено лише ім\'я')
                                ->url(fn (?Sponsorship $record): ?string => $record?->animal ? route('sponsorship.show', $record->animal) : null, shouldOpenInNewTab: true)
                                ->color(fn (?Sponsorship $record): string => $record?->animal ? 'primary' : 'gray')
                                ->icon(fn (?Sponsorship $record): ?string => $record?->animal ? 'heroicon-o-arrow-top-right-on-square' : null),
                            TextEntry::make('animal_edit')
                                ->hiddenLabel()
                                ->state('Редагувати тварину')
                                ->url(fn (?Sponsorship $record): ?string => $record?->animal ? SponsorAnimalResource::getUrl('edit', ['record' => $record->animal]) : null)
                                ->color('primary')
                                ->icon('heroicon-o-pencil-square')
                                ->visible(fn (?Sponsorship $record): bool => $record?->animal !== null),
                        ]),
                ]),
            ]);
    }

    private static function pricesLine(?Sponsorship $record): string
    {
        $terms = $record?->animal?->offeredTerms() ?? [];

        return collect($terms)
            ->map(fn (float $price, string $term): string => ShopSponsorshipSetting::termLabel($term).' — '.ShopPrice::amount($price).' ₴')
            ->implode(' · ') ?: '—';
    }
}
