<?php

namespace App\Filament\Admin\Pages;

use App\Models\ShopCatalogGridSetting;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\ToggleButtons;
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
class ManageCatalogGrid extends Page
{
    use CanUseDatabaseTransactions;

    protected static ?string $slug = 'catalog-grid';

    protected static ?string $title = 'Сітка каталогу';

    protected static ?string $navigationLabel = 'Сітка каталогу';

    protected static string|UnitEnum|null $navigationGroup = 'Каталог';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquares2x2;

    protected static ?int $navigationSort = 14;

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return DbSchema::hasTable('shop_catalog_grid_settings');
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);
        $this->form->fill($this->loadFormData());
    }

    /**
     * @return array<string, int|string>
     */
    private function loadFormData(): array
    {
        ShopCatalogGridSetting::forgetResolvedCache();

        return ShopCatalogGridSetting::resolved();
    }

    public function save(): void
    {
        try {
            $this->beginDatabaseTransaction();
            $this->callHook('beforeValidate');
            $data = $this->form->getState();
            $this->callHook('afterValidate');

            $row = ShopCatalogGridSetting::query()->orderBy('id')->first() ?? new ShopCatalogGridSetting;
            $row->fill(ShopCatalogGridSetting::clamp($data))->save();

            $this->callHook('afterSave');
        } catch (Throwable $exception) {
            $this->rollBackDatabaseTransaction();
            throw $exception;
        }

        $this->commitDatabaseTransaction();

        Notification::make()
            ->success()
            ->title('Збережено')
            ->body('Сітку каталогу оновлено.')
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
                Section::make('Колонки')
                    ->description('Скільки карток товарів стоїть в одному ряду каталогу. Між планшетом і великим екраном — на одну менше, ніж на комп\'ютері.')
                    ->columns(3)
                    ->schema([
                        $this->columnsField('columns_desktop', 'Комп\'ютер', 3, 5),
                        $this->columnsField('columns_tablet', 'Планшет', 2, 4),
                        $this->columnsField('columns_mobile', 'Телефон', 1, 2),
                    ]),
                Section::make('Картка товару')
                    ->description('Діє на картки в каталозі та на головній.')
                    ->schema([
                        Select::make('subtitle_mode')
                            ->label('Рядок під назвою')
                            ->options(ShopCatalogGridSetting::SUBTITLE_MODES)
                            ->required()
                            ->native(false)
                            ->helperText('Рядок однакової висоти в усіх картках, тож ціна й кошик стоять на одній лінії.'),
                        ToggleButtons::make('title_lines')
                            ->label('Рядків у назві')
                            ->options([1 => '1', 2 => '2', 3 => '3'])
                            ->inline()
                            ->required()
                            ->helperText('Довша назва обрізається трикрапкою.'),
                        ToggleButtons::make('photo_ratio')
                            ->label('Форма фото в каталозі')
                            ->options([
                                '1/1' => 'Квадрат',
                                '4/5' => '4:5, вище',
                                '4/3' => '4:3, ширше',
                            ])
                            ->inline()
                            ->required(),
                    ]),
            ]);
    }

    private function columnsField(string $name, string $label, int $min, int $max): ToggleButtons
    {
        $options = [];
        foreach (range($min, $max) as $n) {
            $options[$n] = (string) $n;
        }

        return ToggleButtons::make($name)
            ->label($label)
            ->options($options)
            ->inline()
            ->required();
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
        return static::$title ?? 'Сітка каталогу';
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
