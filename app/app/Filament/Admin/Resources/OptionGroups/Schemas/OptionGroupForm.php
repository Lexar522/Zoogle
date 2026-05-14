<?php

namespace App\Filament\Admin\Resources\OptionGroups\Schemas;

use App\Filament\Admin\Schemas\CatalogCategoryCascadeFields;
use App\Models\OptionGroup;
use App\Support\CatalogCategoryTree;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Schema as DbSchema;
use Illuminate\Support\Str;

class OptionGroupForm
{
    public static function configure(Schema $schema): Schema
    {
        $hasParentColumn = DbSchema::hasColumn('option_values', 'parent_id');

        return $schema
            ->components([
                Section::make('Область застосування')
                    ->description('Для яких карток каталогу ця група опцій пропонується в адмінці та на сайті. «Гілка» — усі товари, чия категорія збігається з обраним рівнем або лежить нижче в дереві.')
                    ->visible(fn (?OptionGroup $record, Get $get): bool => ($record?->slug ?? (string) $get('slug')) !== 'category')
                    ->schema([
                        Select::make('applies_mode')
                            ->label('Тип прив’язки')
                            ->required()
                            ->default(OptionGroup::CATALOG_PRODUCT_TYPE)
                            ->live()
                            ->options(function () use ($hasParentColumn): array {
                                $base = [
                                    OptionGroup::CATALOG_PRODUCT_TYPE => 'Усі товари',
                                ];

                                if (! $hasParentColumn || OptionGroup::systemCategoryGroupId() <= 0) {
                                    return $base;
                                }

                                return $base + [
                                    'category' => 'Конкретна гілка каталогу (до '.CatalogCategoryTree::MAX_DEPTH.' рівнів)',
                                ];
                            })
                            ->afterStateUpdated(function (Set $set, ?string $state): void {
                                if ($state !== 'category') {
                                    for ($i = 1; $i <= CatalogCategoryTree::MAX_DEPTH; $i++) {
                                        $set('scope_category_level_'.$i.'_id', null);
                                    }
                                }
                            }),
                        ...CatalogCategoryCascadeFields::scopeCategoryLevelFields(),
                    ])
                    ->columns(1),
                TextInput::make('name')
                    ->label('Назва групи')
                    ->required()
                    ->disabled(fn (?OptionGroup $record): bool => $record?->slug === 'category')
                    ->live(onBlur: true)
                    ->afterStateUpdated(function (?string $state, callable $set): void {
                        if (blank($state)) {
                            return;
                        }

                        $set('slug', Str::slug($state));
                    }),
                TextInput::make('slug')
                    ->label('Слаг')
                    ->required()
                    ->disabled(fn (?OptionGroup $record): bool => $record?->slug === 'category')
                    ->unique(ignoreRecord: true),
                Select::make('selection_mode')
                    ->label('Режим вибору')
                    ->required()
                    ->default('single')
                    ->disabled(fn (?OptionGroup $record): bool => $record?->slug === 'category')
                    ->options([
                        'single' => 'Тільки один варіант',
                        'multiple' => 'Можна обрати кілька',
                    ]),
                Select::make('value_type')
                    ->label('Формат значення')
                    ->required()
                    ->placeholder('Спочатку оберіть формат значення')
                    ->live()
                    ->disabled(fn (?OptionGroup $record): bool => $record?->slug === 'category')
                    ->helperText(function (?OptionGroup $record): ?string {
                        if ($record?->slug === 'category') {
                            return null;
                        }

                        if (! $record?->values()->exists()) {
                            return null;
                        }

                        $type = (string) ($record->value_type ?? 'text');

                        return $type === 'text'
                            ? 'Можна змінити на «Колір»: для кожного значення потрібно буде обрати колір (hex) і зберегти.'
                            : 'Перехід на «Текст» можливий лише якщо у значень ще не задано колір.';
                    })
                    ->options([
                        'text' => 'Текст (розмір, стать, матеріал)',
                        'color' => 'Колір',
                    ]),
                Toggle::make('is_active')
                    ->label('Активна')
                    ->visible(fn (Get $get): bool => (string) $get('slug') !== 'category')
                    ->dehydrated(true)
                    ->default(true),
                Repeater::make('values')
                    ->label('Наповнення опції (значення)')
                    ->relationship('values')
                    ->orderColumn('sort_order')
                    ->reorderable()
                    ->visible(fn (Get $get): bool => (string) $get('slug') !== 'category')
                    ->defaultItems(0)
                    ->addActionLabel('Додати до наповнення опції (значення)')
                    ->addable(fn (Get $get): bool => filled($get('value_type')))
                    ->helperText(fn (Get $get): ?string => blank($get('value_type'))
                        ? 'Спочатку оберіть формат значення, після цього зʼявиться кнопка додавання.'
                        : null)
                    ->collapsible()
                    ->itemLabel(fn (array $state): ?string => $state['name'] ?? null)
                    ->schema([
                        TextInput::make('name')
                            ->label('Назва значення')
                            ->required()
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (?string $state, callable $set): void {
                                if (blank($state)) {
                                    return;
                                }

                                $set('slug', Str::slug($state));
                            }),
                        TextInput::make('slug')
                            ->label('Слаг')
                            ->required(),
                        ColorPicker::make('color_hex')
                            ->label('Колір')
                            ->visible(function (Get $get): bool {
                                $type = (string) ($get('../../value_type') ?? $get('../value_type') ?? $get('value_type') ?? 'text');

                                return $type === 'color';
                            })
                            ->required(function (Get $get): bool {
                                $type = (string) ($get('../../value_type') ?? $get('../value_type') ?? $get('value_type') ?? 'text');

                                return $type === 'color';
                            })
                            ->helperText('Оберіть колір для цього значення.'),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),
            ]);
    }

