<?php

namespace App\Filament\Admin\Resources\CareArticles;

use App\Filament\Admin\Resources\CareArticles\Pages\CreateCareArticle;
use App\Filament\Admin\Resources\CareArticles\Pages\EditCareArticle;
use App\Filament\Admin\Resources\CareArticles\Pages\ListCareArticles;
use App\Filament\Admin\Resources\CareArticles\Schemas\CareArticleForm;
use App\Filament\Admin\Resources\CareArticles\Tables\CareArticlesTable;
use App\Models\ProductCareArticle;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class CareArticleResource extends Resource
{
    protected static ?string $model = ProductCareArticle::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBookOpen;

    protected static ?string $navigationLabel = 'Статті';

    protected static ?string $modelLabel = 'Стаття';

    protected static ?string $pluralModelLabel = 'Статті';

    protected static string|UnitEnum|null $navigationGroup = 'Каталог';

    protected static ?int $navigationSort = 20;

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Schema $schema): Schema
    {
        return CareArticleForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CareArticlesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCareArticles::route('/'),
            'create' => CreateCareArticle::route('/create'),
            'edit' => EditCareArticle::route('/{record}/edit'),
        ];
    }
}
