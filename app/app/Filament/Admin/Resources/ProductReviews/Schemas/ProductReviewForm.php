<?php

namespace App\Filament\Admin\Resources\ProductReviews\Schemas;

use App\Models\ProductReview;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ProductReviewForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Відгук')
                    ->columns(['default' => 1, 'md' => 2])
                    ->schema([
                        TextEntry::make('product_link')
                            ->label('Товар')
                            ->state(fn (?ProductReview $record): string => mt((string) $record?->product?->title))
                            ->url(fn (?ProductReview $record): ?string => $record?->product ? route('catalog.show', $record->product->slug).'#reviews' : null, shouldOpenInNewTab: true)
                            ->color('primary')
                            ->columnSpanFull(),
                        TextInput::make('author_name')
                            ->label('Ім\'я')
                            ->helperText('На сайті видно лише перше слово (ім\'я).')
                            ->required()
                            ->maxLength(80),
                        Select::make('rating')
                            ->label('Оцінка')
                            ->options(collect(range(ProductReview::RATING_MAX, ProductReview::RATING_MIN))->mapWithKeys(fn (int $r): array => [$r => str_repeat('★', $r)])->all())
                            ->required(),
                        Textarea::make('body')
                            ->label('Текст')
                            ->rows(5)
                            ->required()
                            ->maxLength(2000)
                            ->columnSpanFull(),
                        Select::make('status')
                            ->label('Статус')
                            ->options(ProductReview::STATUS_LABELS)
                            ->required(),
                        Toggle::make('is_verified_buyer')
                            ->label('Покупка в ZOOGLE')
                            ->helperText('Ставиться автоматично, якщо автор купував цей товар.')
                            ->disabled()
                            ->dehydrated(false),
                    ]),
            ]);
    }
}
