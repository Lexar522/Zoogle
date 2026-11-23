<?php

namespace App\Filament\Admin\Resources\ProductQuestions;

use App\Filament\Admin\Resources\ProductQuestions\Pages\EditProductQuestion;
use App\Filament\Admin\Resources\ProductQuestions\Pages\ListProductQuestions;
use App\Filament\Admin\Resources\ProductQuestions\Schemas\ProductQuestionForm;
use App\Filament\Admin\Resources\ProductQuestions\Tables\ProductQuestionsTable;
use App\Models\ProductQuestion;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema as DbSchema;
use Illuminate\Support\Str;
use UnitEnum;

class ProductQuestionResource extends Resource
{
    protected static ?string $model = ProductQuestion::class;

    protected static ?string $slug = 'product-questions';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQuestionMarkCircle;

    protected static ?string $navigationLabel = 'Питання покупців';

    protected static ?string $modelLabel = 'Питання';

    protected static ?string $pluralModelLabel = 'Питання покупців';

    protected static string|UnitEnum|null $navigationGroup = 'Каталог';

    protected static ?int $navigationSort = 6;

    public static function getNavigationBadge(): ?string
    {
        if (! DbSchema::hasTable('product_questions')) {
            return null;
        }

        $waiting = ProductQuestion::query()->where('status', ProductQuestion::STATUS_PENDING)->count();

        return $waiting > 0 ? (string) $waiting : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Питання, на які потрібно відповісти';
    }

    public static function getRecordTitle(?Model $record): string
    {
        return $record instanceof ProductQuestion
            ? 'Питання від '.$record->author_name.': '.Str::limit($record->body, 50)
            : parent::getRecordTitle($record);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return ProductQuestionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ProductQuestionsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProductQuestions::route('/'),
            'edit' => EditProductQuestion::route('/{record}/edit'),
        ];
    }
}