    public static function catalogCategoriesRepeater(): Repeater
    {
        $hasParentColumn = DbSchema::hasColumn('option_values', 'parent_id');

        return Repeater::make('categories')
            ->label('Категорії')
            ->relationship('values', function ($query) {
                if (DbSchema::hasColumn('option_values', 'parent_id')) {
                    $query->whereNull('parent_id');
                }
            })
            ->orderColumn('sort_order')
            ->reorderable()
            ->defaultItems(0)
            ->addActionLabel('Додати категорію')
            ->collapsed()
            ->itemLabel(fn (array $state): ?string => $state['name'] ?? null)
            ->schema(array_merge(
                [
                    Hidden::make('option_group_id')
                        ->default(fn (Get $get): ?int => self::optionGroupIdFromFormRoot($get))
                        ->dehydrated(true),
                    TextInput::make('name')
                        ->label('Назва категорії')
                        ->required()
                        ->live(onBlur: true)
                        ->afterStateUpdated(function (?string $state, callable $set): void {
                            if (blank($state)) {
                                return;
                            }

                            $set('slug', Str::slug($state));
                        }),
                    TextInput::make('slug')
                        ->label('Слаг')
                        ->required(),
                ],
                self::categoryRootCheckoutTogglesForRepeaterItem(),
                $hasParentColumn ? self::categorySubtreeBelowRoot() : [],
            ))
            ->columns(2)
            ->columnSpanFull();
    }

    /**
     * Поля доставки/оплати тільки для **кореневої** рубрики (елемент repeater'а «Категорії»).
     * Підкатегорії нижче в дереві ці прапорці не показують (і при збереженні в моделі скидаються).
     *
     * @return list<Component>
     */
    private static function categoryRootCheckoutTogglesForRepeaterItem(): array
    {
        if (! DbSchema::hasColumn('option_values', 'parent_id')
            || ! DbSchema::hasColumn('option_values', 'pickup_only_subtree')) {
            return [];
        }

        return [
            Toggle::make('pickup_only_subtree')
                ->label('Уся гілка: лише самовивіз (без Нової Пошти)')
                ->helperText('Коренева рубрика: усі товари в цій гілці — лише самовивіз; Нова Пошта на чекауті недоступна.')
                ->default(false)
                ->columnSpanFull(),
            Toggle::make('defer_online_payment')
                ->label('Відкласти онлайн-оплату до узгодження з менеджером')
                ->helperText('Коренева рубрика: на чекауті не буде LiqPay; після дзвінка менеджер у картці замовлення вмикає оплату, клієнт — з акаунта.')
                ->default(false)
                ->columnSpanFull(),
        ];
    }

    /**
     * @return list<Component>
     */
    private static function categorySubtreeBelowRoot(): array
    {
        $nextLevel = 2;
        $repeater = Repeater::make('children')
            ->label('Підкатегорія (рівень '.$nextLevel.')')
            ->relationship('children')
            ->orderColumn('sort_order')
            ->reorderable()
            ->defaultItems(0)
            ->addActionLabel('Додати рівень '.$nextLevel)
            ->collapsed()
            ->itemLabel(fn (array $state): ?string => $state['name'] ?? null)
            ->schema(self::categorySubtreeSchemaForLevel($nextLevel))
            ->columns(2)
            ->columnSpanFull();

        return [$repeater];
    }

    /**
     * @return list<Component>
     */
    private static function categorySubtreeSchemaForLevel(int $level): array
    {
        $nameLabel = $level === 1 ? 'Назва категорії' : 'Назва (рівень '.$level.')';
        $slugLabel = $level === 1 ? 'Слаг' : 'Слаг (рівень '.$level.')';

        $schema = [
            Hidden::make('option_group_id')
                ->default(fn (Get $get): ?int => self::optionGroupIdFromFormRoot($get))
                ->dehydrated(true),
            TextInput::make('name')
                ->label($nameLabel)
                ->required()
                ->live(onBlur: true)
                ->afterStateUpdated(function (?string $state, callable $set): void {
                    if (blank($state)) {
                        return;
                    }

                    $set('slug', Str::slug($state));
                }),
            TextInput::make('slug')
                ->label($slugLabel)
                ->required(),
        ];

        if ($level >= CatalogCategoryTree::MAX_DEPTH) {
            return $schema;
        }

        $childLevel = $level + 1;
        $schema[] = Repeater::make('children')
            ->label('Підкатегорія (рівень '.$childLevel.')')
            ->relationship('children')
            ->orderColumn('sort_order')
            ->reorderable()
            ->defaultItems(0)
            ->addActionLabel('Додати рівень '.$childLevel)
            ->collapsed()
            ->itemLabel(fn (array $state): ?string => $state['name'] ?? null)
            ->schema(self::categorySubtreeSchemaForLevel($childLevel))
            ->columns(2)
            ->columnSpanFull();

        return $schema;
    }

    private static function optionGroupIdFromFormRoot(Get $get): ?int
    {
        $id = $get('/data.id');
        if (filled($id) && (int) $id > 0) {
            return (int) $id;
        }

        return null;
    }
}
