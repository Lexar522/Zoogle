<?php

namespace App\Filament\Admin\Pages;

use App\Models\ShopCatalogPaginationSetting;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
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
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Schema as DbSchema;
use Throwable;
use UnitEnum;

/**
 * @property-read Schema $form
 */
class ManageCatalogPagination extends Page
{
    use CanUseDatabaseTransactions;

    protected static ?string $slug = 'catalog-pagination';

    protected static ?string $title = 'Сторінки каталогу';

    protected static ?string $navigationLabel = 'Сторінки каталогу';

    protected static string|UnitEnum|null $navigationGroup = 'Каталог';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQueueList;

    protected static ?int $navigationSort = 13;

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return DbSchema::hasTable('shop_catalog_pagination_settings');
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);
        $this->form->fill($this->loadFormData());
    }

    /**
     * @return array{visible_pages: int, show_last_arrow: bool, show_all_enabled: bool, per_page: int, show_all_limit: int}
     */
    private function loadFormData(): array
    {
        ShopCatalogPaginationSetting::forgetResolvedCache();
        $settings = ShopCatalogPaginationSetting::resolved();

        return [
            'visible_pages' => $settings['visible_pages'],
            'show_last_arrow' => $settings['show_last_arrow'],
            'show_all_enabled' => $settings['show_all'],
            'per_page' => $settings['per_page'],
            'show_all_limit' => $settings['show_all_limit'],
        ];
    }

    public function save(): void
    {
        try {
            $this->beginDatabaseTransaction();
            $this->callHook('beforeValidate');
            $data = $this->form->getState();
            $this->callHook('afterValidate');

            $row = ShopCatalogPaginationSetting::query()->orderBy('id')->first() ?? new ShopCatalogPaginationSetting;
            $row->visible_pages = ShopCatalogPaginationSetting::clampVisiblePages((int) ($data['visible_pages'] ?? 5));
            $row->show_last_arrow = (bool) ($data['show_last_arrow'] ?? false);
            $row->show_all_enabled = (bool) ($data['show_all_enabled'] ?? false);
            $row->per_page = ShopCatalogPaginationSetting::clampPerPage((int) ($data['per_page'] ?? 24));
            $row->show_all_limit = ShopCatalogPaginationSetting::clampShowAllLimit((int) ($data['show_all_limit'] ?? 1000));
            $row->save();
            ShopCatalogPaginationSetting::forgetResolvedCache();

            $this->callHook('afterSave');
        } catch (Throwable $exception) {
            $this->rollBackDatabaseTransaction();
            throw $exception;
        }

        $this->commitDatabaseTransaction();

        Notification::make()
            ->success()
            ->title('Збережено')
            ->body('Налаштування сторінок каталогу оновлено.')
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
                Section::make('Гортання сторінок')
                    ->description('Номери під списком товарів. Якщо сторінок менше, ніж зазначено, показуються всі наявні.')
                    ->schema([
                        TextInput::make('visible_pages')
                            ->label('Скільки номерів сторінок видно')
                            ->numeric()
                            ->required()
                            ->minValue(5)
                            ->maxValue(15)
                            ->default(ShopCatalogPaginationSetting::DEFAULT_VISIBLE_PAGES)
                            ->helperText('Мінімум 5. Наприклад, 5 — це номери на кшталт 1 2 3 4 5, а не лише поточна і наступна.'),
                        Toggle::make('show_last_arrow')
                            ->label('Стрілка на останню сторінку')
                            ->helperText('Подвійна стрілка » після «далі» одразу відкриває сторінку з останніми товарами.')
                            ->default(true),
                        Select::make('per_page')
                            ->label('Товарів на одній сторінці')
                            ->options(array_combine(
                                ShopCatalogPaginationSetting::PER_PAGE_OPTIONS,
                                array_map(fn (int $n): string => (string) $n, ShopCatalogPaginationSetting::PER_PAGE_OPTIONS),
                            ))
                            ->required()
                            ->native(false)
                            ->default(ShopCatalogPaginationSetting::DEFAULT_PER_PAGE)
                            ->helperText('Стільки карток у одній порції, поки покупець не натисне «Показати всі».'),
                    ]),
                Section::make('Показати всі')
                    ->description('Кнопка стоїть у рядку сортування замість «А–Я» і «Я–А» і виводить товари списком, без перемикання цифр.')
                    ->schema([
                        Toggle::make('show_all_enabled')
                            ->label('Кнопка «Показати всі»')
                            ->default(true),
                        TextInput::make('show_all_limit')
                            ->label('Максимум товарів у режимі «Показати всі»')
                            ->numeric()
                            ->required()
                            ->minValue(24)
                            ->maxValue(5000)
                            ->default(ShopCatalogPaginationSetting::DEFAULT_SHOW_ALL_LIMIT)
                            ->helperText('Якщо товарів більше за цю межу, решта лишається на наступних великих сторінках. Повернення до звичайних номерів — кнопка «По сторінках».'),
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
        return static::$title ?? 'Сторінки каталогу';
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
