<?php

namespace App\Filament\Admin\Resources\ProductReviews;

use App\Filament\Admin\Resources\ProductReviews\Pages\EditProductReview;
use App\Filament\Admin\Resources\ProductReviews\Pages\ListProductReviews;
use App\Filament\Admin\Resources\ProductReviews\Schemas\ProductReviewForm;
use App\Filament\Admin\Resources\ProductReviews\Tables\ProductReviewsTable;
use App\Models\ProductReview;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Schema as DbSchema;
use UnitEnum;

class ProductReviewResource extends Resource
{
    protected static ?string $model = ProductReview::class;

    protected static ?string $slug = 'product-reviews';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedStar;

    protected static ?string $navigationLabel = 'Відгуки';

    protected static ?string $modelLabel = 'Відгук';

    protected static ?string $pluralModelLabel = 'Відгуки';

    protected static string|UnitEnum|null $navigationGroup = 'Каталог';

    protected static ?int $navigationSort = 5;

    protected static ?string $recordTitleAttribute = 'author_name';

    public static function getNavigationBadge(): ?string
    {
        if (! DbSchema::hasTable('product_reviews')) {
            return null;
        }

        $waiting = ProductReview::query()->where('status', ProductReview::STATUS_PENDING)->count();

        return $waiting > 0 ? (string) $waiting : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Відгуки, що чекають перевірки';
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return ProductReviewForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ProductReviewsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProductReviews::route('/'),
            'edit' => EditProductReview::route('/{record}/edit'),
        ];
    }
}
