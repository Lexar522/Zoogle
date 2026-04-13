<?php

namespace App\Filament\Admin\Resources\Products\Schemas;

use App\Filament\Admin\Schemas\CatalogCategoryCascadeFields;
use App\Models\OptionGroup;
use App\Models\OptionValue;
use App\Models\Product;
use App\Support\CatalogCategoryTree;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class ProductForm
{
    /**
     * Кастомний макет галереї (див. listing-photos-upload-layout): мініатюри в ряд + зона додавання.
     */
    public static function applyListingPhotosLayout(FileUpload $upload): FileUpload
    {
        return $upload
            ->itemPanelAspectRatio('1:1')
            ->imagePreviewHeight('88')
            ->placeholder(
                'Перетягніть зображення сюди<br><span class="filepond--label-action">Обрати файли</span>'
            )
            ->extraAttributes(['class' => 'fi-listing-photos-layout'], merge: true);
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Grid::make([
                    'default' => 1,
                    'lg' => 2,
                ])
                    ->schema([
                        Section::make('Назва та ідентифікація')
                            ->schema([
                                TextInput::make('title')
                                    ->label('Назва')
                                    ->required()
                                    ->maxLength(255)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(fn (?string $state, Set $set) => $set('slug', Str::slug((string) $state))),
                                TextInput::make('sku')
                                    ->label('Артикул')
                                    ->maxLength(120)
                                    ->placeholder('Необов’язково')
                                    ->helperText('Якщо порожньо — на сторінці товару не показується.')
                                    ->dehydrateStateUsing(fn (?string $state): ?string => filled(trim((string) $state)) ? trim((string) $state) : null),
                                TextInput::make('slug')
                                    ->label('Слаг')
                                    ->required()
                                    ->maxLength(255)
                                    ->helperText('Використовується в URL сторінки товару.')
                                    ->unique(ignoreRecord: true),
                            ])
                            ->columns(1),
                        Section::make('Категорії')
                            ->schema([
                                ...CatalogCategoryCascadeFields::listingCategoryLevelFields(),
                            ])
                            ->columns(1),
                    ]),
                Section::make('Опис та ціна')
                    ->columns(1)
                    ->schema([
                        RichEditor::make('short_description')
                            ->label('Короткий опис')
                            ->live()
                            ->fileAttachments(false)
                            ->toolbarButtons([
                                ['bold', 'italic', 'underline', 'strike', 'link'],
                                ['bulletList', 'orderedList'],
                                ['undo', 'redo'],
                            ])
                            ->helperText('Для картки в каталозі: наголос, списки, посилання.'),
                        RichEditor::make('description')
                            ->label('Опис')
                            ->live()
                            ->fileAttachments(false)
                            ->extraAttributes(['style' => 'min-height: 14rem'])
                            ->helperText('Повний опис на сторінці товару. Дозволені відносні посилання (наприклад /catalog).'),
                        TextInput::make('price')
                            ->label('Базова ціна')
                            ->required()
                            ->numeric()
                            ->minValue(0)
                            ->prefix('UAH'),
                    ]),
                Section::make('Публікація')
                    ->schema([
                        Toggle::make('is_available')
                            ->label('Показувати в каталозі')
                            ->helperText('Головний перемикач доступності картки товару.')
                            ->default(true)
                            ->required(),
                        DateTimePicker::make('published_at')
                            ->label('Опубліковано'),
                    ]),
                Section::make('Фото товару')
                    ->description('Базова галерея товару. Якщо для кольору задані свої фото — на вітрині вони матимуть пріоритет.')
                    ->schema([
                        self::applyListingPhotosLayout(
                            FileUpload::make('photos')
                                ->hiddenLabel()
                                ->image()
                                ->multiple()
                                ->reorderable()
                                ->appendFiles()
                                ->disk('public')
                                ->directory(fn (?Product $record): string => 'products/'.($record?->id ?? 'draft').'/photos')
                                ->maxFiles(20)
                                ->imageAspectRatio('1:1')
                                ->imageEditor()
                                ->imageEditorViewportWidth(480)
                                ->imageEditorViewportHeight(480)
                                ->helperText('Порядок мініатюр = порядок у галереї на сайті. Олівець — кадр 1:1.')
                        ),
                    ]),
                Section::make('Опції товару')
                    ->description('Оберіть тип опції з довідника, відмітьте значення галочками або додайте нові.')
                    ->schema([
                        Repeater::make('variant_options')
                            ->label('Групи опцій')
                            ->helperText('Перетягуйте групи мишкою: цей порядок буде на сторінці товару.')
                            ->reorderable()
                            ->collapsible()
                            ->collapsed(true)
                            ->itemLabel(function (array $state): ?string {
                                $groupId = $state['option_group_id'] ?? null;

                                if (! $groupId) {
                                    return 'Нова опція';
                                }

                                $group = OptionGroup::query()->find($groupId);
                                if (! $group) {
                                    return 'Опція';
                                }

                                $typeLabel = ($group->value_type ?? 'text') === 'color' ? 'Колір' : 'Текст';

                                return "{$group->name} ({$typeLabel})";
                            })
                            ->schema([
                                Select::make('option_value_type')
                                    ->label('Формат значення')
                                    ->required()
                                    ->default('text')
                                    ->options([
                                        'text' => 'Текст',
                                        'color' => 'Колір',
                                    ])
                                    ->afterStateHydrated(function (Get $get, Set $set, ?string $state): void {
                                        if (filled($state)) {
                                            return;
                                        }

                                        $groupId = (int) ($get('option_group_id') ?? 0);

                                        if ($groupId <= 0) {
                                            return;
                                        }

                                        $valueType = OptionGroup::query()->whereKey($groupId)->value('value_type');

                                        if (filled($valueType)) {
                                            $set('option_value_type', $valueType);
                                        }
                                    })
                                    ->live()
                                    ->afterStateUpdated(function (Set $set): void {
                                        $set('option_group_id', null);
                                        $set('option_value_ids', []);
                                        $set('value_prices', []);
                                        $set('new_values', []);
                                    }),
                                Select::make('option_group_id')
                                    ->label('Група опцій')
                                    ->required()
                                    ->placeholder('Оберіть групу з довідника')
                                    ->options(function (Get $get): array {
                                        $selectedType = $get('option_value_type');

                                        // З repeater-рядка «Опції товару» корінь форми — на два рівні вгору (не /data.*).
                                        $categoryGroupId = OptionGroup::systemCategoryGroupIdForCatalog();
                                        $levels = [];
                                        for ($i = 1; $i <= CatalogCategoryTree::MAX_DEPTH; $i++) {
                                            $key = 'category_level_'.$i.'_id';
                                            $levels[$i] = (int) ($get('../../'.$key) ?? $get('/data.'.$key) ?? 0);
                                        }
                                        $syn = CatalogCategoryTree::syntheticListingCategoryColumns($levels, $categoryGroupId);
                                        $variantOptionsRaw = $get('../../variant_options')
                                            ?? $get('/data.variant_options')
                                            ?? [];

                                        $listing = new Product([
                                            'product_type' => OptionGroup::CATALOG_PRODUCT_TYPE,
                                            'category_parent_value_id' => $syn['category_parent_value_id'],
                                            'category_value_id' => $syn['category_value_id'],
                                            'variant_options' => is_array($variantOptionsRaw) ? $variantOptionsRaw : [],
                                        ]);
                                        $scopeKeys = OptionGroup::productTypeScopeKeysForProduct($listing);

                                        $query = OptionGroup::query()
                                            ->where('slug', '!=', 'category')
                                            ->where('is_active', true)
                                            ->whereIn('product_type', $scopeKeys);

                                        if (filled($selectedType)) {
                                            $query->where('value_type', $selectedType);
                                        }

                                        $result = $query
                                            ->orderBy('name')
                                            ->get(['id', 'name', 'value_type'])
                                            ->mapWithKeys(function (OptionGroup $group): array {
                                                $typeLabel = ($group->value_type ?? 'text') === 'color' ? 'Колір' : 'Текст';

                                                return [$group->id => "{$group->name} ({$typeLabel})"];
                                            })
                                            ->all();

                                        $currentGroupId = (int) ($get('option_group_id') ?? 0);
                                        if ($currentGroupId > 0 && ! array_key_exists($currentGroupId, $result)) {
                                            $currentGroup = OptionGroup::query()->find($currentGroupId, ['id', 'name', 'value_type', 'is_active']);
                                            if ($currentGroup) {
                                                $typeLabel = ($currentGroup->value_type ?? 'text') === 'color' ? 'Колір' : 'Текст';
                                                $suffix = $currentGroup->is_active ? '' : ' (неактивна)';
                                                $result[$currentGroup->id] = "{$currentGroup->name} ({$typeLabel}){$suffix}";
                                            }
                                        }

                                        return $result;
                                    })
                                    ->searchable()
                                    ->searchPrompt('Введіть назву групи опцій')
                                    ->searchingMessage('Пошук...')
                                    ->noSearchResultsMessage('Нічого не знайдено')
                                    ->noOptionsMessage('Немає доступних груп опцій. Створи їх у довіднику "Групи опцій".')
                                    ->live()
                                    ->afterStateUpdated(function (Set $set, $state): void {
                                        $set('option_value_ids', []);
                                        $set('value_prices', []);
                                        $set('new_values', []);
                                        $gid = (int) $state;
                                        if ($gid > 0) {
                                            $valueType = OptionGroup::query()->whereKey($gid)->value('value_type');
                                            if (filled($valueType) && in_array($valueType, ['text', 'color'], true)) {
                                                $set('option_value_type', $valueType);
                                            }
                                        }
                                    }),
                                CheckboxList::make('option_value_ids')
                                    ->label('Значення (галочки)')
                                    ->allowHtml()
                                    ->live()
                                    ->helperText(function (Get $get): ?string {
                                        $groupId = (int) ($get('option_group_id') ?? 0);

                                        if ($groupId <= 0) {
                                            return null;
                                        }

                                        $group = OptionGroup::query()->find($groupId);

                                        if (! $group) {
                                            return null;
                                        }

                                        if (($group->selection_mode ?? 'single') === 'single') {
                                            return 'Режим групи — один вибір для покупця на сайті; у картці можна відмітити всі доступні значення.';
                                        }

                                        return 'Можна відмітити кілька значень — усі збережуться в картці товару.';
                                    })
                                    ->options(function (Get $get): array {
                                        $groupId = $get('option_group_id');

                                        if (blank($groupId)) {
                                            return [];
                                        }

                                        return OptionValue::query()
                                            ->where('option_group_id', (int) $groupId)
                                            ->where('is_active', true)
                                            ->orderBy('sort_order')
                                            ->get()
                                            ->mapWithKeys(fn (OptionValue $value): array => [
                                                $value->id => (function () use ($value): string {
                                                    $label = e($value->name);

                                                    if (! filled($value->color_hex)) {
                                                        return $label;
                                                    }

                                                    $dot = '<span style="display:inline-block;width:12px;height:12px;border-radius:9999px;border:1px solid #d1d5db;background:'.e((string) $value->color_hex).';margin-right:8px;vertical-align:middle;"></span>';

                                                    return $dot.$label;
                                                })(),
                                            ])
                                            ->all();
                                    })
                                    ->columns(2),
                                Repeater::make('value_prices')
                                    ->label('Доплата за значення (на цей товар)')
                                    ->helperText('Необов’язково. Задайте доплату в ₴ лише для цього товару; у довіднику залишаються лише назви значень.')
                                    ->schema([
                                        Select::make('option_value_id')
                                            ->label('Значення')
                                            ->required()
                                            ->options(function (Get $get): array {
                                                $groupId = (int) ($get('../../option_group_id') ?? 0);
                                                $selectedIds = collect($get('../../option_value_ids') ?? [])
                                                    ->map(fn ($id) => (int) $id)
                                                    ->filter(fn (int $id) => $id > 0)
                                                    ->values()
                                                    ->all();
                                                if ($groupId <= 0 || $selectedIds === []) {
                                                    return [];
                                                }

                                                return OptionValue::query()
                                                    ->where('option_group_id', $groupId)
                                                    ->whereIn('id', $selectedIds)
                                                    ->where('is_active', true)
                                                    ->orderBy('sort_order')
                                                    ->pluck('name', 'id')
                                                    ->all();
                                            })
                                            ->searchable(),
                                        TextInput::make('price_addon')
                                            ->label('Доплата (₴)')
                                            ->numeric()
                                            ->minValue(0)
                                            ->prefix('UAH'),
                                    ])
                                    ->defaultItems(0)
                                    ->addActionLabel('Додати доплату')
                                    ->columns(2)
                                    ->visible(fn (Get $get): bool => (int) ($get('option_group_id') ?? 0) > 0),
                                Repeater::make('value_photos')
                                    ->label('Фото для значень опції')
                                    ->helperText('Для кольору можна прив’язати кілька фото до конкретного значення. На вітрині вони перемикатимуть галерею.')
                                    ->visible(function (Get $get): bool {
                                        $groupId = (int) ($get('option_group_id') ?? 0);
                                        if ($groupId <= 0) {
                                            return false;
                                        }

                                        return OptionGroup::query()
                                            ->whereKey($groupId)
                                            ->where('value_type', 'color')
                                            ->exists();
                                    })
                                    ->schema([
                                        Select::make('option_value_id')
                                            ->label('Значення кольору')
                                            ->required()
                                            ->options(function (Get $get): array {
                                                $groupId = (int) ($get('../../option_group_id') ?? 0);
                                                $selectedIds = collect($get('../../option_value_ids') ?? [])
                                                    ->map(fn ($id) => (int) $id)
                                                    ->filter(fn (int $id) => $id > 0)
                                                    ->values()
                                                    ->all();
                                                if ($groupId <= 0 || $selectedIds === []) {
                                                    return [];
                                                }

                                                return OptionValue::query()
                                                    ->where('option_group_id', $groupId)
                                                    ->whereIn('id', $selectedIds)
                                                    ->where('is_active', true)
                                                    ->orderBy('sort_order')
                                                    ->pluck('name', 'id')
                                                    ->all();
                                            })
                                            ->searchable(),
                                        self::applyListingPhotosLayout(
                                            FileUpload::make('photos')
                                                ->label('Фото цього кольору')
                                                ->image()
                                                ->multiple()
                                                ->reorderable()
                                                ->appendFiles()
                                                ->disk('public')
                                                ->directory(fn (?Product $record): string => 'products/'.($record?->id ?? 'draft').'/option-value-photos')
                                                ->maxFiles(20)
                                                ->imageAspectRatio('1:1')
                                                ->imageEditor()
                                                ->imageEditorViewportWidth(480)
                                                ->imageEditorViewportHeight(480)
                                                ->helperText('Зверху — мініатюри; знизу — додати фото. Олівець — кадр 1:1 як у головній галереї.')
                                        ),
                                    ])
                                    ->columns(1),
                                Repeater::make('new_values')
                                    ->label('Додати нові значення (якщо треба)')
                                    ->schema([
                                        TextInput::make('name')
                                            ->label('Назва')
                                            ->required(),
                                        TextInput::make('price')
                                            ->label('Ціна (необов`язково)')
                                            ->numeric()
                                            ->minValue(0)
                                            ->prefix('UAH'),
                                        ColorPicker::make('color_hex')
                                            ->label('Колір')
                                            ->visible(function (Get $get): bool {
                                                $groupId = (int) ($get('../../option_group_id') ?? 0);

                                                if ($groupId <= 0) {
                                                    return false;
                                                }

                                                return OptionGroup::query()
                                                    ->whereKey($groupId)
                                                    ->where('value_type', 'color')
                                                    ->exists();
                                            })
                                            ->required(function (Get $get): bool {
                                                $groupId = (int) ($get('../../option_group_id') ?? 0);

                                                if ($groupId <= 0) {
                                                    return false;
                                                }

                                                return OptionGroup::query()
                                                    ->whereKey($groupId)
                                                    ->where('value_type', 'color')
                                                    ->exists();
                                            }),
                                        self::applyListingPhotosLayout(
                                            FileUpload::make('photos')
                                                ->label('Фото цього кольору')
                                                ->image()
                                                ->multiple()
                                                ->reorderable()
                                                ->appendFiles()
                                                ->disk('public')
                                                ->directory(fn (?Product $record): string => 'products/'.($record?->id ?? 'draft').'/option-value-photos')
                                                ->maxFiles(20)
                                                ->imageAspectRatio('1:1')
                                                ->imageEditor()
                                                ->imageEditorViewportWidth(480)
                                                ->imageEditorViewportHeight(480)
                                                ->helperText('Зверху — мініатюри; знизу — додати фото. Олівець — кадр 1:1 як у головній галереї.')
                                                ->visible(function (Get $get): bool {
                                                    $groupId = (int) ($get('../../option_group_id') ?? 0);
                                                    if ($groupId <= 0) {
                                                        return false;
                                                    }

                                                    return OptionGroup::query()
                                                        ->whereKey($groupId)
                                                        ->where('value_type', 'color')
                                                        ->exists();
                                                })
                                        ),
                                    ]),
                            ])
                            ->columns(1),
                    ]),
                Section::make('SEO')
                    ->description('Фото додаються лише у вкладці «Варіанти».')
                    ->schema([
                        TagsInput::make('search_tags')
                            ->label('Пошукові теги')
                            ->helperText('Додайте ключові слова для пошуку.'),
                    ]),
            ]);
    }
}
