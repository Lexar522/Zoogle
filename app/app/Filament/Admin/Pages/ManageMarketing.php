<?php

namespace App\Filament\Admin\Pages;

use App\Models\ShopMarketingSetting;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
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
 * Лічильники (Google Analytics, Meta Pixel), підтвердження сайту для Google і Facebook
 * та посилання на фід товарів для Google Merchant Center і каталогу Facebook/Instagram.
 *
 * @property-read Schema $form
 */
class ManageMarketing extends Page
{
    use CanUseDatabaseTransactions;

    protected static ?string $slug = 'marketing';

    protected static ?string $title = 'Маркетинг і соцмережі';

    protected static ?string $navigationLabel = 'Маркетинг і соцмережі';

    protected static string|UnitEnum|null $navigationGroup = 'Налаштування';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMegaphone;

    protected static ?int $navigationSort = 95;

    /** @var list<string> */
    private const FIELDS = ['ga4_id', 'meta_pixel_id', 'google_site_verification', 'facebook_domain_verification'];

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return DbSchema::hasTable('shop_marketing_settings');
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
        $row = ShopMarketingSetting::query()->orderBy('id')->first();

        return collect(self::FIELDS)->mapWithKeys(fn (string $field): array => [$field => $row?->{$field}])->all();
    }

    public function save(): void
    {
        try {
            $this->beginDatabaseTransaction();
            $data = $this->form->getState();

            $row = ShopMarketingSetting::query()->orderBy('id')->first() ?? new ShopMarketingSetting;
            $row->fill(collect(self::FIELDS)->mapWithKeys(fn (string $field): array => [
                $field => trim((string) ($data[$field] ?? '')) ?: null,
            ])->all())->save();
        } catch (Throwable $exception) {
            $this->rollBackDatabaseTransaction();
            throw $exception;
        }

        $this->commitDatabaseTransaction();

        Notification::make()->success()->title('Збережено')->send();

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
                Section::make('Фід товарів')
                    ->description('Одне посилання для Google Merchant Center і для каталогу Facebook / Instagram (Commerce Manager → Каталог → Джерела даних → Заплановане завантаження). Оновлюється автоматично.')
                    ->schema([
                        TextEntry::make('feed_url')
                            ->hiddenLabel()
                            ->state(route('seo.merchant-feed'))
                            ->copyable()
                            ->copyMessage('Скопійовано')
                            ->fontFamily('mono'),
                    ]),
                Section::make('Лічильники')
                    ->description('Вмикаються на сайті лише після згоди відвідувача з cookie. Порожнє поле — лічильник вимкнено.')
                    ->columns(['default' => 1, 'md' => 2])
                    ->schema([
                        TextInput::make('ga4_id')
                            ->label('Google Analytics 4 — ідентифікатор потоку')
                            ->placeholder('G-XXXXXXXXXX')
                            ->maxLength(32)
                            ->regex(ShopMarketingSetting::GA4_PATTERN)
                            ->validationMessages(['regex' => 'Має виглядати як G-ABC123XYZ.'])
                            ->dehydrateStateUsing(fn (?string $state): ?string => $state === null ? null : strtoupper(trim($state)))
                            ->helperText('Google Analytics → Адміністратор → Потоки даних → ваш сайт.'),
                        TextInput::make('meta_pixel_id')
                            ->label('Meta Pixel (Facebook / Instagram) — ID')
                            ->placeholder('123456789012345')
                            ->maxLength(32)
                            ->regex(ShopMarketingSetting::PIXEL_PATTERN)
                            ->validationMessages(['regex' => 'Лише цифри, як у Events Manager.'])
                            ->helperText('Meta Events Manager → Джерела даних → ваш піксель. Рахує перегляди товарів, кошик, оформлення й покупки — для реклами в Instagram і Facebook.'),
                    ]),
                Section::make('Підтвердження сайту')
                    ->description('Вставте лише код із тегу (те, що в content="…"), без самого тегу.')
                    ->columns(['default' => 1, 'md' => 2])
                    ->schema([
                        TextInput::make('google_site_verification')
                            ->label('Google (Search Console / Merchant Center)')
                            ->maxLength(120)
                            ->regex(ShopMarketingSetting::VERIFICATION_PATTERN)
                            ->validationMessages(['regex' => 'Лише латиниця, цифри, «-» і «_».'])
                            ->helperText('Потрібно, лише якщо Google просить підтвердити сайт HTML-тегом.'),
                        TextInput::make('facebook_domain_verification')
                            ->label('Facebook / Instagram — підтвердження домену')
                            ->maxLength(120)
                            ->regex(ShopMarketingSetting::VERIFICATION_PATTERN)
                            ->validationMessages(['regex' => 'Лише латиниця, цифри, «-» і «_».'])
                            ->helperText('Meta Business Suite → Налаштування компанії → Безпека бренду → Домени → «Мета-тег».'),
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
        return static::$title ?? 'Маркетинг і соцмережі';
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
