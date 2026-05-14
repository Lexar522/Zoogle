<?php

namespace App\Filament\Admin\Pages;

use App\Models\Product;
use App\Models\ShopHomeListItem;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
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

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?int $navigationSort = 15;

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill($this->loadFormData());
    }

    private static function productSelectLabel(Product $product): string
    {
        $title = trim((string) $product->title);

        return $title !== '' ? $title : '#'.$product->id;
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

        return [
            'bestsellers' => $mapRows(ShopHomeListItem::LIST_BESTSELLERS),
            'recommended' => $mapRows(ShopHomeListItem::LIST_RECOMMENDED),
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

            $this->callHook('afterSave');
        } catch (Throwable $exception) {
            $this->rollBackDatabaseTransaction();
            throw $exception;
        }

        $this->commitDatabaseTransaction();

        Notification::make()
            ->success()
            ->title('Збережено')
            ->body('Списки товарів на головній оновлено.')
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
                ->options(function (): array {
                    return Product::query()
                        ->orderBy('title')
                        ->limit(2000)
                        ->get()
                        ->mapWithKeys(fn (Product $p): array => [(string) $p->id => self::productSelectLabel($p)])
                        ->all();
                })
                ->getOptionLabelUsing(function ($value): ?string {
                    if ($value === null || $value === '') {
                        return null;
                    }

                    $product = Product::query()->find($value);

                    return $product ? self::productSelectLabel($product) : null;
                })
                ->native(false);
        };

        return $schema
            ->components([
                Section::make('Хіти продажів')
                    ->description('Порядок у списку = порядок карток на сайті. Перетягуйте рядки.')
                    ->schema([
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

                                $p = Product::query()->find($state['product_id']);

                                return $p ? self::productSelectLabel($p) : 'Товар';
                            }),
                    ])
                    ->columnSpanFull(),
                Section::make('Рекомендовані товари')
                    ->description('Порядок у списку = порядок карток на сайті.')
                    ->schema([
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

                                $p = Product::query()->find($state['product_id']);

                                return $p ? self::productSelectLabel($p) : 'Товар';
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
