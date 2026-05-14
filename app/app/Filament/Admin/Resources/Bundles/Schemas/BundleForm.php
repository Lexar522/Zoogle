<?php

namespace App\Filament\Admin\Resources\Bundles\Schemas;

use App\Filament\Admin\Resources\Products\Schemas\ProductForm;
use App\Filament\Admin\Schemas\CatalogCategoryCascadeFields;
use App\Models\Bundle;
use App\Models\OptionGroup;
use App\Models\OptionValue;
use App\Models\Product;
use App\Support\CatalogCategoryTree;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class BundleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Основна інформація')
                    ->columns(2)
                    ->schema([
                        TextInput::make('title')
                            ->label('Назва')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (?string $state, Set $set) => $set('slug', Str::slug((string) $state))),
                        TextInput::make('slug')
                            ->label('Слаг')
                            ->required()
                            ->maxLength(255)
                            ->helperText('Використовується в URL сторінки комплекту.')
                            ->unique(ignoreRecord: true),
                        TextInput::make('sku')
                            ->label('Артикул')
                            ->maxLength(120)
                            ->placeholder('Необов’язково')
                            ->dehydrateStateUsing(fn (?string $state): ?string => filled(trim((string) $state)) ? trim((string) $state) : null),
                        ...CatalogCategoryCascadeFields::listingCategoryLevelFields(),
                        Textarea::make('short_description')
                            ->label('Короткий опис')
                            ->columnSpanFull(),
                        Textarea::make('description')
                            ->label('Опис')
                            ->columnSpanFull(),
                    ]),
                Section::make('Публікація')
                    ->schema([
                        Toggle::make('is_visible')
                            ->label('На вітрині')
                            ->default(true),
                        Toggle::make('is_active')
                            ->label('Активний')
                            ->default(true),
                    ]),
                Section::make('Товари в комплекті')
                    ->description('Підсумкова ціна на сайті зводиться з цих товарів (з урахуванням акцій на комплект).')
                    ->schema([
                        Repeater::make('items')
                            ->label('Позиції')
                            ->reorderable()
                            ->collapsible()
                            ->minItems(1)
                            ->defaultItems(1)
                            ->addActionLabel('Додати товар')
                            ->itemLabel(fn (array $state): ?string => Product::query()->whereKey((int) ($state['product_id'] ?? 0))->value('title'))
                            ->schema([
                                Select::make('product_id')
                                    ->label('Товар')
                                    ->required()
                                    ->searchable()
                                    ->getSearchResultsUsing(fn (string $search): array => Product::query()
                                        ->where('title', 'like', '%'.$search.'%')
                                        ->orderBy('title')
                                        ->limit(50)
                                        ->pluck('title', 'id')
                                        ->all())
                                    ->getOptionLabelUsing(fn ($value): ?string => Product::query()->whereKey($value)->value('title')),
                                TextInput::make('qty')
                                    ->label('Кількість')
                                    ->required()
                                    ->numeric()
                                    ->minValue(1)
                                    ->default(1),
                                TextInput::make('sort_order')
                                    ->label('Порядок')
                                    ->numeric()
                                    ->default(0),
                            ])
                            ->columns(3),
                    ]),
                Section::make('Фото комплекту')
                    ->description('Галерея на сторінці комплекту.')
                    ->schema([
                        ProductForm::applyListingPhotosLayout(
                            FileUpload::make('photos')
                                ->hiddenLabel()
                                ->image()
                                ->multiple()
                                ->reorderable()
                                ->appendFiles()
                                ->disk('public')
                                ->directory(fn (?Bundle $record): string => 'bundles/'.($record?->id ?? 'draft').'/photos')
                                ->maxFiles(20)
                                ->imageEditor()
                                ->imageEditorViewportWidth(480)
                                ->imageEditorViewportHeight(480)
                                ->helperText('Формати: JPEG (.jpg, .jpeg), PNG, WebP, GIF. Дочекайтесь мініатюр перед збереженням. Порядок мініатюр = порядок у галереї на сайті.')
                        ),
                    ]),
                Section::make('Опції комплекту')
                    ->description('Як у товарі: групи з довідника, значення, доплати та фото для кольорів.')
                    ->schema([
                        Repeater::make('variant_options')
                            ->label('Групи опцій')
                            ->helperText('Порядок груп збігається з порядком на сторінці комплекту.')
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
                                    ->noOptionsMessage('Немає доступних груп опцій.')
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

                                        return 'Можна відмітити кілька значень.';
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
                                    ->label('Доплата за значення (на комплект)')
                                    ->helperText('Необов’язково. Доплата в ₴ для цього комплекту.')
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
                                    ->helperText('Для кольору можна прив’язати фото до значення.')
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
                                        ProductForm::applyListingPhotosLayout(
                                            FileUpload::make('photos')
                                                ->label('Фото цього кольору')
                                                ->image()
                                                ->multiple()
                                                ->reorderable()
                                                ->appendFiles()
                                                ->disk('public')
                                                ->directory(fn (?Bundle $record): string => 'bundles/'.($record?->id ?? 'draft').'/option-value-photos')
                                                ->maxFiles(20)
                                                ->imageEditor()
                                                ->imageEditorViewportWidth(480)
                                                ->imageEditorViewportHeight(480)
                                                ->helperText('Формати: JPEG (.jpg, .jpeg), PNG, WebP, GIF. Дочекайтесь мініатюр перед збереженням. Мініатюри зверху; олівець — кадр 1:1.')
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
                                            ->label('Ціна (необов’язково)')
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
                                        ProductForm::applyListingPhotosLayout(
                                            FileUpload::make('photos')
                                                ->label('Фото цього кольору')
                                                ->image()
                                                ->multiple()
                                                ->reorderable()
                                                ->appendFiles()
                                                ->disk('public')
                                                ->directory(fn (?Bundle $record): string => 'bundles/'.($record?->id ?? 'draft').'/option-value-photos')
                                                ->maxFiles(20)
                                                ->imageEditor()
                                                ->imageEditorViewportWidth(480)
                                                ->imageEditorViewportHeight(480)
                                                ->helperText('Формати: JPEG (.jpg, .jpeg), PNG, WebP, GIF. Дочекайтесь мініатюр перед збереженням. Мініатюри зверху; олівець — кадр 1:1.')
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
            ]);
    }
}
