<?php

namespace App\Filament\Admin\Pages;

use App\Models\ShopFooterBrand;
use App\Models\ShopFooterColumn;
use App\Models\ShopFooterLink;
use App\Models\ShopInfoPage;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
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

        $informationPages = ShopInfoPage::query()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(static function (ShopInfoPage $p): array {
                return [
                    'title_uk' => $p->title_uk ?? '',
                    'body_uk' => $p->body_uk ?? '',
                ];
            })
            ->values()
            ->all();

        return [
            'brand' => [
                'site_title' => $brand->site_title ?? '',
                'body' => $brand->body ?? '',
                'phone' => $brand->phone ?? '',
            ],
            'information_pages' => $informationPages,
            'columns' => $columns,
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

            $brandData = is_array($data['brand'] ?? null) ? $data['brand'] : [];
            $brand = ShopFooterBrand::record();
            $brand->site_title = self::nullableString($brandData['site_title'] ?? null);
            $brand->body = self::nullableString($brandData['body'] ?? null);
            $brand->phone = self::nullableString($brandData['phone'] ?? null);
            $brand->logo_path = null;
            $brand->save();

            ShopInfoPage::query()->delete();
            $infoRows = is_array($data['information_pages'] ?? null) ? $data['information_pages'] : [];
            $infoSort = 0;
            foreach ($infoRows as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $slug = strtolower(trim((string) ($row['slug'] ?? '')));
                if ($slug === '') {
                    continue;
                }
                ShopInfoPage::query()->create([
                    'sort_order' => $infoSort,
                    'slug' => $slug,
                    'title_uk' => self::nullableString($row['title_uk'] ?? null),
                    'title_en' => null,
                    'title_ru' => null,
                    'body_uk' => self::nullableString($row['body_uk'] ?? null),
                    'body_en' => null,
                    'body_ru' => null,
                ]);
                $infoSort++;
            }

            ShopFooterColumn::query()->delete();

            $columns = is_array($data['columns'] ?? null) ? $data['columns'] : [];
            foreach ($columns as $i => $colData) {
                if (! is_array($colData)) {
                    continue;
                }
                $col = ShopFooterColumn::query()->create([
                    'sort_order' => (int) $i,
                    'title_uk' => self::nullableString($colData['title_uk'] ?? null),
                    'title_en' => self::nullableString($colData['title_en'] ?? null),
                    'title_ru' => self::nullableString($colData['title_ru'] ?? null),
                ]);

                $links = is_array($colData['links'] ?? null) ? $colData['links'] : [];
                foreach ($links as $j => $linkData) {
                    if (! is_array($linkData)) {
                        continue;
                    }
                    $url = trim((string) ($linkData['url'] ?? ''));
                    if ($url === '') {
                        continue;
                    }
                    ShopFooterLink::query()->create([
                        'shop_footer_column_id' => $col->id,
                        'sort_order' => (int) $j,
                        'label_uk' => self::nullableString($linkData['label_uk'] ?? null),
                        'label_en' => self::nullableString($linkData['label_en'] ?? null),
                        'label_ru' => self::nullableString($linkData['label_ru'] ?? null),
                        'url' => $url,
                        'open_new_tab' => ! empty($linkData['open_new_tab']),
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
     * @param  array<string, mixed>  $data
     */
    private function validateInformationPages(array $data): void
    {
        $rows = is_array($data['information_pages'] ?? null) ? $data['information_pages'] : [];
        $seenSlugs = [];
        foreach ($rows as $i => $row) {
            if (! is_array($row)) {
                continue;
            }
            $title = trim((string) ($row['title_uk'] ?? ''));
            $body = trim((string) ($row['body_uk'] ?? ''));
            if ($title === '' && $body === '') {
                continue;
            }
            if ($title === '' && $body !== '') {
                self::haltInfoValidation('Інфо-сторінка #'.($i + 1).': вкажіть назву — вона показується у футері та в заголовку сторінки.');
            }
            $slug = strtolower(trim((string) ($row['slug'] ?? '')));
            if ($slug === '') {
                self::haltInfoValidation('Інфо-сторінка #'.($i + 1).': не вдалося зробити адресу з назви. Додайте до назви літери або цифри.');
            }
            if (strlen($slug) > 128) {
                self::haltInfoValidation('Slug «'.$slug.'» занадто довгий (макс. 128 символів).');
            }
            if (preg_match(self::INFO_SLUG_PATTERN, $slug) !== 1) {
                self::haltInfoValidation('Slug «'.$slug.'»: дозволені лише малі літери a–z, цифри та дефіс між блоками (наприклад pro-nas).');
            }
            if (in_array($slug, self::RESERVED_INFO_SLUGS, true)) {
                self::haltInfoValidation('Slug «'.$slug.'» зарезервовано. Оберіть інший.');
            }
            if (isset($seenSlugs[$slug])) {
                self::haltInfoValidation('Повторюваний slug «'.$slug.'». Кожна інфо-сторінка має бути з унікальним slug.');
            }
            $seenSlugs[$slug] = true;
        }
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
            $slug = strtolower(trim((string) ($row['slug'] ?? '')));
            if ($slug !== '') {
                continue;
            }
            $generated = self::slugFromInfoPageTitles($row);
            if ($generated !== '') {
                $pages[$i]['slug'] = $generated;
            }
        }

        return $pages;
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
                        Textarea::make('brand.body')
                            ->label('Текст під брендом')
                            ->rows(5)
                            ->helperText('Простий текст; переноси рядків зберігаються.')
                            ->columnSpanFull(),
                        TextInput::make('brand.phone')
                            ->label('Телефон для футера')
                            ->maxLength(64)
                            ->tel()
                            ->placeholder('+380 …'),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),
                Section::make('Інформаційні сторінки')
                    ->description('Додайте назву та текст — посилання з’явиться у футері вітрини; сторінка відкривається за адресою /info/… (адресу згенеровано автоматично з назви).')
                    ->schema([
                        Repeater::make('information_pages')
                            ->hiddenLabel()
                            ->reorderable()
                            ->addActionLabel('Додати сторінку')
                            ->defaultItems(0)
                            ->schema([
                                TextInput::make('title_uk')
                                    ->label('Назва')
                                    ->maxLength(255),
                                Textarea::make('body_uk')
                                    ->label('Текст')
                                    ->rows(8)
                                    ->columnSpanFull(),
                            ])
                            ->itemLabel(function (array $state): ?string {
                                $t = trim((string) ($state['title_uk'] ?? ''));

                                return $t !== '' ? (mb_strlen($t) > 48 ? mb_substr($t, 0, 45).'…' : $t) : 'Сторінка';
                            })
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),
                Section::make('Колонки з посиланнями')
                    ->description('Опційно: додаткові колонки посилань праворуч від бренду. Інфо-сторінки з блоку вище вже показуються під логотипом; тут можна вказати, наприклад, «Каталог» або зовнішні посилання.')
                    ->schema([
                        Repeater::make('columns')
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
                                            ->helperText('Порожній URL при збереженні ігнорується.'),
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
