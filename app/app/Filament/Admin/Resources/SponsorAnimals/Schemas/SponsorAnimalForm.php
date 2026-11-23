<?php

namespace App\Filament\Admin\Resources\SponsorAnimals\Schemas;

use App\Filament\Admin\Resources\Products\Schemas\ProductForm;
use App\Models\ProductCareArticle;
use App\Models\ShopCharitySetting;
use App\Models\ShopSponsorshipSetting;
use App\Support\ListingPhotoUpload;
use Closure;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class SponsorAnimalForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Тварина')
                    ->schema([
                        Grid::make(['default' => 1, 'md' => 3])
                            ->schema([
                                TextInput::make('name')
                                    ->label('Ім\'я')
                                    ->required()
                                    ->maxLength(120)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(fn (?string $state, Set $set) => $set('slug', Str::slug((string) $state))),
                                TextInput::make('species')
                                    ->label('Вид')
                                    ->maxLength(120)
                                    ->placeholder('Кролик, папуга, їжак…'),
                                TextInput::make('slug')
                                    ->label('Адреса сторінки')
                                    ->maxLength(140)
                                    ->helperText('/opika/адреса. Порожньо — з імені.'),
                            ]),
                        Textarea::make('excerpt')
                            ->label('Короткий опис на картці')
                            ->rows(2)
                            ->maxLength(300),
                        RichEditor::make('story')
                            ->label('Історія тварини')
                            ->fileAttachments(true)
                            ->fileAttachmentsDisk('public')
                            ->fileAttachmentsDirectory('sponsor-animals/story')
                            ->dehydrateStateUsing(fn (mixed $state): ?string => ProductCareArticle::normalizeRichEditorValueForStorage($state))
                            ->extraAttributes(['style' => 'min-height: 16rem'])
                            ->helperText('Можна форматувати текст, вставляти фото та YouTube-посилання.'),
                    ]),
                Section::make('Фото та відео')
                    ->description('Перше фото — на картці в списку тварин. Порядок мініатюр = порядок на сторінці.')
                    ->schema([
                        ListingPhotoUpload::applyImageEditorIfAvailable(
                            ProductForm::applyListingPhotosLayout(
                                ListingPhotoUpload::make('photos')
                                    ->hiddenLabel()
                                    ->multiple()
                                    ->reorderable()
                                    ->appendFiles()
                                    ->disk('public')
                                    ->directory('sponsor-animals/photos')
                                    ->maxFiles(20)
                            )
                        ),
                    ]),
                Section::make('Ціни опікунства')
                    ->description('Порожнє поле — цей термін для тварини не пропонується.')
                    ->columns(['default' => 1, 'md' => 3])
                    ->schema([
                        self::priceField('price_month', ShopSponsorshipSetting::termLabel('month')),
                        self::priceField('price_half_year', ShopSponsorshipSetting::termLabel('half_year')),
                        self::priceField('price_year', ShopSponsorshipSetting::termLabel('year')),
                    ]),
                Section::make('Показ')
                    ->columns(['default' => 1, 'md' => 3])
                    ->schema([
                        Toggle::make('is_active')
                            ->label('Показувати на сайті')
                            ->default(true),
                        TextInput::make('sort_order')
                            ->label('Порядок')
                            ->numeric()
                            ->minValue(0)
                            ->default(0),
                        TextInput::make('jar_url')
                            ->label('Окрема банка (за бажанням)')
                            ->url()
                            ->maxLength(500)
                            ->placeholder('https://send.monobank.ua/jar/...')
                            ->rule(fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                                if (filled($value) && ! ShopCharitySetting::isAllowedJarUrl((string) $value)) {
                                    $fail('Потрібне посилання Monobank, що починається з https://send.monobank.ua/');
                                }
                            })
                            ->helperText('Порожньо — загальна банка з налаштувань опіки.'),
                    ]),
            ]);
    }

    private static function priceField(string $name, string $label): TextInput
    {
        return TextInput::make($name)
            ->label($label)
            ->numeric()
            ->minValue(1)
            ->maxValue(1000000)
            ->suffix('₴');
    }
}
