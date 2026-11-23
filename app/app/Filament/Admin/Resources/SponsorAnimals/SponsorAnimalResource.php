<?php

namespace App\Filament\Admin\Resources\SponsorAnimals;

use App\Filament\Admin\Resources\SponsorAnimals\Pages\CreateSponsorAnimal;
use App\Filament\Admin\Resources\SponsorAnimals\Pages\EditSponsorAnimal;
use App\Filament\Admin\Resources\SponsorAnimals\Pages\ListSponsorAnimals;
use App\Filament\Admin\Resources\SponsorAnimals\Schemas\SponsorAnimalForm;
use App\Filament\Admin\Resources\SponsorAnimals\Tables\SponsorAnimalsTable;
use App\Models\SponsorAnimal;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class SponsorAnimalResource extends Resource
{
    protected static ?string $model = SponsorAnimal::class;

    protected static ?string $slug = 'sponsor-animals';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSparkles;

    protected static ?string $navigationLabel = 'Тварини під опікою';

    protected static ?string $modelLabel = 'Тварина';

    protected static ?string $pluralModelLabel = 'Тварини під опікою';

    protected static string|UnitEnum|null $navigationGroup = 'Віртуальна опіка';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return SponsorAnimalForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SponsorAnimalsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSponsorAnimals::route('/'),
            'create' => CreateSponsorAnimal::route('/create'),
            'edit' => EditSponsorAnimal::route('/{record}/edit'),
        ];
    }
}
