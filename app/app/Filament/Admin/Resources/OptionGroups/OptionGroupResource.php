<?php

namespace App\Filament\Admin\Resources\OptionGroups;

use App\Filament\Admin\Resources\OptionGroups\Pages\CreateOptionGroup;
use App\Filament\Admin\Resources\OptionGroups\Pages\EditOptionGroup;
use App\Filament\Admin\Resources\OptionGroups\Pages\ListOptionGroups;
use App\Filament\Admin\Resources\OptionGroups\Schemas\OptionGroupForm;
use App\Filament\Admin\Resources\OptionGroups\Tables\OptionGroupsTable;
use App\Models\OptionGroup;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class OptionGroupResource extends Resource
{
    protected static ?string $model = OptionGroup::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $navigationLabel = 'Групи опцій';

    protected static ?string $modelLabel = 'Група опцій';

    protected static ?string $pluralModelLabel = 'Групи опцій';

    protected static string|UnitEnum|null $navigationGroup = 'Довідники';

    protected static bool $shouldRegisterNavigation = true;

    public static function form(Schema $schema): Schema
    {
        return OptionGroupForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return OptionGroupsTable::configure($table);
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
            'index' => ListOptionGroups::route('/'),
            'create' => CreateOptionGroup::route('/create'),
            'edit' => EditOptionGroup::route('/{record}/edit'),
        ];
    }
}
