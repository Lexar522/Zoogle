<?php

namespace App\Filament\Admin\Resources\Promotions;

use App\Filament\Admin\Resources\Promotions\Pages\CreatePromotion;
use App\Filament\Admin\Resources\Promotions\Pages\EditPromotion;
use App\Filament\Admin\Resources\Promotions\Pages\ListPromotions;
use App\Filament\Admin\Resources\Promotions\RelationManagers\PromotionTargetsRelationManager;
use App\Filament\Admin\Resources\Promotions\Schemas\PromotionForm;
use App\Filament\Admin\Resources\Promotions\Tables\PromotionsTable;
use App\Models\Promotion;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class PromotionResource extends Resource
{
    protected static ?string $model = Promotion::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSparkles;

    protected static ?string $navigationLabel = 'Акції';

    protected static ?string $modelLabel = 'Акція';

    protected static ?string $pluralModelLabel = 'Акції';

    protected static string|UnitEnum|null $navigationGroup = 'Маркетинг';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return PromotionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PromotionsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            PromotionTargetsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPromotions::route('/'),
            'create' => CreatePromotion::route('/create'),
            'edit' => EditPromotion::route('/{record}/edit'),
        ];
    }
}
