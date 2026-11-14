<?php

namespace App\Filament\Admin\Resources\CareArticles\Schemas;

use App\Filament\Admin\Schemas\CatalogCategoryCascadeFields;
use App\Models\ProductCareArticle;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class CareArticleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Напрямок')
                    ->description('Єдине місце для статей. Оберіть шлях у каталозі — наприклад Тварини → Гризуни → Кролик. На товарах цієї гілки стаття з’явиться сама.')
                    ->schema(CatalogCategoryCascadeFields::articleCategoryLevelFields()),
                Section::make('Стаття')
                    ->schema([
                        Grid::make([
                            'default' => 1,
                            'md' => 2,
                        ])
                            ->schema([
                                TextInput::make('title')
                                    ->label('Заголовок')
                                    ->required()
                                    ->maxLength(255)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(fn (?string $state, Set $set) => $set('slug', Str::slug((string) $state))),
                                TextInput::make('slug')
                                    ->label('Слаг')
                                    ->maxLength(255)
                                    ->helperText('URL: /porady/слаг. Порожньо — з назви.'),
                            ]),
                        Textarea::make('excerpt')
                            ->label('Короткий вступ')
                            ->rows(2)
                            ->maxLength(500)
                            ->helperText('Картка в бібліотеці та на товарі.'),
                        RichEditor::make('body')
                            ->label('Текст статті')
                            ->fileAttachments(true)
                            ->fileAttachmentsDisk('public')
                            ->fileAttachmentsDirectory(fn (?ProductCareArticle $record): string => 'care-articles/'.($record?->id ?? 'draft'))
                            ->dehydrateStateUsing(fn (mixed $state): ?string => ProductCareArticle::normalizeRichEditorValueForStorage($state))
                            ->extraAttributes(['style' => 'min-height: 18rem'])
                            ->helperText('Можна форматувати текст, вставляти фото та YouTube-посилання.'),
                        Grid::make([
                            'default' => 1,
                            'md' => 3,
                        ])
                            ->schema([
                                TextInput::make('sort_order')
                                    ->label('Порядок')
                                    ->numeric()
                                    ->minValue(0)
                                    ->default(0),
                                Toggle::make('is_published')
                                    ->label('Опубліковано')
                                    ->default(true),
                                DateTimePicker::make('published_at')
                                    ->label('Дата публікації')
                                    ->helperText('Порожньо — показувати одразу.'),
                            ]),
                    ]),
            ]);
    }
}
