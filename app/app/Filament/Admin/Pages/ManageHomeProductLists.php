<?php

namespace App\Filament\Admin\Pages;

use App\Filament\Support\ProductAdminSelectSearch;
use App\Models\ShopHomeListItem;
use App\Models\ShopHomeProductSection;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
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
use Filament\Support\Facades\FilamentView;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Schema as DbSchema;
use Throwable;
use UnitEnum;

/**
 * @property-read Schema $form
 */
class ManageHomeProductLists extends Page
{
    use CanUseDatabaseTransactions;

    protected static ?string $slug = 'home-product-lists';

    protected static ?string $title = 'Головна: хіти та рекомендовані';

    protected static ?string $navigationLabel = 'Головна: хіти та рекомендовані';

    protected static string|UnitEnum|null $navigationGroup = 'Каталог';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHome;

    protected static ?int $navigationSort = 15;

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
        $mapRows = static function (string $list): array {
            return ShopHomeListItem::query()
                ->forList($list)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get()
                ->map(fn (ShopHomeListItem $row): array => ['product_id' => (string) $row->product_id])
                ->values()
                ->all();
        };

        $copy = ShopHomeProductSection::formDefaults();
        if (DbSchema::hasTable('shop_home_product_sections')) {
            $row = ShopHomeProductSection::query()->orderBy('id')->first();
            if ($row !== null) {
                $copy = [
                    'hits_enabled' => (bool) $row->hits_enabled,
                    'hits_title' => (string) ($row->hits_title ?? ''),
                    'hits_lead' => (string) ($row->hits_lead ?? ''),
                    'recommended_enabled' => (bool) $row->recommended_enabled,
                    'recommended_title' => (string) ($row->recommended_title ?? ''),
                    'recommended_lead' => (string) ($row->recommended_lead ?? ''),
                ];
            }
        }

