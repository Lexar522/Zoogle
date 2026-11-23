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
                    ->disabled(fn (?OptionGroup $record, Get $get): bool => $record?->slug === 'category'
                        || (string) ($get('value_type') ?? $record?->value_type ?? 'text') === 'color')
                    ->dehydrated(true)
                    ->options([
                        'single' => 'Тільки один варіант',
                        'multiple' => 'Можна обрати кілька',
                    ])
                    ->helperText(fn (Get $get): ?string => (string) ($get('value_type') ?? '') === 'color'
                        ? 'Для кольору завжди один варіант — на вітрині можна обрати лише один колір.'
                        : null)
                    ->dehydrateStateUsing(function (mixed $state, Get $get): string {
                        if ((string) ($get('value_type') ?? '') === 'color') {
                            return 'single';
                        }

                        return in_array($state, ['single', 'multiple'], true) ? (string) $state : 'single';
                    }),
                Select::make('value_type')
                    ->label('Формат значення')
                    ->required()
                    ->default('text')
                    ->live()
                    ->disabled(fn (?OptionGroup $record): bool => $record?->slug === 'category')
                    ->afterStateUpdated(function (?string $state, callable $set): void {
                        if ($state === 'color') {
                            $set('selection_mode', 'single');
                        }
                    })
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
                        ? 'Оберіть «Формат значення» вище (текст або колір) — тоді зʼявиться кнопка «Додати до наповнення опції».'
                        : 'Додайте значення (розміри, кольори тощо). Після збереження групи вони зʼявляться при редагуванні товарів.')
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

    /**
     * Лише кореневі рубрики, без підкатегорій: гілки редагуються по одній ({@see catalogBranchRepeater()}).
     * Усе дерево в одній формі важило ~30 КБ HTML на категорію і вантажилося секундами.
     */
    public static function catalogRootCategoriesRepeater(): Repeater
    {
        $repeater = Repeater::make('categories')
            ->relationship('values', function ($query) {
                if (DbSchema::hasColumn('option_values', 'parent_id')) {
                    $query->whereNull('parent_id');
                }
            });

        return self::configureCategoryRepeater(
            $repeater,
            'Кореневі категорії',
            'Додати кореневу категорію',
            [...self::categoryIdentityFields('Назва категорії', 'Слаг'), ...self::categoryRootCheckoutTogglesForRepeaterItem()],
        );
    }

    /**
     * Підкатегорії однієї кореневої рубрики (рівень 2 і глибше). Нові елементи прив'язуються до цієї рубрики.
     */
    public static function catalogBranchRepeater(int $rootId): Repeater
    {
        $repeater = Repeater::make('branch')
            ->relationship('values', fn ($query) => $query->where('parent_id', $rootId))
            ->mutateRelationshipDataBeforeCreateUsing(fn (array $data): array => [...$data, 'parent_id' => $rootId]);

        return self::configureCategoryRepeater($repeater, 'Підкатегорія (рівень 2)', 'Додати рівень 2', self::categorySubtreeSchemaForLevel(2));
    }

    /**
     * @param  list<Component|Repeater|Hidden|TextInput|Toggle>  $schema
     */
    private static function configureCategoryRepeater(Repeater $repeater, string $label, string $addActionLabel, array $schema): Repeater
    {
        return $repeater
            ->label($label)
            ->orderColumn('sort_order')
            ->reorderable()
            ->defaultItems(0)
            ->addActionLabel($addActionLabel)
            ->collapsed()
            ->itemLabel(fn (array $state): ?string => $state['name'] ?? null)
            ->schema($schema)
            ->columns(2)
            ->columnSpanFull();
    }

    /**
     * @return list<Hidden|TextInput>
     */
    private static function categoryIdentityFields(string $nameLabel, string $slugLabel): array
    {
        return [
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
                ->label('Уся гілка: без Нової Пошти (самовивіз або таксі)')
                ->helperText('Коренева рубрика, напр. «Тварини»: на чекауті лишаються тільки самовивіз і таксі-доставка по місту.')
                ->default(false)
                ->columnSpanFull(),
            Toggle::make('defer_online_payment')
                ->label('Відкласти онлайн-оплату до узгодження з менеджером')
                ->helperText('Коренева рубрика: на чекауті не буде LiqPay; після дзвінка менеджер у картці замовлення вмикає оплату, клієнт — з акаунта.')
                ->default(false)
                ->columnSpanFull(),
            Toggle::make('exclude_from_merchant_feed')
                ->label('Не передавати в Google Merchant (вкладка «Покупки»)')
                ->helperText('Коренева рубрика: товари гілки не потрапляють у фід Google. Потрібно для живих тварин — Google забороняє їх рекламувати.')
                ->default(false)
                ->columnSpanFull(),
        ];
    }

    /**
     * @return list<Component>
     */
    private static function categorySubtreeSchemaForLevel(int $level): array
    {
        $schema = self::categoryIdentityFields('Назва (рівень '.$level.')', 'Слаг (рівень '.$level.')');

        if (DbSchema::hasColumn('option_values', 'exclude_from_merchant_feed')) {
            $schema[] = Toggle::make('exclude_from_merchant_feed')
                ->label('Не передавати в Google Merchant')
                ->helperText('Підкатегорія з живими тваринами чи живим кормом: її товари не потрапляють у фід Google і Meta.')
                ->default(false)
                ->columnSpanFull();
        }

        if ($level >= CatalogCategoryTree::MAX_DEPTH) {
            return $schema;
        }

        $childLevel = $level + 1;
        $schema[] = self::configureCategoryRepeater(
            Repeater::make('children')->relationship('children'),
            'Підкатегорія (рівень '.$childLevel.')',
            'Додати рівень '.$childLevel,
            self::categorySubtreeSchemaForLevel($childLevel),
        );

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
