<?php

namespace App\Filament\Admin\Pages;

use App\Models\ShopCharitySetting;
use App\Support\RichTextSanitizer;
use BackedEnum;
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
class ManageCharity extends Page
{
    use CanUseDatabaseTransactions;

    protected static ?string $slug = 'charity';

    protected static ?string $title = 'Благодійність';

    protected static ?string $navigationLabel = 'Благодійність';

    protected static string|UnitEnum|null $navigationGroup = 'Налаштування';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHeart;

    protected static ?int $navigationSort = 93;

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return DbSchema::hasTable('shop_charity_settings');
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
        $row = ShopCharitySetting::query()->orderBy('id')->first();

        return [
            'is_enabled' => (bool) $row?->is_enabled,
            'header_label' => $row?->header_label ?: ShopCharitySetting::DEFAULTS['header_label'],
            'title' => $row?->title ?: ShopCharitySetting::DEFAULTS['title'],
            'body' => RichTextSanitizer::coerceForFilamentRichEditor($row?->body),
            'jar_url' => $row?->jar_url,
            'button_label' => $row?->button_label ?: ShopCharitySetting::DEFAULTS['button_label'],
            'order_prompt' => $row?->order_prompt ?: ShopCharitySetting::DEFAULTS['order_prompt'],
        ];
    }

    public function save(): void
    {
        try {
            $this->beginDatabaseTransaction();
            $data = $this->form->getState();

            $row = ShopCharitySetting::query()->orderBy('id')->first() ?? new ShopCharitySetting;
            $row->fill([
                'is_enabled' => (bool) ($data['is_enabled'] ?? false),
                'header_label' => trim((string) ($data['header_label'] ?? '')) ?: null,
                'title' => trim((string) ($data['title'] ?? '')) ?: null,
                'body' => RichTextSanitizer::normalizeNullableStoredHtml($data['body'] ?? null),
                'jar_url' => trim((string) ($data['jar_url'] ?? '')) ?: null,
                'button_label' => trim((string) ($data['button_label'] ?? '')) ?: null,
                'order_prompt' => trim((string) ($data['order_prompt'] ?? '')) ?: null,
            ])->save();
        } catch (Throwable $exception) {
            $this->rollBackDatabaseTransaction();
            throw $exception;
        }

        $this->commitDatabaseTransaction();

        Notification::make()
            ->success()
            ->title('Збережено')
            ->body('Сторінку благодійності оновлено.')
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
        return $schema
            ->components([
                Section::make('Показ на сайті')
                    ->schema([
                        Toggle::make('is_enabled')
                            ->label('Увімкнути благодійність на сайті')
                            ->helperText('Показує кнопку в шапці, сторінку /charity і запрошення допомогти після оформлення замовлення.')
                            ->live(),
                        TextInput::make('header_label')
                            ->label('Напис на кнопці в шапці')
                            ->maxLength(40)
                            ->placeholder(ShopCharitySetting::DEFAULTS['header_label']),
                    ]),
                Section::make('Сторінка «Благодійність»')
                    ->schema([
                        TextInput::make('title')
                            ->label('Заголовок')
                            ->maxLength(160)
                            ->placeholder(ShopCharitySetting::DEFAULTS['title']),
                        RichEditor::make('body')
                            ->label('Текст')
                            ->toolbarButtons([
                                ['bold', 'italic', 'underline', 'link'],
                                ['h2', 'h3', 'bulletList', 'orderedList', 'blockquote'],
                                ['undo', 'redo'],
                            ])
                            ->extraAttributes(['style' => 'min-height: 14rem'])
                            ->helperText('Розкажіть, кому й на що збираєте кошти.'),
                        TextInput::make('jar_url')
                            ->label('Посилання на банку Monobank')
                            ->url()
                            ->maxLength(500)
                            ->placeholder('https://send.monobank.ua/jar/...')
                            ->required(fn (Get $get): bool => (bool) $get('is_enabled'))
                            ->rule(fn () => function (string $attribute, mixed $value, \Closure $fail): void {
                                if (filled($value) && ! ShopCharitySetting::isAllowedJarUrl((string) $value)) {
                                    $fail('Потрібне посилання Monobank, що починається з https://send.monobank.ua/');
                                }
                            })
                            ->helperText('Monobank → Банка → Поділитися → скопіюйте посилання.'),
                        TextInput::make('button_label')
                            ->label('Напис на кнопці донату')
                            ->maxLength(60)
                            ->placeholder(ShopCharitySetting::DEFAULTS['button_label']),
                    ]),
                Section::make('Після оформлення замовлення')
                    ->description('Блок на сторінці «Дякуємо за покупку» та на сторінці замовлення.')
                    ->schema([
                        TextInput::make('order_prompt')
                            ->label('Текст запрошення')
                            ->maxLength(255)
                            ->placeholder(ShopCharitySetting::DEFAULTS['order_prompt']),
                    ]),
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
        return static::$title ?? 'Благодійність';
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
