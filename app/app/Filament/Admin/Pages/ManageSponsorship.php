<?php

namespace App\Filament\Admin\Pages;

use App\Models\ShopCharitySetting;
use App\Models\ShopSponsorshipSetting;
use App\Support\RichTextSanitizer;
use BackedEnum;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Concerns\CanUseDatabaseTransactions;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Schema as DbSchema;
use Throwable;
use UnitEnum;

/**
 * @property-read Schema $form
 */
class ManageSponsorship extends Page
{
    use CanUseDatabaseTransactions;

    protected static ?string $slug = 'sponsorship-settings';

    protected static ?string $title = 'Налаштування опіки';

    protected static ?string $navigationLabel = 'Налаштування опіки';

    protected static string|UnitEnum|null $navigationGroup = 'Віртуальна опіка';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static ?int $navigationSort = 3;

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return DbSchema::hasTable('shop_sponsorship_settings');
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
        $row = ShopSponsorshipSetting::query()->orderBy('id')->first();
        $stored = is_array($row?->texts) ? $row->texts : [];

        $texts = [];
        foreach (ShopSponsorshipSetting::TEXTS as $key => $meta) {
            $texts[$key] = trim((string) ($stored[$key] ?? '')) ?: $meta['default'];
        }

        return [
            'is_enabled' => (bool) $row?->is_enabled,
            'jar_url' => $row?->jar_url,
            'intro' => RichTextSanitizer::coerceForFilamentRichEditor($row?->intro),
            'thanks_text' => RichTextSanitizer::coerceForFilamentRichEditor($row?->thanks_text) ?? ShopSponsorshipSetting::DEFAULT_THANKS_TEXT,
            'texts' => $texts,
        ];
    }

    public function save(): void
    {
        try {
            $this->beginDatabaseTransaction();
            $data = $this->form->getState();

            $texts = [];
            foreach (array_keys(ShopSponsorshipSetting::TEXTS) as $key) {
                $value = trim((string) ($data['texts'][$key] ?? ''));
                if ($value !== '') {
                    $texts[$key] = $value;
                }
            }

            $row = ShopSponsorshipSetting::query()->orderBy('id')->first() ?? new ShopSponsorshipSetting;
            $row->fill([
                'is_enabled' => (bool) ($data['is_enabled'] ?? false),
                'jar_url' => trim((string) ($data['jar_url'] ?? '')) ?: null,
                'intro' => RichTextSanitizer::normalizeNullableStoredHtml($data['intro'] ?? null),
                'thanks_text' => RichTextSanitizer::normalizeNullableStoredHtml($data['thanks_text'] ?? null),
                'texts' => $texts,
            ])->save();
        } catch (Throwable $exception) {
            $this->rollBackDatabaseTransaction();
            throw $exception;
        }

        $this->commitDatabaseTransaction();

        Notification::make()
            ->success()
            ->title('Збережено')
            ->body('Налаштування віртуальної опіки оновлено.')
            ->send();

        $this->form->fill($this->loadFormData());
    }

    public function defaultForm(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->statePath('data');
    }

    public function form(Schema $schema): Schema
    {
        $textFields = [];
        foreach (ShopSponsorshipSetting::TEXTS as $key => $meta) {
            $textFields[] = TextInput::make('texts.'.$key)
                ->label($meta['label'])
                ->maxLength(255)
                ->placeholder($meta['default']);
        }

        return $schema
            ->components([
                Section::make('Показ на сайті')
                    ->schema([
                        Toggle::make('is_enabled')
                            ->label('Увімкнути «Віртуальну опіку» на сайті')
                            ->helperText('Показує кнопку в шапці та сторінки /opika. Спершу додайте тварин у розділі «Тварини під опікою».')
                            ->live(),
                        TextInput::make('jar_url')
                            ->label('Банка Monobank для оплати опіки')
                            ->url()
                            ->maxLength(500)
                            ->placeholder('https://send.monobank.ua/jar/...')
                            ->required(fn (Get $get): bool => (bool) $get('is_enabled'))
                            ->rule(fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                                if (filled($value) && ! ShopCharitySetting::isAllowedJarUrl((string) $value)) {
                                    $fail('Потрібне посилання Monobank, що починається з https://send.monobank.ua/');
                                }
                            })
                            ->helperText('Загальна банка. Для окремої тварини можна вказати свою банку в її картці.'),
                    ]),
                Section::make('Тексти')
                    ->schema([
                        RichEditor::make('intro')
                            ->label('Опис на сторінці «Віртуальна опіка»')
                            ->toolbarButtons([
                                ['bold', 'italic', 'underline', 'link'],
                                ['h2', 'h3', 'bulletList', 'orderedList', 'blockquote'],
                                ['undo', 'redo'],
                            ])
                            ->extraAttributes(['style' => 'min-height: 12rem']),
                        RichEditor::make('thanks_text')
                            ->label('Подяка після анкети')
                            ->toolbarButtons([
                                ['bold', 'italic', 'underline', 'link'],
                                ['bulletList', 'orderedList'],
                                ['undo', 'redo'],
                            ])
                            ->helperText('Показується на сторінці оплати: наприклад, що на email надходитимуть фото та відео.'),
                    ]),
                Section::make('Підписи та кнопки')
                    ->description('Порожнє поле — типовий текст (показаний сірим).')
                    ->columns(['default' => 1, 'md' => 2])
                    ->collapsible()
                    ->schema($textFields),
            ]);
    }

    /**
     * @return list<Action>
     */
    protected function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label('Зберегти')
                ->submit('save')
                ->keyBindings(['mod+s']),
        ];
    }

    public function getTitle(): string|Htmlable
    {
        return static::$title ?? 'Налаштування опіки';
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
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
                    ->key('form-actions'),
            ]);
    }
}