        return [
            'bestsellers' => $mapRows(ShopHomeListItem::LIST_BESTSELLERS),
            'recommended' => $mapRows(ShopHomeListItem::LIST_RECOMMENDED),
            ...$copy,
        ];
    }

    public function save(): void
    {
        try {
            $this->beginDatabaseTransaction();
            $this->callHook('beforeValidate');
            $data = $this->form->getState();
            $this->callHook('afterValidate');

            $this->persistList(ShopHomeListItem::LIST_BESTSELLERS, $data['bestsellers'] ?? []);
            $this->persistList(ShopHomeListItem::LIST_RECOMMENDED, $data['recommended'] ?? []);
            $this->persistCopy($data);

            $this->callHook('afterSave');
        } catch (Throwable $exception) {
            $this->rollBackDatabaseTransaction();
            throw $exception;
        }

        $this->commitDatabaseTransaction();

        Notification::make()
            ->success()
            ->title('Збережено')
            ->body('Блоки «Хіти продажів» і «Рекомендовані товари» оновлено.')
            ->send();

        $this->form->fill($this->loadFormData());

        if ($redirectUrl = $this->getRedirectUrl()) {
            $this->redirect($redirectUrl, navigate: FilamentView::hasSpaMode($redirectUrl));
        }
    }

    /**
     * @param  list<array{product_id?: string|int|null}>  $rows
     */
    private function persistList(string $list, array $rows): void
    {
        ShopHomeListItem::query()->where('list', $list)->delete();

        $position = 0;
        $seen = [];
        foreach ($rows as $row) {
            $pid = isset($row['product_id']) ? (int) $row['product_id'] : 0;
            if ($pid <= 0 || isset($seen[$pid])) {
                continue;
            }
            $seen[$pid] = true;

            ShopHomeListItem::query()->create([
                'list' => $list,
                'product_id' => $pid,
                'sort_order' => $position,
            ]);
            $position++;
        }
    }

    protected function getRedirectUrl(): ?string
    {
        return null;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function persistCopy(array $data): void
    {
        if (! DbSchema::hasTable('shop_home_product_sections')) {
            return;
        }

        $row = ShopHomeProductSection::query()->orderBy('id')->first() ?? new ShopHomeProductSection;
        $row->fill([
            'hits_enabled' => (bool) ($data['hits_enabled'] ?? true),
            'hits_title' => mb_substr(trim((string) ($data['hits_title'] ?? '')), 0, 255),
            'hits_lead' => mb_substr(trim((string) ($data['hits_lead'] ?? '')), 0, 1000),
            'recommended_enabled' => (bool) ($data['recommended_enabled'] ?? true),
            'recommended_title' => mb_substr(trim((string) ($data['recommended_title'] ?? '')), 0, 255),
            'recommended_lead' => mb_substr(trim((string) ($data['recommended_lead'] ?? '')), 0, 1000),
        ]);
        $row->save();
    }

    public function defaultForm(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->statePath('data');
    }

    public function form(Schema $schema): Schema
    {
        $productSelect = static function (): Select {
            // Повний список у options + searchable() — фільтрація в інтерфейсі Filament (надійніше за лише getSearchResultsUsing у Livewire).
            return Select::make('product_id')
                ->label('Товар')
                ->searchable()
                ->helperText('Пошук лише за назвою: введіть частину назви або оберіть зі списку (до 2000 товарів за алфавітом).')
                ->options(fn (): array => ProductAdminSelectSearch::titleChoices())
                ->getOptionLabelUsing(fn ($value): ?string => ProductAdminSelectSearch::titleForId((int) $value))
                ->native(false);
        };

        return $schema
            ->components([
                Section::make('Хіти продажів')
                    ->description('Заголовок і короткий опис на головній, а нижче — товари. Порядок у списку = порядок карток. Блок можна сховати.')
                    ->schema([
                        Toggle::make('hits_enabled')
                            ->label('Показувати блок на головній')
                            ->default(true),
                        TextInput::make('hits_title')
                            ->label('Заголовок')
                            ->maxLength(255)
                            ->placeholder('Хіти продажів'),
                        Textarea::make('hits_lead')
                            ->label('Короткий опис')
                            ->rows(2)
                            ->maxLength(1000),
                        Repeater::make('bestsellers')
                            ->hiddenLabel()
                            ->reorderable()
                            ->addActionLabel('Додати товар')
                            ->defaultItems(0)
                            ->schema([
                                $productSelect(),
                            ])
                            ->itemLabel(function (array $state): ?string {
                                if (! isset($state['product_id']) || $state['product_id'] === '' || $state['product_id'] === null) {
                                    return 'Новий рядок';
                                }

                                return ProductAdminSelectSearch::titleForId((int) $state['product_id']) ?? 'Товар';
                            }),
                    ])
                    ->columnSpanFull(),
                Section::make('Рекомендовані товари')
                    ->description('Заголовок і короткий опис на головній, а нижче — товари. Порядок у списку = порядок карток. Блок можна сховати.')
                    ->schema([
                        Toggle::make('recommended_enabled')
                            ->label('Показувати блок на головній')
                            ->default(true),
                        TextInput::make('recommended_title')
                            ->label('Заголовок')
                            ->maxLength(255)
                            ->placeholder('Рекомендовані товари'),
                        Textarea::make('recommended_lead')
                            ->label('Короткий опис')
                            ->rows(2)
                            ->maxLength(1000),
                        Repeater::make('recommended')
                            ->hiddenLabel()
                            ->reorderable()
                            ->addActionLabel('Додати товар')
                            ->defaultItems(0)
                            ->schema([
                                $productSelect(),
                            ])
                            ->itemLabel(function (array $state): ?string {
                                if (! isset($state['product_id']) || $state['product_id'] === '' || $state['product_id'] === null) {
                                    return 'Новий рядок';
                                }

                                return ProductAdminSelectSearch::titleForId((int) $state['product_id']) ?? 'Товар';
                            }),
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
        return static::$title ?? 'Головна';
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
