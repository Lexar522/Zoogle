<?php

namespace App\Filament\Admin\Resources\OptionValues;

use App\Filament\Admin\Resources\OptionValues\Pages\CreateOptionValue;
use App\Filament\Admin\Resources\OptionValues\Pages\EditOptionValue;
use App\Filament\Admin\Resources\OptionValues\Pages\ListOptionValues;
use App\Filament\Admin\Resources\OptionValues\Schemas\OptionValueForm;
use App\Filament\Admin\Resources\OptionValues\Tables\OptionValuesTable;
use App\Models\OptionValue;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class OptionValueResource extends Resource
{
    protected static ?string $model = OptionValue::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $navigationLabel = 'Значення опцій';

    protected static ?string $modelLabel = 'Значення опції';

    protected static ?string $pluralModelLabel = 'Значення опцій';

    protected static string|UnitEnum|null $navigationGroup = 'Довідники';

    protected static bool $shouldRegisterNavigation = false;

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return OptionValueForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return OptionValuesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOptionValues::route('/'),
            'create' => CreateOptionValue::route('/create'),
            'edit' => EditOptionValue::route('/{record}/edit'),
        ];
    }
}
