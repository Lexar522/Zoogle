<?php

namespace App\Filament\Admin\Resources\Bundles;

use App\Filament\Admin\Resources\Bundles\Pages\CreateBundle;
use App\Filament\Admin\Resources\Bundles\Pages\EditBundle;
use App\Filament\Admin\Resources\Bundles\Pages\ListBundles;
use App\Filament\Admin\Resources\Bundles\Schemas\BundleForm;
use App\Filament\Admin\Resources\Bundles\Tables\BundlesTable;
use App\Models\Bundle;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class BundleResource extends Resource
{
    protected static ?string $model = Bundle::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedGift;

    protected static ?string $navigationLabel = 'Комплекти';

    protected static ?string $modelLabel = 'Комплект';

    protected static ?string $pluralModelLabel = 'Комплекти';

    protected static string|UnitEnum|null $navigationGroup = 'Каталог';

    public static function form(Schema $schema): Schema
    {
        return BundleForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return BundlesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBundles::route('/'),
            'create' => CreateBundle::route('/create'),
            'edit' => EditBundle::route('/{record}/edit'),
        ];
    }
}
