<?php

namespace App\Filament\Admin\Pages;

use App\Filament\Admin\Resources\OptionGroups\OptionGroupResource;
use App\Filament\Support\ProductAdminSelectSearch;
use App\Models\OptionGroup;
use App\Models\OptionValue;
use App\Models\ShopHeaderBanner;
use App\Models\ShopHeaderSetting;
use App\Support\ListingPhotoUpload;
use App\Support\ShopHeaderChipStyles;
use App\Support\StoragePublicPath;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ViewField;
use Filament\Notifications\Notification;
use Filament\Pages\Concerns\CanUseDatabaseTransactions;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;
use UnitEnum;

/**
 * @property-read Schema $form
 */
class ManageCatalogHeader extends Page
{
    use CanUseDatabaseTransactions;

    protected static ?string $slug = 'catalog-header';

    protected static ?string $title = 'Шапка каталогу';

    protected static ?string $navigationLabel = 'Шапка каталогу';

    protected static string|UnitEnum|null $navigationGroup = 'Каталог';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedViewColumns;

    protected static ?int $navigationSort = 12;

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        $record = OptionGroup::query()->where('slug', 'category')->first();

        return $record !== null && OptionGroupResource::canEdit($record);
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);
        $this->form->fill($this->loadFormData());
    }

    /**
     * @return array<string, mixed>
     */
    private function loadFormData(): array
    {
        $settings = ShopHeaderSetting::record();

        $bannersTop = [];
        $bannersBottom = [];

        ShopHeaderBanner::query()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->each(function (ShopHeaderBanner $row) use (&$bannersTop, &$bannersBottom): void {
                $item = [
                    'id' => (string) $row->id,
                    'upload_key' => (string) $row->id,
                    'image_path' => $row->image_path,
                    'link_type' => $row->link_type,
                    'product_id' => $row->product_id ? (string) $row->product_id : null,
                    'url' => $row->url,
                    'category_option_value_id' => $row->category_option_value_id ? (string) $row->category_option_value_id : null,
                    'is_active' => (bool) $row->is_active,
                    'text_uk' => $row->text_uk,
                    'text_en' => $row->text_en,
                    'text_ru' => $row->text_ru,
                ];

                $slot = $row->slot ?? ShopHeaderBanner::SLOT_TOP;
                if ($slot === ShopHeaderBanner::SLOT_BOTTOM) {
                    $bannersBottom[] = $item;
                } else {
                    $bannersTop[] = $item;
                }
            });

        return [
            'featured_left_option_value_id' => $settings->featured_left_option_value_id
                ? (string) $settings->featured_left_option_value_id
                : null,
            'featured_right_option_value_id' => $settings->featured_right_option_value_id
                ? (string) $settings->featured_right_option_value_id
                : null,
            'featured_left_panel_max_height_px' => $settings->featured_left_panel_max_height_px
                ?: $settings->featured_panel_max_height_px
                ?: ShopHeaderSetting::DEFAULT_FEATURED_PANEL_MAX_HEIGHT_PX,
            'featured_right_panel_max_height_px' => $settings->featured_right_panel_max_height_px
                ?: $settings->featured_panel_max_height_px
                ?: ShopHeaderSetting::DEFAULT_FEATURED_PANEL_MAX_HEIGHT_PX,
            'banner_height_px' => $settings->banner_height_px
                ?: ShopHeaderSetting::DEFAULT_BANNER_HEIGHT_PX,
            'chip_styles' => $settings->resolvedChipStylesForForm(),
            'banners_top' => $bannersTop,
            'banners_bottom' => $bannersBottom,
        ];
    }

    public function save(): void
    {
        try {
            $this->beginDatabaseTransaction();
            $this->callHook('beforeValidate');
            $data = $this->form->getState();
            $this->callHook('afterValidate');

            $leftId = isset($data['featured_left_option_value_id']) ? (int) $data['featured_left_option_value_id'] : 0;
            $rightId = isset($data['featured_right_option_value_id']) ? (int) $data['featured_right_option_value_id'] : 0;

            if ($leftId > 0 && $rightId > 0 && $leftId === $rightId) {
                throw ValidationException::withMessages([
                    'data.featured_right_option_value_id' => 'Права категорія має відрізнятися від лівої.',
                ]);
            }

            $settings = ShopHeaderSetting::record();
            $leftPanelHeight = self::nullablePositivePx($data['featured_left_panel_max_height_px'] ?? null, 80, 1200);
            $rightPanelHeight = self::nullablePositivePx($data['featured_right_panel_max_height_px'] ?? null, 80, 1200);

            $settings->update([
                'featured_left_option_value_id' => $leftId > 0 ? $leftId : null,
                'featured_right_option_value_id' => $rightId > 0 ? $rightId : null,
                'featured_left_panel_max_height_px' => $leftPanelHeight,
                'featured_right_panel_max_height_px' => $rightPanelHeight,
                // Keep shared column in sync for older installs / fallbacks.
                'featured_panel_max_height_px' => $leftPanelHeight ?? $rightPanelHeight,
                'banner_height_px' => self::nullablePositivePx($data['banner_height_px'] ?? null, 64, 800),
                'chip_styles' => ShopHeaderChipStyles::sanitizeForStorage(
                    is_array($data['chip_styles'] ?? null) ? $data['chip_styles'] : null
                ),
            ]);

            $this->persistBanners(
                $data['banners_top'] ?? [],
                ShopHeaderBanner::SLOT_TOP,
            );
            $this->persistBanners(
                $data['banners_bottom'] ?? [],
                ShopHeaderBanner::SLOT_BOTTOM,
            );

            $this->callHook('afterSave');
        } catch (Throwable $exception) {
            $this->rollBackDatabaseTransaction();
            throw $exception;
        }

        $this->commitDatabaseTransaction();

        Notification::make()
            ->success()
            ->title('Збережено')
            ->body('Налаштування шапки каталогу оновлено.')
            ->send();

        $this->form->fill($this->loadFormData());
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function persistBanners(array $rows, string $slot): void
    {
        if (! array_key_exists($slot, ShopHeaderBanner::slotLabels())) {
            $slot = ShopHeaderBanner::SLOT_TOP;
        }

        $keptIds = [];
        $position = 0;

        foreach ($rows as $row) {
            $path = $this->normalizeBannerImagePath($row['image_path'] ?? null);

            $existingId = max(0, (int) ($row['id'] ?? 0));
            $existing = $existingId > 0 ? ShopHeaderBanner::query()->find($existingId) : null;

            if ($path === null && $existing !== null) {
                $path = $this->normalizeBannerImagePath($existing->image_path);
            }

            if ($path === null) {
                continue;
            }

            $linkType = (string) ($row['link_type'] ?? ShopHeaderBanner::LINK_NONE);
            if (! array_key_exists($linkType, ShopHeaderBanner::linkTypeLabels())) {
                $linkType = ShopHeaderBanner::LINK_NONE;
            }

            $attributes = [
                'image_path' => $path,
                'link_type' => $linkType,
                'product_id' => $linkType === ShopHeaderBanner::LINK_PRODUCT
                    ? max(0, (int) ($row['product_id'] ?? 0)) ?: null
                    : null,
                'url' => $linkType === ShopHeaderBanner::LINK_URL
                    ? trim((string) ($row['url'] ?? '')) ?: null
                    : null,
                'category_option_value_id' => $linkType === ShopHeaderBanner::LINK_CATEGORY
                    ? max(0, (int) ($row['category_option_value_id'] ?? 0)) ?: null
                    : null,
                'sort_order' => $position,
                'slot' => $slot,
                'is_active' => (bool) ($row['is_active'] ?? true),
                'text_uk' => trim((string) ($row['text_uk'] ?? '')) ?: null,
                'text_en' => trim((string) ($row['text_en'] ?? '')) ?: null,
                'text_ru' => trim((string) ($row['text_ru'] ?? '')) ?: null,
            ];

            if ($existing !== null) {
                $existing->update($attributes);
                $banner = $existing->fresh();
            } else {
                $banner = ShopHeaderBanner::query()->create($attributes);
            }

            if (! $banner instanceof ShopHeaderBanner) {
                continue;
            }

            $finalPath = $this->relocateBannerImage($path, (int) $banner->id);
            if ($finalPath !== $banner->image_path) {
                $banner->update(['image_path' => $finalPath]);
            }

            $keptIds[] = (int) $banner->id;
            $position++;
        }

        $slotQuery = ShopHeaderBanner::query()->where('slot', $slot);
        if ($keptIds !== []) {
            $slotQuery->whereNotIn('id', $keptIds)->delete();
        } else {
            $slotQuery->delete();
        }
    }

    private function normalizeBannerImagePath(mixed $incoming): ?string
    {
        $path = StoragePublicPath::normalizeFileUploadState($incoming);
        if ($path === null) {
            return null;
        }

        $disk = Storage::disk('public');
        if (! $disk->exists($path) || str_starts_with($path, 'livewire-tmp/')) {
            return null;
        }

        return $path;
    }

    private function isBannerDraftPath(string $path): bool
    {
        $draft = ListingPhotoUpload::headerBannerDraftDirectory();

        return $path === $draft || str_starts_with($path, $draft.'/');
    }

    private function relocateBannerImage(string $path, int $bannerId): string
    {
        if ($bannerId <= 0 || ! $this->isBannerDraftPath($path)) {
            return $path;
        }

        $disk = Storage::disk('public');
        $targetDirectory = ListingPhotoUpload::headerBannerDirectory($bannerId);
        $filename = basename($path);
        $newPath = $targetDirectory.'/'.$filename;

        if ($disk->exists($newPath)) {
            if ($disk->exists($path)) {
                $disk->delete($path);
            }

            return $newPath;
        }

        if (! $disk->exists($path)) {
            return $path;
        }

        $disk->makeDirectory($targetDirectory);
        $disk->move($path, $newPath);

        return $newPath;
    }

    /**
     * @return array<string, string>
     */
    private static function rootCategoryOptions(): array
    {
        $groupId = OptionGroup::systemCategoryGroupIdForCatalog();
        if ($groupId <= 0) {
            return [];
        }

        return OptionValue::query()
            ->where('option_group_id', $groupId)
            ->where('is_active', true)
            ->whereNull('parent_id')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name'])
            ->mapWithKeys(fn (OptionValue $value): array => [(string) $value->id => (string) $value->name])
            ->all();
    }

    /**
     * @return array<string, string>
     */
    private static function categoryOptions(): array
    {
        $groupId = OptionGroup::systemCategoryGroupIdForCatalog();
        if ($groupId <= 0) {
            return [];
        }

        return OptionValue::query()
            ->where('option_group_id', $groupId)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name'])
            ->mapWithKeys(fn (OptionValue $value): array => [(string) $value->id => (string) $value->name])
            ->all();
    }

    public function defaultForm(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->statePath('data');
    }

    public function form(Schema $schema): Schema
    {
        $rootCategoryOptions = static::rootCategoryOptions();
        $categoryOptions = static::categoryOptions();

        $productSelect = static function (): Select {
            return Select::make('product_id')
                ->label('Товар')
                ->searchable()
                ->options(fn (): array => ProductAdminSelectSearch::titleChoices())
                ->getOptionLabelUsing(fn ($value): ?string => ProductAdminSelectSearch::titleForId((int) $value))
                ->native(false)
                ->visible(fn (Get $get): bool => $get('link_type') === ShopHeaderBanner::LINK_PRODUCT);
        };

        $urlInput = static fn (): TextInput => TextInput::make('url')
            ->label('Посилання')
            ->placeholder('/catalog або https://…')
            ->maxLength(2048)
            ->visible(fn (Get $get): bool => $get('link_type') === ShopHeaderBanner::LINK_URL);

        $categorySelect = static fn (): Select => Select::make('category_option_value_id')
            ->label('Категорія')
            ->searchable()
            ->options($categoryOptions)
            ->native(false)
            ->visible(fn (Get $get): bool => $get('link_type') === ShopHeaderBanner::LINK_CATEGORY);

        $bannerSlideSchema = static function () use ($productSelect, $urlInput, $categorySelect): array {
            return [
                Hidden::make('id')
                    ->dehydrated(),
                Hidden::make('upload_key')
                    ->default(fn (): string => (string) Str::uuid())
                    ->dehydrated(),
                ListingPhotoUpload::applyBannerImageEditorIfAvailable(
                    ListingPhotoUpload::applyBannerPhotosLayout(
                        ListingPhotoUpload::make('image_path')
                            ->label('Зображення')
                            ->required()
                            ->disk('public')
                            ->visibility('public')
                            ->directory(fn (Get $get): string => ListingPhotoUpload::headerBannerRowDirectory($get))
                    )
                ),
                Select::make('link_type')
                    ->label('Тип посилання')
                    ->options(ShopHeaderBanner::linkTypeLabels())
                    ->default(ShopHeaderBanner::LINK_NONE)
                    ->required()
                    ->native(false)
                    ->live(),
                $productSelect(),
                $urlInput(),
                $categorySelect(),
                Toggle::make('is_active')
                    ->label('Активний')
                    ->default(true),
                TextInput::make('text_uk')
                    ->label('Текст на банері (UK)')
                    ->maxLength(512)
                    ->columnSpanFull(),
                TextInput::make('text_en')
                    ->label('Текст на банері (EN)')
                    ->maxLength(512)
                    ->columnSpanFull(),
                TextInput::make('text_ru')
                    ->label('Текст на банері (RU)')
                    ->maxLength(512)
                    ->columnSpanFull(),
            ];
        };

        return $schema
            ->components([
                Tabs::make('catalogHeaderTabs')
                    ->tabs([
                        Tab::make('featured')
                            ->label('Розгорнуті категорії')
                            ->schema([
                                Section::make('Бокові блоки шапки')
                                    ->description('Оберіть дві кореневі категорії для лівого та правого розгорнутих блоків. Якщо не обрано — використовуються перші дві за порядком сортування.')
                                    ->schema([
                                        Select::make('featured_left_option_value_id')
                                            ->label('Ліва категорія')
                                            ->placeholder('За замовчуванням (перша)')
                                            ->options($rootCategoryOptions)
                                            ->searchable()
                                            ->native(false),
                                        Select::make('featured_right_option_value_id')
                                            ->label('Права категорія')
                                            ->placeholder('За замовчуванням (друга)')
                                            ->options($rootCategoryOptions)
                                            ->searchable()
                                            ->native(false),
                                        TextInput::make('featured_left_panel_max_height_px')
                                            ->label('Макс. висота лівого блоку (px)')
                                            ->numeric()
                                            ->minValue(80)
                                            ->maxValue(1200)
                                            ->default(ShopHeaderSetting::DEFAULT_FEATURED_PANEL_MAX_HEIGHT_PX)
                                            ->helperText('Якщо контенту більше — зʼявиться прокрутка. Порожньо = '.ShopHeaderSetting::DEFAULT_FEATURED_PANEL_MAX_HEIGHT_PX.'px.'),
                                        TextInput::make('featured_right_panel_max_height_px')
                                            ->label('Макс. висота правого блоку (px)')
                                            ->numeric()
                                            ->minValue(80)
                                            ->maxValue(1200)
                                            ->default(ShopHeaderSetting::DEFAULT_FEATURED_PANEL_MAX_HEIGHT_PX)
                                            ->helperText('Якщо контенту більше — зʼявиться прокрутка. Порожньо = '.ShopHeaderSetting::DEFAULT_FEATURED_PANEL_MAX_HEIGHT_PX.'px.'),
                                    ])
                                    ->columns(2)
                                    ->columnSpanFull(),
                            ]),
                        Tab::make('banner')
                            ->label('Банери')
                            ->schema([
                                Section::make('Розмір банерів')
                                    ->description('Висота слота верхнього та нижнього банера в шапці каталогу (desktop і mobile).')
                                    ->schema([
                                        TextInput::make('banner_height_px')
                                            ->label('Висота банера (px)')
                                            ->numeric()
                                            ->minValue(64)
                                            ->maxValue(800)
                                            ->default(ShopHeaderSetting::DEFAULT_BANNER_HEIGHT_PX)
                                            ->helperText('Порожньо = '.ShopHeaderSetting::DEFAULT_BANNER_HEIGHT_PX.'px.'),
                                    ])
                                    ->columns(1)
                                    ->columnSpanFull(),
                                Section::make('Верхній банер')
                                    ->description('Перший ряд у центрі шапки каталогу (desktop і mobile). Карусель, якщо слайдів кілька. Рекомендоване співвідношення ~3:1.')
                                    ->schema([
                                        Repeater::make('banners_top')
                                            ->hiddenLabel()
                                            ->reorderable()
                                            ->addActionLabel('Додати слайд')
                                            ->defaultItems(0)
                                            ->schema($bannerSlideSchema())
                                            ->itemLabel(fn (array $state): string => trim((string) ($state['text_uk'] ?? '')) !== ''
                                                ? (string) $state['text_uk']
                                                : 'Слайд верхнього банера'),
                                    ])
                                    ->columnSpanFull(),
                                Section::make('Нижній банер')
                                    ->description('Другий ряд під верхнім банером у шапці каталогу.')
                                    ->schema([
                                        Repeater::make('banners_bottom')
                                            ->hiddenLabel()
                                            ->reorderable()
                                            ->addActionLabel('Додати слайд')
                                            ->defaultItems(0)
                                            ->schema($bannerSlideSchema())
                                            ->itemLabel(fn (array $state): string => trim((string) ($state['text_uk'] ?? '')) !== ''
                                                ? (string) $state['text_uk']
                                                : 'Слайд нижнього банера'),
                                    ])
                                    ->columnSpanFull(),
                            ]),
                        Tab::make('chip_styles')
                            ->label('Стиль чіпів')
                            ->schema([
                                Grid::make([
                                    'default' => 1,
                                    'lg' => 5,
                                ])
                                    ->schema([
                                        Section::make('Налаштування')
                                            ->description('Порожні поля залишають поточний вигляд вітрини. Палітра з 6 кольорів за замовчуванням як зараз; увімкніть кастомну, щоб змінити.')
                                            ->schema([
                                                Section::make('Центральні чіпи')
                                                    ->description('Кнопки в центрі шапки (Каталог, Акції тощо).')
                                                    ->schema(self::chipLevelFields('chip_styles.center'))
                                                    ->columns(2)
                                                    ->collapsed()
                                                    ->columnSpanFull(),
                                                Section::make('Бокові батьківські')
                                                    ->description('Заголовки розгорнутих блоків зліва/справа.')
                                                    ->schema(self::chipLevelFields('chip_styles.side_parent'))
                                                    ->columns(2)
                                                    ->collapsed()
                                                    ->columnSpanFull(),
                                                Section::make('Підкатегорії (1-й рівень)')
                                                    ->description('Прямі підкатегорії в розгорнутих бокових блоках.')
                                                    ->schema([
                                                        ...self::chipLevelFields('chip_styles.side_sub'),
                                                        ...self::chipColorFields('chip_styles.side_sub'),
                                                        Toggle::make('chip_styles.side_sub.use_palette')
                                                            ->label('Брати кольори з палітри чіпів')
                                                            ->helperText('Якщо увімкнено — ігнорує кольори вище й бере tint/hover/active з палітри.')
                                                            ->live()
                                                            ->columnSpanFull(),
                                                    ])
                                                    ->columns(2)
                                                    ->collapsed()
                                                    ->columnSpanFull(),
                                                Section::make('Вкладені рівні (2+)')
                                                    ->description('Наступні рівні всередині розгорнутої підкатегорії (кнопки глибшого рівня).')
                                                    ->schema([
                                                        ...self::chipLevelFields('chip_styles.side_nested'),
                                                        ...self::chipColorFields('chip_styles.side_nested'),
                                                        Toggle::make('chip_styles.side_nested.use_palette')
                                                            ->label('Брати кольори з палітри чіпів')
                                                            ->helperText('Якщо увімкнено — ігнорує кольори вище й бере tint/hover/active з палітри.')
                                                            ->live()
                                                            ->columnSpanFull(),
                                                    ])
                                                    ->columns(2)
                                                    ->collapsed()
                                                    ->columnSpanFull(),
                                                Section::make('Палітра кольорів')
                                                    ->description('6 кольорів по черзі для кнопок. Порядок призначення на вітрині не змінюється.')
                                                    ->schema([
                                                        Toggle::make('chip_styles.custom_palette_enabled')
                                                            ->label('Увімкнути власну палітру')
                                                            ->helperText('Вимкнено — стандартні 6 кольорів як раніше.')
                                                            ->live()
                                                            ->columnSpanFull(),
                                                        ...self::chipPaletteFields(),
                                                    ])
                                                    ->columns(2)
                                                    ->collapsed()
                                                    ->columnSpanFull(),
                                                Actions::make([
                                                    Action::make('resetChipStyles')
                                                        ->label('Скинути стилі до дефолту')
                                                        ->color('gray')
                                                        ->requiresConfirmation()
                                                        ->modalHeading('Скинути стилі чіпів?')
                                                        ->modalDescription('Усі кастомні розміри, відступи й палітра буде скинуто. Збережіть сторінку після скидання.')
                                                        ->action(function (): void {
                                                            $this->resetChipStylesToDefault();
                                                        }),
                                                ])->columnSpanFull(),
                                            ])
                                            ->columnSpan([
                                                'default' => 1,
                                                'lg' => 3,
                                            ]),
                                        Section::make('Превʼю')
                                            ->description('Оновлюється миттєво під час зміни полів (без збереження).')
                                            ->schema([
                                                ViewField::make('chip_styles_preview')
                                                    ->hiddenLabel()
                                                    ->view('filament.admin.catalog-header-chip-styles-preview')
                                                    ->viewData(function (): array {
                                                        $styles = is_array($this->data['chip_styles'] ?? null)
                                                            ? $this->data['chip_styles']
                                                            : ShopHeaderChipStyles::formDefaults();

                                                        return [
                                                            'chipStyles' => $styles,
                                                        ];
                                                    })
                                                    ->dehydrated(false),
                                            ])
                                            ->columnSpan([
                                                'default' => 1,
                                                'lg' => 2,
                                            ])
                                            ->extraAttributes([
                                                'style' => 'position: sticky; top: 1rem;',
                                            ]),
                                    ])
                                    ->columnSpanFull(),
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    /**
     * @return list<\Filament\Forms\Components\Component>
     */
    private static function chipLevelFields(string $prefix): array
    {
        return [
            TextInput::make("{$prefix}.font_size_px")
                ->label('Розмір шрифту (px)')
                ->numeric()
                ->minValue(10)
                ->maxValue(32)
                ->placeholder('за замовчуванням')
                ->nullable()
                ->live(debounce: 150),
            Select::make("{$prefix}.font_weight")
                ->label('Товщина')
                ->options([
                    400 => '400',
                    500 => '500',
                    600 => '600',
                    700 => '700',
                    800 => '800',
                    900 => '900',
                ])
                ->placeholder('за замовчуванням')
                ->native(false)
                ->nullable()
                ->live(),
            Select::make("{$prefix}.font_style")
                ->label('Стиль')
                ->options([
                    'normal' => 'Звичайний',
                    'italic' => 'Курсив',
                ])
                ->native(false)
                ->live(),
            TextInput::make("{$prefix}.padding_x_px")
                ->label('Відступ по боках (px)')
                ->numeric()
                ->minValue(0)
                ->maxValue(32)
                ->placeholder('за замовчуванням')
                ->nullable()
                ->live(debounce: 150),
            TextInput::make("{$prefix}.padding_y_px")
                ->label('Відступ зверху/знизу (px)')
                ->numeric()
                ->minValue(0)
                ->maxValue(32)
                ->placeholder('за замовчуванням')
                ->nullable()
                ->live(debounce: 150),
            TextInput::make("{$prefix}.radius_px")
                ->label('Радіус (px)')
                ->numeric()
                ->minValue(0)
                ->maxValue(40)
                ->placeholder('за замовчуванням')
                ->nullable()
                ->live(debounce: 150),
            TextInput::make("{$prefix}.gap_px")
                ->label('Проміжок між кнопками (px)')
                ->numeric()
                ->minValue(0)
                ->maxValue(24)
                ->placeholder('за замовчуванням')
                ->nullable()
                ->live(debounce: 150),
        ];
    }

    /**
     * @return list<\Filament\Forms\Components\Component>
     */
    private static function chipColorFields(string $prefix): array
    {
        return [
            ColorPicker::make("{$prefix}.bg")
                ->label('Фон')
                ->live(debounce: 100),
            ColorPicker::make("{$prefix}.text")
                ->label('Текст')
                ->live(debounce: 100),
            ColorPicker::make("{$prefix}.border")
                ->label('Рамка')
                ->live(debounce: 100),
            ColorPicker::make("{$prefix}.hover_bg")
                ->label('Hover фон')
                ->live(debounce: 100),
            ColorPicker::make("{$prefix}.hover_text")
                ->label('Hover текст')
                ->live(debounce: 100),
            ColorPicker::make("{$prefix}.active_bg")
                ->label('Active фон')
                ->live(debounce: 100),
            ColorPicker::make("{$prefix}.active_text")
                ->label('Active текст')
                ->live(debounce: 100)
                ->columnSpanFull(),
        ];
    }

    /**
     * @return list<\Filament\Forms\Components\Component>
     */
    private static function chipPaletteFields(): array
    {
        $fields = [];
        for ($i = 1; $i <= 6; $i++) {
            $fields[] = Section::make("Колір {$i}")
                ->schema([
                    ColorPicker::make("chip_styles.palette.{$i}.accent")
                        ->label('Accent')
                        ->live(debounce: 100),
                    TextInput::make("chip_styles.palette.{$i}.tint")
                        ->label('Фон (tint)')
                        ->placeholder('rgba(...) або #hex')
                        ->helperText('Можна rgba, як у стандартній палітрі.')
                        ->live(debounce: 150),
                    ColorPicker::make("chip_styles.palette.{$i}.text")
                        ->label('Текст')
                        ->live(debounce: 100),
                    ColorPicker::make("chip_styles.palette.{$i}.hover_bg")
                        ->label('Hover фон')
                        ->live(debounce: 100),
                    ColorPicker::make("chip_styles.palette.{$i}.hover_text")
                        ->label('Hover текст')
                        ->live(debounce: 100),
                    ColorPicker::make("chip_styles.palette.{$i}.active_bg")
                        ->label('Active фон')
                        ->live(debounce: 100),
                    ColorPicker::make("chip_styles.palette.{$i}.active_text")
                        ->label('Active текст')
                        ->live(debounce: 100),
                ])
                ->columns(2)
                ->collapsed()
                ->visible(fn (Get $get): bool => (bool) $get('chip_styles.custom_palette_enabled'))
                ->columnSpanFull();
        }

        return $fields;
    }

    public function resetChipStylesToDefault(): void
    {
        $data = $this->data ?? [];
        $data['chip_styles'] = ShopHeaderChipStyles::formDefaults();
        $this->form->fill($data);

        Notification::make()
            ->success()
            ->title('Стилі скинуто')
            ->body('Натисніть «Зберегти», щоб застосувати на вітрині.')
            ->send();
    }

    private static function nullablePositivePx(mixed $value, int $min, int $max): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $n = (int) $value;
        if ($n <= 0) {
            return null;
        }

        return min($max, max($min, $n));
    }

    /**
     * @return list<Action>
     */
    protected function getFormActions(): array
    {
        return [
            $this->getSaveFormAction(),
        ];
    }

    protected function getSaveFormAction(): Action
    {
        return Action::make('save')
            ->label('Зберегти')
            ->submit('save')
            ->keyBindings(['mod+s']);
    }

    public function getTitle(): string|Htmlable
    {
        return static::$title ?? 'Шапка каталогу';
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->getFormContentComponent(),
            ]);
    }

    public function getFormContentComponent(): Component
    {
        return Form::make([EmbeddedSchema::make('form')])
            ->id('form')
            ->livewireSubmitHandler('save')
            ->footer([
                Actions::make($this->getFormActions())
                    ->alignment($this->getFormActionsAlignment())
                    ->fullWidth($this->hasFullWidthFormActions())
                    ->sticky($this->areFormActionsSticky())
                    ->key('form-actions'),
            ]);
    }

    protected function hasFullWidthFormActions(): bool
    {
        return false;
    }
}
