<?php

namespace App\Filament\Admin\Pages;

use App\Models\ShopSeoSetting;
use BackedEnum;
use Filament\Actions\Action;
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
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Schema as DbSchema;
use Throwable;
use UnitEnum;

/**
 * Текст, який Google показує для головної сторінки: заголовок і опис під посиланням.
 *
 * @property-read Schema $form
 */
class ManageHomeSeo extends Page
{
    use CanUseDatabaseTransactions;

    protected static ?string $slug = 'home-seo';

    protected static ?string $title = 'Google: головна сторінка';

    protected static ?string $navigationLabel = 'Google: головна';

    protected static string|UnitEnum|null $navigationGroup = 'Налаштування';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMagnifyingGlass;

    protected static ?int $navigationSort = 94;

    /** Рекомендована довжина, щоб Google показав текст без обрізання. */
    private const TITLE_RECOMMENDED = 60;

    private const DESCRIPTION_RECOMMENDED = 160;

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return DbSchema::hasTable('shop_seo_settings');
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
        $row = ShopSeoSetting::query()->orderBy('id')->first();

        return [
            'home_title' => $row?->home_title,
            'home_description' => $row?->home_description,
        ];
    }

    public function save(): void
    {
        try {
            $this->beginDatabaseTransaction();
            $data = $this->form->getState();

            $row = ShopSeoSetting::query()->orderBy('id')->first() ?? new ShopSeoSetting;
            $row->fill([
                'home_title' => trim((string) ($data['home_title'] ?? '')) ?: null,
                'home_description' => trim(preg_replace('/\s+/u', ' ', (string) ($data['home_description'] ?? ''))) ?: null,
            ])->save();
        } catch (Throwable $exception) {
            $this->rollBackDatabaseTransaction();
            throw $exception;
        }

        $this->commitDatabaseTransaction();

        Notification::make()
            ->success()
            ->title('Збережено')
            ->body('Google оновить текст у пошуку, коли наступного разу відвідає сайт (зазвичай за кілька днів).')
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
                Section::make('Як сайт виглядає в пошуку Google')
                    ->schema([
                        TextInput::make('home_title')
                            ->label('Заголовок')
                            ->maxLength(ShopSeoSetting::TITLE_MAX)
                            ->placeholder(__('shop.home_page_title', [], 'uk'))
                            ->helperText('До '.self::TITLE_RECOMMENDED.' символів — довший Google обріже трикрапкою.'),
                        Textarea::make('home_description')
                            ->label('Опис')
                            ->rows(3)
                            ->maxLength(ShopSeoSetting::DESCRIPTION_MAX)
                            ->placeholder(__('shop.home_meta_description', [], 'uk'))
                            ->helperText('Найкраще 120–'.self::DESCRIPTION_RECOMMENDED.' символів: що продаєте і чому у вас. Google іноді підбирає свій фрагмент тексту зі сторінки.'),
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
        return static::$title ?? 'Google: головна сторінка';
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
