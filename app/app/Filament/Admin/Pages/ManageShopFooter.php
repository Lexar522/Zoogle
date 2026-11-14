<?php

namespace App\Filament\Admin\Pages;

use App\Models\ShopFooterBrand;
use App\Models\ShopFooterColumn;
use App\Models\ShopFooterFaqItem;
use App\Models\ShopFooterLink;
use App\Models\ShopInfoPage;
use BackedEnum;
use Filament\Actions\Action;
use App\Models\ProductCareArticle;
use App\Support\RichTextSanitizer;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Concerns\CanUseDatabaseTransactions;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Exceptions\Halt;
use Filament\Support\Facades\FilamentView;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Schema as DbSchema;
use Illuminate\Support\Str;
use Throwable;
use UnitEnum;

/**
 * @property-read Schema $form
 */
class ManageShopFooter extends Page
{
    use CanUseDatabaseTransactions;

    /** Slug для шляху /info/{slug}: лише a-z, 0-9 та дефіс між сегментами. */
    private const INFO_SLUG_PATTERN = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/';

    /** Не можна використовувати як slug (службові сегменти). */
    private const RESERVED_INFO_SLUGS = [
        'admin', 'api', 'storage', 'vendor', 'filament', 'livewire',
    ];

    protected static ?string $slug = 'footer';

    protected static ?string $title = 'Футер магазину';

    protected static ?string $navigationLabel = 'Футер магазину';

    protected static string|UnitEnum|null $navigationGroup = 'Налаштування';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBars3BottomLeft;

