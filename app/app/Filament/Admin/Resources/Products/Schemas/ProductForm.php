<?php

namespace App\Filament\Admin\Resources\Products\Schemas;

use App\Filament\Admin\Schemas\CatalogCategoryCascadeFields;
use App\Filament\Support\ProductAdminSelectSearch;
use App\Models\OptionGroup;
use App\Models\OptionValue;
use App\Models\Product;
use App\Support\CatalogCategoryTree;
use App\Support\ListingPhotoUpload;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Schema as DbSchema;
use Illuminate\Support\Str;
use Livewire\Component as LivewireComponent;

class ProductForm
{
    /**
     * Кастомний макет галереї (див. listing-photos-upload-layout): мініатюри в ряд + зона додавання.
     */
    public static function applyListingPhotosLayout(FileUpload $upload): FileUpload
    {
        return ListingPhotoUpload::applyPublicPreviewUrl(
            ListingPhotoUpload::applyAcceptedImageTypes($upload)
        )
            ->itemPanelAspectRatio('1:1')
            ->imagePreviewHeight('88')
            // Менше залежність від бекенд-метаданих по кожному файлу; разом із коректним URL public disk зменшує зависання FilePond («Waiting for size»).
            ->fetchFileInformation(false)
            // Один активний upload за раз — менше конфліктів через блокування PHP-сесії на паралельних Livewire-запитах.
            ->maxParallelUploads(1)
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
                            ->description('Основна категорія визначає опції товару та правила оформлення. Додаткові — лише де товар з’являється в каталозі (наприклад іграшка для кількох тварин).')
                            ->schema([
                                ...CatalogCategoryCascadeFields::listingCategoryLevelFields(),
                                Select::make('additional_category_ids')
                                    ->label('Також показувати в категоріях')
                                    ->helperText('Оберіть інші гілки каталогу, де товар має з’являтися. Основну категорію вище сюди додавати не потрібно.')
                                    ->multiple()
                                    ->searchable()
                                    ->preload()
                                    ->native(false)
                                    ->options(function (Get $get): array {
                                        if (! DbSchema::hasTable('product_categories')) {
                                            return [];
                                        }

                                        $groupId = OptionGroup::systemCategoryGroupIdForCatalog();
                                        if ($groupId <= 0) {
                                            return [];
                                        }

                                        $options = CatalogCategoryTree::flatPathOptionsForSelect($groupId);
                                        $primaryId = 0;
                                        for ($i = CatalogCategoryTree::MAX_DEPTH; $i >= 1; $i--) {
                                            $levelId = (int) ($get('category_level_'.$i.'_id') ?? 0);
                                            if ($levelId > 0) {
                                                $primaryId = $levelId;
                                                break;
                                            }
                                        }
                                        if ($primaryId > 0) {
                                            unset($options[$primaryId]);
                                        }

                                        return $options;
                                    })
                                    ->visible(fn (): bool => DbSchema::hasTable('product_categories')
                                        && OptionGroup::systemCategoryGroupIdForCatalog() > 0)
                                    ->dehydrated(true),
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
                        Grid::make([
                            'default' => 1,
                            'md' => 3,
                        ])
                            ->schema([
                                TextInput::make('length_cm')
                                    ->label('Довжина')
                                    ->numeric()
                                    ->minValue(0)
                                    ->suffix('см')
                                    ->dehydrateStateUsing(fn ($state): ?float => filled((string) $state) ? (float) $state : null),
                                TextInput::make('width_cm')
                                    ->label('Ширина')
                                    ->numeric()
                                    ->minValue(0)
                                    ->suffix('см')
                                    ->dehydrateStateUsing(fn ($state): ?float => filled((string) $state) ? (float) $state : null),
                                TextInput::make('height_cm')
                                    ->label('Висота')
                                    ->numeric()
                                    ->minValue(0)
                                    ->suffix('см')
                                    ->dehydrateStateUsing(fn ($state): ?float => filled((string) $state) ? (float) $state : null),
                            ]),
                    ]),
                Section::make('Публікація')
                    ->schema([
                        Toggle::make('is_available')
                            ->label('Показувати на вітрині')
                            ->helperText('Головний перемикач видимості товару в каталозі.')
                            ->default(true)
                            ->required(),
                        Select::make('stock_status')
                            ->label('Статус наявності')
                            ->options(Product::stockStatusOptions())
                            ->default(Product::STOCK_IN_STOCK)
                            ->required()
                            ->native(false)
                            ->helperText('«Під замовлення» можна додати в кошик, але онлайн-оплата відкладається.'),
                        TextInput::make('stock_quantity')
                            ->label('Кількість на складі')
                            ->numeric()
                            ->integer()
                            ->minValue(0)
                            ->nullable()
                            ->helperText('Для товару без варіантів. Порожньо — кількість не рахується. На нулі товар стає «Немає в наявності».'),
                        DateTimePicker::make('published_at')
                            ->label('Опубліковано'),
                    ]),
                Section::make('Фото товару')
                    ->description('Базова галерея товару. Якщо для кольору задані свої фото — на вітрині вони матимуть пріоритет.')
                    ->schema([
                        ListingPhotoUpload::applyImageEditorIfAvailable(
                            self::applyListingPhotosLayout(
                                ListingPhotoUpload::make('photos')
                                    ->hiddenLabel()
                                    ->multiple()
                                    ->reorderable()
                                    ->appendFiles()
                                    ->disk('public')
                                    ->visibility('public')
                                    ->directory(fn (?Product $record, FileUpload $component): string => ListingPhotoUpload::productListingPhotosDirectory($record, $component))
                                    ->maxFiles(20)
                                    ->maxSize(51200)
                                    ->helperText('Формати: JPEG (.jpg, .jpeg), PNG, WebP, GIF, MP4, WebM. Бажане кадрування 1:1 — через олівець на мініатюрі (лише для фото; GIF і відео не обрізати олівцем). Перший файл — головний. Дочекайтесь мініатюр перед збереженням.')
                            )
                        ),
                    ]),
                Section::make('Опції товару')
                    ->description('1) Спочатку оберіть категорію товару вище. 2) «Додати групу опцій» → група з довідника «Групи опцій» (або створіть її в Довідники). 3) Відмітьте значення або додайте нові в блоці «Додати нові значення».')
                    ->schema([
                        Repeater::make('variant_options')
                            ->label('Групи опцій')
                            ->helperText('Після «Додати групу опцій» розгорніть рядок: оберіть групу зі списку й відмітьте хоча б одне значення. Збережіть товар кнопкою вгорі — лише довідник «Групи опцій» не прив’язує опцію до товару.')
                            ->reorderable()
                            ->defaultItems(0)
                            ->addActionLabel('Додати групу опцій')
                            ->collapsible()
                            ->collapsed(false)
                            ->itemLabel(function (array $state): ?string {
                                $groupId = $state['option_group_id'] ?? null;

                                if (! $groupId) {
                                    return 'Нова опція';
                                }

                                $group = OptionGroup::cached((int) $groupId);
                                if (! $group) {
                                    return 'Опція';
                                }

                                $typeLabel = ($group->value_type ?? 'text') === 'color' ? 'Колір' : 'Текст';

                                return "{$group->name} ({$typeLabel})";
                            })
                            ->schema([
                                Select::make('option_value_type')
                                    ->label('Формат значення')
                                    ->required(fn (Get $get): bool => (int) ($get('option_group_id') ?? 0) > 0)
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

                                        $valueType = OptionGroup::cached($groupId)?->value_type;

                                        if (filled($valueType)) {
                                            $set('option_value_type', $valueType);
                                        }
                                    })
                                    ->live()
                                    ->disabled(fn (Get $get): bool => (int) ($get('option_group_id') ?? 0) > 0)
                                    ->helperText(fn (Get $get): ?string => (int) ($get('option_group_id') ?? 0) > 0
                                        ? 'Формат зафіксовано після вибору групи. Щоб змінити тип — видаліть цей рядок і додайте новий.'
                                        : 'Спочатку оберіть формат, потім групу нижче.')
                                    ->afterStateUpdated(function (Set $set, Get $get, ?string $state, ?string $old): void {
                                        if ((int) ($get('option_group_id') ?? 0) > 0) {
                                            return;
                                        }

                                        if ($state === $old) {
                                            return;
                                        }

                                        $set('option_group_id', null);
                                        $set('option_value_ids', []);
                                        $set('value_prices', []);
                                        $set('new_values', []);
                                    }),
                                Select::make('option_group_id')
                                    ->label('Група опцій')
                                    ->required()
                                    ->placeholder('Оберіть групу з довідника (або видаліть рядок, якщо зайвий)')
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
                                            $currentGroup = OptionGroup::cached($currentGroupId);
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
                                            $valueType = OptionGroup::cached($gid)?->value_type;
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

                                        $group = OptionGroup::cached((int) $groupId);

                                        if (! $group) {
                                            return 'Обов’язково відмітьте хоча б одне значення — без цього опція не з’явиться на товарі після збереження.';
                                        }

                                        if (($group->selection_mode ?? 'single') === 'single') {
                                            return 'Обов’язково відмітьте хоча б одне значення. На сайті покупець обере один варіант; у картці можна відмітити всі доступні значення.';
                                        }

                                        return 'Обов’язково відмітьте хоча б одне значення — усі відмічені збережуться в картці товару.';
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
                                Select::make('default_option_value_id')
                                    ->label('Головна ціна / преселект на вітрині')
                                    ->helperText('Яке значення підсвітити при відкритті товару й врахувати в ціні картки каталогу. Порожньо — автоматично значення без доплати або з мінімальною.')
                                    ->placeholder('Авто: мінімальна / без доплати')
                                    ->options(function (Get $get): array {
                                        $groupId = (int) ($get('option_group_id') ?? 0);
                                        $selectedIds = collect($get('option_value_ids') ?? [])
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
                                    ->searchable()
                                    ->nullable()
                                    ->native(false)
                                    ->visible(fn (Get $get): bool => (int) ($get('option_group_id') ?? 0) > 0
                                        && collect($get('option_value_ids') ?? [])->filter()->count() >= 2)
                                    ->columnSpanFull(),
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
                                        ListingPhotoUpload::applyImageEditorIfAvailable(
                                            self::applyListingPhotosLayout(
                                                ListingPhotoUpload::make('photos')
                                                    ->label('Фото цього кольору')
                                                    ->multiple()
                                                    ->reorderable()
                                                    ->appendFiles()
                                                    ->disk('public')
                                                    ->directory(fn (?Product $record, FileUpload $component): string => ListingPhotoUpload::productOptionValuePhotosDirectory($record, $component))
                                                    ->maxFiles(20)
                                                    ->helperText('Формати: JPEG (.jpg, .jpeg), PNG, WebP, GIF. Кадр 1:1 — за бажанням через олівець.')
                                            )
                                        ),
                                    ])
                                    ->columns(1),
                                Repeater::make('new_values')
                                    ->label('Додати нові значення (якщо треба)')
                                    ->addActionLabel('Додати значення')
                                    ->addable(fn (Get $get): bool => (int) ($get('option_group_id') ?? 0) > 0)
                                    ->helperText(fn (Get $get): ?string => (int) ($get('option_group_id') ?? 0) > 0
                                        ? null
                                        : 'Спочатку оберіть «Група опцій» у цьому рядку — тоді зʼявиться кнопка додавання значення.')
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
                                        ListingPhotoUpload::applyImageEditorIfAvailable(
                                            self::applyListingPhotosLayout(
                                                ListingPhotoUpload::make('photos')
                                                    ->label('Фото цього кольору')
                                                    ->multiple()
                                                    ->reorderable()
                                                    ->appendFiles()
                                                    ->disk('public')
                                                    ->directory(fn (?Product $record, FileUpload $component): string => ListingPhotoUpload::productOptionValuePhotosDirectory($record, $component))
                                                    ->maxFiles(20)
                                                    ->helperText('Формати: JPEG (.jpg, .jpeg), PNG, WebP, GIF. Кадр 1:1 — за бажанням через олівець.')
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
                                            )
                                        ),
                                    ]),
                            ])
                            ->columns(1),
                    ]),
                Section::make('Рекомендовані товари')
                    ->description('Показуються в блоці «Схожі товари» на сторінці цього товару. Порядок у списку = порядок на сайті. Пошук за назвою або артикулом.')
                    ->schema([
                        Repeater::make('recommended_items')
                            ->hiddenLabel()
                            ->reorderable()
                            ->collapsible()
                            ->defaultItems(0)
                            ->addActionLabel('Додати рекомендований товар')
                            ->itemLabel(fn (array $state): ?string => ProductAdminSelectSearch::labelForId((int) ($state['product_id'] ?? 0)) ?? 'Новий рядок')
                            ->schema([
                                Select::make('product_id')
                                    ->label('Товар')
                                    ->required()
                                    ->searchable()
                                    ->searchPrompt('Назва або артикул')
                                    ->allowHtml()
                                    ->native(false)
                                    ->getSearchResultsUsing(function (string $search, LivewireComponent $livewire): array {
                                        $excludeId = null;
                                        if (method_exists($livewire, 'getRecord')) {
                                            $record = $livewire->getRecord();
                                            if ($record instanceof Product) {
                                                $excludeId = (int) $record->id;
                                            }
                                        }

                                        return ProductAdminSelectSearch::searchWithPhoto($search, $excludeId);
                                    })
                                    ->getOptionLabelUsing(fn ($value): ?string => ProductAdminSelectSearch::labelHtmlForId((int) $value))
                                    ->helperText('Почніть вводити назву або артикул.'),
                            ])
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