    protected static ?int $navigationSort = 92;

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill($this->loadFormData());
    }

    /**
     * @return array<string, mixed>
     */
    private function loadFormData(): array
    {
        $brand = ShopFooterBrand::record();

        $columns = ShopFooterColumn::query()
            ->with('links')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(static function (ShopFooterColumn $col): array {
                return [
                    'title_uk' => $col->title_uk ?? '',
                    'title_en' => $col->title_en ?? '',
                    'title_ru' => $col->title_ru ?? '',
                    'links' => $col->links
                        ->map(static function (ShopFooterLink $link): array {
                            return [
                                'label_uk' => $link->label_uk ?? '',
                                'label_en' => $link->label_en ?? '',
                                'label_ru' => $link->label_ru ?? '',
                                'url' => $link->url,
                                'open_new_tab' => (bool) $link->open_new_tab,
                            ];
                        })
                        ->values()
                        ->all(),
                ];
            })
            ->values()
            ->all();

        $informationPages = self::infoPagesTreeFromDatabase();
        $hasFaqTitleColumns = DbSchema::hasColumn('shop_footer_brands', 'faq_title_uk');
        $faqItems = [];
        if (DbSchema::hasTable('shop_footer_faq_items')) {
            $faqItems = ShopFooterFaqItem::query()
                ->ordered()
                ->get()
                ->map(static function (ShopFooterFaqItem $item): array {
                    return [
                        'question_uk' => $item->question_uk ?? '',
                        'question_en' => $item->question_en ?? '',
                        'question_ru' => $item->question_ru ?? '',
                        'answer_uk' => RichTextSanitizer::coerceForFilamentRichEditor($item->answer_uk) ?? '',
                        'answer_en' => RichTextSanitizer::coerceForFilamentRichEditor($item->answer_en) ?? '',
                        'answer_ru' => RichTextSanitizer::coerceForFilamentRichEditor($item->answer_ru) ?? '',
                    ];
                })
                ->values()
                ->all();
        }

        return [
            'brand' => [
                'site_title' => $brand->site_title ?? '',
                'body' => RichTextSanitizer::coerceForFilamentRichEditor($brand->body) ?? '',
                'phone' => $brand->phone ?? '',
                'faq_title_uk' => $hasFaqTitleColumns ? ($brand->faq_title_uk ?? '') : '',
                'faq_title_en' => $hasFaqTitleColumns ? ($brand->faq_title_en ?? '') : '',
                'faq_title_ru' => $hasFaqTitleColumns ? ($brand->faq_title_ru ?? '') : '',
            ],
            'information_pages' => $informationPages,
            'columns' => $columns,
            'faq_items' => $faqItems,
        ];
    }

    public function save(): void
    {
        try {
            $this->beginDatabaseTransaction();
            $this->callHook('beforeValidate');
            $data = $this->form->getState();
            $data['information_pages'] = self::informationPagesWithAutoSlugs($data['information_pages'] ?? []);
            $this->callHook('afterValidate');

            $this->validateInformationPages($data);
            $this->validateFooterColumns($data);

            $brandData = is_array($data['brand'] ?? null) ? $data['brand'] : [];
            $brand = ShopFooterBrand::record();
            $brand->site_title = self::nullableString($brandData['site_title'] ?? null);
            $brand->body = self::nullableRichHtml($brandData['body'] ?? null);
            $brand->phone = self::nullableString($brandData['phone'] ?? null);
            $brand->logo_path = null;
            if (DbSchema::hasColumn('shop_footer_brands', 'faq_title_uk')) {
                $brand->faq_title_uk = self::nullableString($brandData['faq_title_uk'] ?? null);
                $brand->faq_title_en = self::nullableString($brandData['faq_title_en'] ?? null);
                $brand->faq_title_ru = self::nullableString($brandData['faq_title_ru'] ?? null);
            }
            $brand->save();

            ShopInfoPage::query()->delete();
            $infoRows = is_array($data['information_pages'] ?? null) ? $data['information_pages'] : [];
            $infoSort = 0;
            self::saveInfoPagesTree($infoRows, null, $infoSort);

            ShopFooterColumn::query()->delete();

            $columns = is_array($data['columns'] ?? null) ? $data['columns'] : [];
            foreach ($columns as $i => $colData) {
                if (! is_array($colData)) {
                    continue;
                }
                $titleUk = self::nullableString($colData['title_uk'] ?? null);
                $titleEn = self::nullableString($colData['title_en'] ?? null);
                $titleRu = self::nullableString($colData['title_ru'] ?? null);
                $links = is_array($colData['links'] ?? null) ? $colData['links'] : [];
                $validLinks = [];
                foreach ($links as $j => $linkData) {
                    if (! is_array($linkData)) {
                        continue;
                    }
                    $url = trim((string) ($linkData['url'] ?? ''));
                    if ($url === '') {
                        continue;
                    }
                    $validLinks[] = [
                        'sort_order' => (int) $j,
                        'label_uk' => self::nullableString($linkData['label_uk'] ?? null),
                        'label_en' => self::nullableString($linkData['label_en'] ?? null),
                        'label_ru' => self::nullableString($linkData['label_ru'] ?? null),
                        'url' => $url,
                        'open_new_tab' => ! empty($linkData['open_new_tab']),
                    ];
                }
                // Порожні рядки-заготовки в репітері ігноруємо.
                if ($titleUk === null && $titleEn === null && $titleRu === null && $validLinks === []) {
                    continue;
                }

                $col = ShopFooterColumn::query()->create([
                    'sort_order' => (int) $i,
                    'title_uk' => $titleUk,
                    'title_en' => $titleEn,
                    'title_ru' => $titleRu,
                ]);

                foreach ($validLinks as $linkRow) {
                    ShopFooterLink::query()->create([
                        'shop_footer_column_id' => $col->id,
                        ...$linkRow,
                    ]);
                }
            }

            if (DbSchema::hasTable('shop_footer_faq_items')) {
                ShopFooterFaqItem::query()->delete();
                $faqRows = is_array($data['faq_items'] ?? null) ? $data['faq_items'] : [];
                foreach ($faqRows as $i => $faqData) {
                    if (! is_array($faqData)) {
                        continue;
                    }
                    $questionUk = self::nullableString($faqData['question_uk'] ?? null);
                    $questionEn = self::nullableString($faqData['question_en'] ?? null);
                    $questionRu = self::nullableString($faqData['question_ru'] ?? null);
                    if ($questionUk === null && $questionEn === null && $questionRu === null) {
                        continue;
                    }
                    ShopFooterFaqItem::query()->create([
                        'sort_order' => (int) $i,
                        'question_uk' => $questionUk,
                        'question_en' => $questionEn,
                        'question_ru' => $questionRu,
                        'answer_uk' => self::nullableRichHtml($faqData['answer_uk'] ?? null),
                        'answer_en' => self::nullableRichHtml($faqData['answer_en'] ?? null),
                        'answer_ru' => self::nullableRichHtml($faqData['answer_ru'] ?? null),
                    ]);
                }
            }

            $this->callHook('afterSave');
        } catch (Halt $exception) {
            $this->rollBackDatabaseTransaction();

            return;
        } catch (Throwable $exception) {
            $this->rollBackDatabaseTransaction();

            throw $exception;
        }

        $this->commitDatabaseTransaction();

        Notification::make()
            ->success()
            ->title('Збережено')
            ->body('Футер на вітрині оновлено.')
            ->send();

        $this->form->fill($this->loadFormData());

        if ($redirectUrl = $this->getRedirectUrl()) {
            $this->redirect($redirectUrl, navigate: FilamentView::hasSpaMode($redirectUrl));
        }
    }

    /**
     * @return list<array{title_uk: string, body_uk: string, children: list<mixed>}>
     */
    private static function infoPagesTreeFromDatabase(): array
    {
        $pages = ShopInfoPage::query()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        /** @var array<int, list<ShopInfoPage>> $byParent */
        $byParent = [];
        foreach ($pages as $page) {
            $key = $page->parent_id ?? 0;
            $byParent[$key][] = $page;
        }

        return self::mapInfoPagesTreeNodes($byParent, 0);
    }

    /**
     * @param  array<int, list<ShopInfoPage>>  $byParent
     * @return list<array{title_uk: string, body_uk: string, children: list<mixed>}>
     */
    private static function mapInfoPagesTreeNodes(array $byParent, int $parentKey): array
    {
        $nodes = [];
        foreach ($byParent[$parentKey] ?? [] as $page) {
            $nodes[] = [
                'title_uk' => $page->title_uk ?? '',
                'body_uk' => RichTextSanitizer::coerceForFilamentRichEditor($page->body_uk) ?? '',
                'children' => self::mapInfoPagesTreeNodes($byParent, $page->id),
            ];
        }

        return $nodes;
    }

    /**
     * @param  array<int|string, mixed>  $rows
     */
    private static function saveInfoPagesTree(array $rows, ?int $parentId, int &$sort): void
    {
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $slug = strtolower(trim((string) ($row['slug'] ?? '')));
            if ($slug === '') {
                continue;
            }
            $page = ShopInfoPage::query()->create([
                'parent_id' => $parentId,
                'sort_order' => $sort,
                'slug' => $slug,
                'title_uk' => self::nullableString($row['title_uk'] ?? null),
                'title_en' => null,
                'title_ru' => null,
                'body_uk' => self::nullableRichHtml($row['body_uk'] ?? null),
                'body_en' => null,
                'body_ru' => null,
            ]);
            $sort++;

            $children = is_array($row['children'] ?? null) ? $row['children'] : [];
            if ($children !== []) {
                $childSort = 0;
                self::saveInfoPagesTree($children, $page->id, $childSort);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function validateFooterColumns(array $data): void
    {
        $columns = is_array($data['columns'] ?? null) ? $data['columns'] : [];
        foreach ($columns as $i => $colData) {
            if (! is_array($colData)) {
                continue;
            }
            $titleUk = trim((string) ($colData['title_uk'] ?? ''));
            $titleEn = trim((string) ($colData['title_en'] ?? ''));
            $titleRu = trim((string) ($colData['title_ru'] ?? ''));
            $label = $titleUk !== '' ? $titleUk : ($titleEn !== '' ? $titleEn : ($titleRu !== '' ? $titleRu : 'Колонка #'.((int) $i + 1)));

            $links = is_array($colData['links'] ?? null) ? $colData['links'] : [];
            $validLinkCount = 0;
            foreach ($links as $linkData) {
                if (! is_array($linkData)) {
                    continue;
                }
                if (trim((string) ($linkData['url'] ?? '')) !== '') {
                    $validLinkCount++;
                }
            }

            $hasTitle = $titleUk !== '' || $titleEn !== '' || $titleRu !== '';
            if (! $hasTitle && $validLinkCount === 0) {
                continue;
            }
            if ($validLinkCount === 0) {
                self::haltInfoValidation(
                    '«'.$label.'»: додайте хоча б одне посилання з URL, інакше колонка буде порожньою на вітрині. Або видаліть цю колонку.'
                );
            }
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function validateInformationPages(array $data): void
    {
        $rows = is_array($data['information_pages'] ?? null) ? $data['information_pages'] : [];
        $seenSlugs = [];
        foreach (self::flattenInfoPagesTree($rows) as $entry) {
            $row = $entry['row'];
            $label = $entry['label'];
            if (! is_array($row)) {
                continue;
            }
            $title = trim((string) ($row['title_uk'] ?? ''));
            $hasBody = RichTextSanitizer::hasVisibleContent(
                is_string($row['body_uk'] ?? null) ? (string) $row['body_uk'] : null
            );
            $children = is_array($row['children'] ?? null) ? $row['children'] : [];
            $hasChildren = self::infoTreeHasMeaningfulNodes($children);
            if ($title === '' && ! $hasBody && ! $hasChildren) {
                continue;
            }
            if ($title === '' && ($hasBody || $hasChildren)) {
                self::haltInfoValidation('«'.$label.'»: вкажіть назву — вона показується у футері або на сторінці розділу.');
            }
            $slug = strtolower(trim((string) ($row['slug'] ?? '')));
            if ($slug === '') {
                self::haltInfoValidation('«'.$label.'»: не вдалося зробити адресу з назви. Додайте до назви літери або цифри.');
            }
            if (strlen($slug) > 128) {
                self::haltInfoValidation('«'.$label.'»: slug «'.$slug.'» занадто довгий (макс. 128 символів).');
            }
            if (preg_match(self::INFO_SLUG_PATTERN, $slug) !== 1) {
                self::haltInfoValidation('«'.$label.'»: slug «'.$slug.'» — дозволені лише малі літери a–z, цифри та дефіс між блоками.');
            }
            if (in_array($slug, self::RESERVED_INFO_SLUGS, true)) {
                self::haltInfoValidation('«'.$label.'»: slug «'.$slug.'» зарезервовано. Оберіть інший.');
            }
            if (isset($seenSlugs[$slug])) {
                self::haltInfoValidation('Повторюваний slug «'.$slug.'» ('.$label.' і '.$seenSlugs[$slug].'). Кожен розділ має мати унікальну адресу.');
            }
            $seenSlugs[$slug] = $label;
        }
    }

    /**
     * @param  array<int|string, mixed>  $rows
     * @return list<array{row: array<string, mixed>, label: string}>
     */
    private static function flattenInfoPagesTree(array $rows, string $prefix = 'Розділ'): array
    {
        $flat = [];
        foreach ($rows as $i => $row) {
            if (! is_array($row)) {
                continue;
            }
            $index = (int) $i + 1;
            $label = $prefix.' #'.$index;
            $flat[] = ['row' => $row, 'label' => $label];

            $children = is_array($row['children'] ?? null) ? $row['children'] : [];
            if ($children !== []) {
                $childPrefix = match ($prefix) {
                    'Розділ' => 'Підрозділ',
                    'Підрозділ' => 'Сторінка',
                    default => 'Елемент',
                };
                $flat = array_merge($flat, self::flattenInfoPagesTree($children, $label.' → '.$childPrefix));
            }
        }

        return $flat;
    }

    /**
     * @param  array<int|string, mixed>  $rows
     */
    private static function infoTreeHasMeaningfulNodes(array $rows): bool
    {
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $title = trim((string) ($row['title_uk'] ?? ''));
            $hasBody = RichTextSanitizer::hasVisibleContent(
                is_string($row['body_uk'] ?? null) ? (string) $row['body_uk'] : null
            );
            $children = is_array($row['children'] ?? null) ? $row['children'] : [];
            if ($title !== '' || $hasBody || self::infoTreeHasMeaningfulNodes($children)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<int|string, mixed>  $pages
     * @return array<int|string, mixed>
     */
    private static function informationPagesWithAutoSlugs(mixed $pages): array
    {
        if (! is_array($pages)) {
            return [];
        }
        foreach ($pages as $i => $row) {
            if (! is_array($row)) {
                continue;
            }
            $pages[$i] = self::infoPageRowWithAutoSlug($row);
        }

        return $pages;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private static function infoPageRowWithAutoSlug(array $row): array
    {
        $slug = strtolower(trim((string) ($row['slug'] ?? '')));
        if ($slug === '') {
            $generated = self::slugFromInfoPageTitles($row);
            if ($generated !== '') {
                $row['slug'] = $generated;
            }
        }

        $children = is_array($row['children'] ?? null) ? $row['children'] : [];
        if ($children !== []) {
            foreach ($children as $j => $child) {
                if (! is_array($child)) {
                    continue;
                }
                $children[$j] = self::infoPageRowWithAutoSlug($child);
            }
            $row['children'] = $children;
        }

        return $row;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private static function slugFromInfoPageTitles(array $row): string
    {
        $t = trim((string) ($row['title_uk'] ?? ''));
        if ($t === '') {
            return '';
        }
        $slug = Str::slug($t, language: 'uk');

        return $slug;
    }

    private static function haltInfoValidation(string $message): void
    {
        Notification::make()
            ->danger()
            ->title('Футер не збережено')
            ->body($message)
            ->send();

        throw new Halt;
    }

    private static function nullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }
        $t = trim($value);

        return $t === '' ? null : $t;
    }

    private static function nullableRichHtml(mixed $value): ?string
    {
        $html = ProductCareArticle::normalizeRichEditorValueForStorage($value);
        if ($html === null || ! RichTextSanitizer::hasVisibleContent($html)) {
            return null;
        }

        return $html;
    }

    private static function makeFooterRichEditor(string $name): RichEditor
    {
        return RichEditor::make($name)
            ->fileAttachments(false)
            ->toolbarButtons([
                ['bold', 'italic', 'underline', 'strike', 'link'],
                ['h2', 'h3', 'small', 'lead'],
                ['bulletList', 'orderedList'],
                ['undo', 'redo'],
            ])
            ->dehydrateStateUsing(fn (mixed $state): ?string => ProductCareArticle::normalizeRichEditorValueForStorage($state));
    }

    protected function getRedirectUrl(): ?string
    {
        return null;
    }

    public function defaultForm(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->statePath('data');
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Блок бренду (ліва колонка)')
                    ->description(function (): string {
                        $path = parse_url(static::getUrl([], false), PHP_URL_PATH) ?: '/admin/footer';

                        return 'Сторінка в адмінці: '.$path.' (або через меню «Налаштування» → «Футер магазину»). Лого у футері таке саме, як у шапці (файл images/zoogle-logo-new.png). Текст і телефон показуються на сайті; інформаційні сторінки з блоку нижче з’являються посиланнями під брендом; додаткові колонки посилань — опційно.';
                    })
                    ->schema([
                        TextInput::make('brand.site_title')
                            ->label('Назва замість ZOOGLE')
                            ->maxLength(128)
                            ->placeholder('ZOOGLE')
                            ->helperText('Якщо порожньо — на сайті лишається «ZOOGLE».'),
                        self::makeFooterRichEditor('brand.body')
                            ->label('Текст під брендом')
                            ->helperText('Можна змінювати розмір (H2/H3, дрібний, lead), жирний/курсив, списки та посилання.')
                            ->columnSpanFull(),
                        TextInput::make('brand.phone')
                            ->label('Телефон для футера')
                            ->maxLength(64)
                            ->tel()
                            ->placeholder('+380 …'),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),
                Section::make('Інформаційні розділи')
                    ->description('До 3 рівнів: розділ → підрозділ → сторінка з текстом. У футері вітрини показуються лише розділи 1-го рівня; підрозділи та сторінки відкриваються за адресою /info/… (slug генерується з назви).')
                    ->schema([
                        Repeater::make('information_pages')
                            ->collapsible()
                            ->collapsed()
                            ->hiddenLabel()
                            ->reorderable()
                            ->addActionLabel('Додати розділ')
                            ->defaultItems(0)
                            ->schema([
                                TextInput::make('title_uk')
                                    ->label('Назва розділу (1-й рівень)')
                                    ->maxLength(255),
                                self::makeFooterRichEditor('body_uk')
                                    ->label('Вступний текст (опційно)')
                                    ->helperText('Показується на сторінці розділу над списком підрозділів.')
                                    ->columnSpanFull(),
                                Repeater::make('children')
                                    ->label('Підрозділи (2-й рівень)')
                                    ->reorderable()
                                    ->addActionLabel('Додати підрозділ')
                                    ->defaultItems(0)
                                    ->schema([
                                        TextInput::make('title_uk')
                                            ->label('Назва підрозділу')
                                            ->maxLength(255),
                                        self::makeFooterRichEditor('body_uk')
                                            ->label('Вступний текст (опційно)')
                                            ->columnSpanFull(),
                                        Repeater::make('children')
                                            ->label('Сторінки (3-й рівень)')
                                            ->reorderable()
                                            ->addActionLabel('Додати сторінку')
                                            ->defaultItems(0)
                                            ->schema([
                                                TextInput::make('title_uk')
                                                    ->label('Назва сторінки')
                                                    ->maxLength(255),
                                                self::makeFooterRichEditor('body_uk')
                                                    ->label('Текст')
                                                    ->columnSpanFull(),
                                            ])
                                            ->itemLabel(function (array $state): ?string {
                                                $t = trim((string) ($state['title_uk'] ?? ''));

                                                return $t !== '' ? (mb_strlen($t) > 48 ? mb_substr($t, 0, 45).'…' : $t) : 'Сторінка';
                                            })
                                            ->columnSpanFull(),
                                    ])
                                    ->itemLabel(function (array $state): ?string {
                                        $t = trim((string) ($state['title_uk'] ?? ''));

                                        return $t !== '' ? (mb_strlen($t) > 48 ? mb_substr($t, 0, 45).'…' : $t) : 'Підрозділ';
                                    })
                                    ->columnSpanFull(),
                            ])
                            ->itemLabel(function (array $state): ?string {
                                $t = trim((string) ($state['title_uk'] ?? ''));

                                return $t !== '' ? (mb_strlen($t) > 48 ? mb_substr($t, 0, 45).'…' : $t) : 'Розділ';
                            })
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),
                Section::make('Колонки з посиланнями')
                    ->description('Опційно: додаткові колонки посилань праворуч від бренду. На вітрині показуються лише колонки, у яких є хоча б одне посилання з URL. Заголовок без посилань зберегти не можна — додайте пункти («Додати посилання») або видаліть колонку.')
                    ->schema([
                        Repeater::make('columns')
                            ->collapsible()
                            ->collapsed()
                            ->hiddenLabel()
                            ->reorderable()
                            ->addActionLabel('Додати колонку')
                            ->defaultItems(0)
                            ->schema([
                                TextInput::make('title_uk')
                                    ->label('Заголовок (UK)')
                                    ->maxLength(255),
                                TextInput::make('title_en')
                                    ->label('Заголовок (EN)')
                                    ->maxLength(255),
                                TextInput::make('title_ru')
                                    ->label('Заголовок (RU)')
                                    ->maxLength(255),
                                Repeater::make('links')
                                    ->label('Посилання')
                                    ->reorderable()
                                    ->addActionLabel('Додати посилання')
                                    ->defaultItems(0)
                                    ->schema([
                                        TextInput::make('label_uk')
                                            ->label('Текст (UK)')
                                            ->maxLength(255),
                                        TextInput::make('label_en')
                                            ->label('Текст (EN)')
                                            ->maxLength(255),
                                        TextInput::make('label_ru')
                                            ->label('Текст (RU)')
                                            ->maxLength(255),
                                        TextInput::make('url')
                                            ->label('URL')
                                            ->maxLength(2048)
                                            ->placeholder('/catalog або /info/moya-storinka або https://…')
                                            ->helperText('Обовʼязково для показу. Порожній URL ігнорується; колонка без жодного URL на сайт не потрапить.'),
                                        Checkbox::make('open_new_tab')
                                            ->label('Відкривати в новій вкладці'),
                                    ])
                                    ->columns(1)
                                    ->columnSpanFull(),
                            ])
                            ->itemLabel(function (array $state): ?string {
                                $uk = trim((string) ($state['title_uk'] ?? ''));

                                return $uk !== '' ? $uk : 'Колонка';
                            })
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),
                Section::make('Люди також запитували')
                    ->description('Компактний FAQ-блок у футері. На вітрині питання показуються як список-акордеон, а відповіді відкриваються по кліку.')
                    ->schema([
                        TextInput::make('brand.faq_title_uk')
                            ->label('Заголовок FAQ (UK)')
                            ->maxLength(255)
                            ->placeholder('Люди також запитували'),
                        TextInput::make('brand.faq_title_en')
                            ->label('Заголовок FAQ (EN)')
                            ->maxLength(255)
                            ->placeholder('People also ask'),
                        TextInput::make('brand.faq_title_ru')
                            ->label('Заголовок FAQ (RU)')
                            ->maxLength(255)
                            ->placeholder('Люди также спрашивали'),
                        Repeater::make('faq_items')
                            ->collapsible()
                            ->collapsed()
                            ->label('Питання та відповіді')
                            ->reorderable()
                            ->addActionLabel('Додати питання')
                            ->defaultItems(0)
                            ->schema([
                                TextInput::make('question_uk')
                                    ->label('Питання (UK)')
                                    ->maxLength(512),
                                TextInput::make('question_en')
                                    ->label('Питання (EN)')
                                    ->maxLength(512),
                                TextInput::make('question_ru')
                                    ->label('Питання (RU)')
                                    ->maxLength(512),
                                self::makeFooterRichEditor('answer_uk')
                                    ->label('Відповідь (UK)')
                                    ->columnSpanFull(),
                                self::makeFooterRichEditor('answer_en')
                                    ->label('Відповідь (EN)')
                                    ->columnSpanFull(),
                                self::makeFooterRichEditor('answer_ru')
                                    ->label('Відповідь (RU)')
                                    ->columnSpanFull(),
                            ])
                            ->itemLabel(function (array $state): ?string {
                                $uk = trim((string) ($state['question_uk'] ?? ''));
                                if ($uk === '') {
                                    $uk = trim((string) ($state['question_en'] ?? ''));
                                }
                                if ($uk === '') {
                                    $uk = trim((string) ($state['question_ru'] ?? ''));
                                }

                                return $uk !== '' ? (mb_strlen($uk) > 56 ? mb_substr($uk, 0, 53).'…' : $uk) : 'Питання';
                            })
                            ->columns(1)
                            ->columnSpanFull(),
                    ])
                    ->columns(3)
                    ->columnSpanFull(),
            ]);
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
        return static::$title ?? 'Футер';
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
