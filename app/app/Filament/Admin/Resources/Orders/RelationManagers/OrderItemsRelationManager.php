<?php

namespace App\Filament\Admin\Resources\Orders\RelationManagers;

use App\Filament\Admin\Resources\Bundles\BundleResource;
use App\Filament\Admin\Resources\Products\ProductResource;
use App\Filament\Support\ProductAdminSelectSearch;
use App\Models\Bundle;
use App\Models\Order;
use App\Models\OrderItem;
use App\Services\AdminOrderItemEditor;
use App\Services\InventoryService;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class OrderItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    protected static ?string $title = 'Позиції';

    protected static ?string $modelLabel = 'Позиція';

    protected static ?string $pluralModelLabel = 'Позиції';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        $editor = app(AdminOrderItemEditor::class);

        return $schema
            ->columns(1)
            ->components([
                Select::make('product_id')
                    ->label('Товар')
                    ->required(fn (?OrderItem $record): bool => $record === null || (int) ($record->bundle_id ?? 0) <= 0)
                    ->searchable()
                    ->searchPrompt('Назва або артикул')
                    ->allowHtml()
                    ->native(false)
                    ->live()
                    ->disabled(fn (?OrderItem $record): bool => $record !== null && (int) ($record->bundle_id ?? 0) > 0)
                    ->dehydrated(fn (?OrderItem $record): bool => $record === null || (int) ($record->bundle_id ?? 0) <= 0)
                    ->getSearchResultsUsing(fn (string $search): array => ProductAdminSelectSearch::searchWithPhoto($search))
                    ->getOptionLabelUsing(fn ($value): ?string => ProductAdminSelectSearch::labelHtmlForId((int) $value))
                    ->afterStateUpdated(function (Set $set, Get $get) use ($editor): void {
                        $set('option_picks', []);
                        $set('option_value_ids', []);
                        $set('price', $editor->suggestedUnitPrice((int) ($get('product_id') ?? 0), []));
                    })
                    ->helperText('Пошук за назвою або артикулом — можна додати товар, якого не було в замовленні.'),
                Group::make()
                    ->visible(function (Get $get, ?OrderItem $record) use ($editor): bool {
                        if ($record !== null && (int) ($record->bundle_id ?? 0) > 0) {
                            return false;
                        }

                        return $editor->optionFormComponents((int) ($get('product_id') ?? 0)) !== [];
                    })
                    ->schema(function (Get $get) use ($editor): array {
                        $components = $editor->optionFormComponents((int) ($get('product_id') ?? 0));
                        foreach ($components as $component) {
                            $component->afterStateUpdated(function (Set $set, Get $liveGet) use ($editor): void {
                                $set('price', $editor->suggestedUnitPrice(
                                    (int) ($liveGet('product_id') ?? 0),
                                    $editor->flattenOptionPicks($liveGet('option_picks')),
                                    (int) ($liveGet('qty') ?? 1),
                                ));
                            });
                        }

                        return $components;
                    }),
                TextInput::make('qty')
                    ->label('Кількість')
                    ->numeric()
                    ->required()
                    ->minValue(1)
                    ->maxValue(999)
                    ->default(1),
                TextInput::make('price')
                    ->label('Ціна за шт')
                    ->numeric()
                    ->prefix('₴')
                    ->minValue(0)
                    ->helperText('Підставляється з каталогу. Можна змінити вручну для цього замовлення.'),
            ]);
    }

    public function table(Table $table): Table
    {
        $editor = app(AdminOrderItemEditor::class);

        return $table
            ->extraAttributes([
                'class' => 'fi-ta-order-items-compact',
            ], merge: true)
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with([
                'product',
                'bundle.items.product.variants',
            ]))
            ->columns([
                ImageColumn::make('catalog_photo')
                    ->label('')
                    ->getStateUsing(fn (OrderItem $record): ?string => $record->adminCatalogPhotoUrl())
                    ->checkFileExistence(false)
                    ->imageWidth(40)
                    ->imageHeight(40)
                    ->square()
                    ->extraHeaderAttributes(['class' => 'fi-ta-col-catalog_photo'])
                    ->extraCellAttributes(['class' => 'fi-ta-col-catalog_photo']),
                TextColumn::make('title_snapshot')
                    ->label('Товар / комплект')
                    ->getStateUsing(fn (OrderItem $record): string => $editor->productTitleForTable($record))
                    ->wrap()
                    ->url(function (OrderItem $record): ?string {
                        if ($record->bundle_id !== null && (int) $record->bundle_id > 0) {
                            return BundleResource::getUrl('edit', ['record' => $record->bundle_id]);
                        }

                        $productId = (int) ($record->product_id ?? 0);
                        if ($productId > 0) {
                            return ProductResource::getUrl('edit', ['record' => $productId]);
                        }

                        return null;
                    })
                    ->openUrlInNewTab(),
                TextColumn::make('admin_options')
                    ->label('Опції')
                    ->wrap()
                    ->getStateUsing(fn (OrderItem $record): string => $record->adminOptionsSummary()),
                TextColumn::make('qty')
                    ->label('К-сть')
                    ->alignEnd()
                    ->extraHeaderAttributes(['class' => 'fi-ta-col-qty'])
                    ->extraCellAttributes(['class' => 'fi-ta-col-qty']),
                TextColumn::make('price')
                    ->label('Ціна')
                    ->money('UAH')
                    ->alignEnd()
                    ->extraHeaderAttributes(['class' => 'fi-ta-col-money'])
                    ->extraCellAttributes(['class' => 'fi-ta-col-money']),
                TextColumn::make('line_total')
                    ->label('Разом')
                    ->money('UAH')
                    ->alignEnd()
                    ->extraHeaderAttributes(['class' => 'fi-ta-col-money'])
                    ->extraCellAttributes(['class' => 'fi-ta-col-money']),
            ])
            ->defaultSort('id')
            ->paginated(false)
            ->headerActions([
                CreateAction::make()
                    ->label('Додати товар')
                    ->modalHeading('Додати товар у замовлення')
                    ->mutateDataUsing(function (array $data): array {
                        return $this->editor()->prepareDataForSave($this->order(), $data);
                    })
                    ->after(function (): mixed {
                        $this->syncInventoryAfterCreate();

                        return $this->afterLineChanged('Товар додано, суму замовлення оновлено.');
                    }),
                CreateAction::make('addBundle')
                    ->label('Додати комплект')
                    ->modalHeading('Додати комплект у замовлення')
                    ->schema([
                        Select::make('bundle_id')
                            ->label('Комплект')
                            ->required()
                            ->searchable()
                            ->native(false)
                            ->getSearchResultsUsing(function (string $search): array {
                                $term = trim($search);
                                if ($term === '') {
                                    return [];
                                }

                                return Bundle::query()
                                    ->where(function ($q) use ($term): void {
                                        $q->where('title', 'like', '%'.$term.'%')
                                            ->orWhere('sku', 'like', '%'.$term.'%');
                                    })
                                    ->orderBy('title')
                                    ->limit(20)
                                    ->get(['id', 'title', 'sku'])
                                    ->mapWithKeys(function (Bundle $bundle): array {
                                        $sku = trim((string) ($bundle->sku ?? ''));

                                        return [
                                            $bundle->id => $sku !== ''
                                                ? $bundle->title.' · '.$sku
                                                : $bundle->title,
                                        ];
                                    })
                                    ->all();
                            })
                            ->getOptionLabelUsing(function ($value): ?string {
                                $bundle = Bundle::query()->find((int) $value);

                                return $bundle?->title;
                            }),
                        TextInput::make('qty')
                            ->label('Кількість')
                            ->numeric()
                            ->required()
                            ->minValue(1)
                            ->maxValue(999)
                            ->default(1),
                        TextInput::make('price')
                            ->label('Ціна за шт')
                            ->numeric()
                            ->prefix('₴')
                            ->minValue(0)
                            ->helperText('Підставляється з каталогу. Можна змінити вручну.'),
                    ])
                    ->mutateDataUsing(function (array $data): array {
                        return $this->editor()->prepareBundleCreate($data);
                    })
                    ->after(function (): mixed {
                        $this->syncInventoryAfterCreate();

                        return $this->afterLineChanged('Комплект додано, суму замовлення оновлено.');
                    }),
            ])
            ->recordActions([
                EditAction::make()
                    ->label('Змінити')
                    ->iconButton()
                    ->icon('heroicon-o-pencil-square')
                    ->modalHeading('Редагувати позицію')
                    ->mutateRecordDataUsing(function (array $data): array {
                        $record = $this->getMountedTableActionRecord();
                        if ($record instanceof OrderItem) {
                            $ids = array_values(array_filter(array_map(
                                'intval',
                                is_array($record->option_value_ids) ? $record->option_value_ids : []
                            )));
                            $data['option_value_ids'] = $ids;
                            $data['option_picks'] = $this->editor()->optionPicksFromValueIds(
                                (int) ($record->product_id ?? 0),
                                $ids
                            );
                        }

                        return $data;
                    })
                    ->mutateDataUsing(function (array $data): array {
                        $record = $this->getMountedTableActionRecord();
                        if ($record instanceof OrderItem) {
                            $this->inventoryEditBefore = $this->cloneOrderItemForInventory($record);
                        }

                        return $this->editor()->prepareDataForSave(
                            $this->order(),
                            $data,
                            $record instanceof OrderItem ? $record : null,
                        );
                    })
                    ->after(function (): mixed {
                        $record = $this->getMountedTableActionRecord();
                        if ($record instanceof OrderItem) {
                            app(InventoryService::class)->syncCommittedOrderItem(
                                $this->order(),
                                $this->inventoryEditBefore,
                                $record->fresh(['product.variants', 'bundle.items.product.variants'])
                            );
                        }
                        $this->inventoryEditBefore = null;

                        return $this->afterLineChanged('Позицію оновлено, суму замовлення перераховано.');
                    }),
                DeleteAction::make()
                    ->label('Видалити')
                    ->iconButton()
                    ->before(function (): void {
                        $record = $this->getMountedTableActionRecord();
                        if ($record instanceof OrderItem) {
                            app(InventoryService::class)->syncCommittedOrderItem($this->order(), $record, null);
                        }
                    })
                    ->after(fn (): mixed => $this->afterLineChanged('Позицію видалено, суму замовлення перераховано.')),
            ]);
    }

    private ?OrderItem $inventoryEditBefore = null;

    private function editor(): AdminOrderItemEditor
    {
        return app(AdminOrderItemEditor::class);
    }

    private function order(): Order
    {
        $order = $this->getOwnerRecord();
        if (! $order instanceof Order) {
            throw new \RuntimeException('OrderItemsRelationManager очікує замовлення.');
        }

        return $order;
    }

    private function afterLineChanged(string $message): void
    {
        $order = $this->order();
        $recount = $this->editor()->recountTotals($order);
        $fresh = $order->fresh();

        Notification::make()
            ->title($message)
            ->body('Сума: '.number_format((float) $fresh->total, 2, ',', ' ').' ₴')
            ->success()
            ->send();

        if ($recount['payment_status_changed']) {
            Notification::make()
                ->title('Статус оплати змінено на «частково сплачено»')
                ->body('Нова сума більша за попередню оплачену. Перевірте доплату.')
                ->warning()
                ->send();
        }

        $this->dispatch('order-items-changed');
    }

    private function syncInventoryAfterCreate(): void
    {
        $record = $this->getMountedTableActionRecord();
        if ($record instanceof OrderItem) {
            app(InventoryService::class)->syncCommittedOrderItem($this->order(), null, $record);
        }
    }

    private function cloneOrderItemForInventory(OrderItem $record): OrderItem
    {
        $clone = $record->replicate();
        $clone->forceFill([
            'product_id' => $record->product_id,
            'variant_id' => $record->variant_id,
            'bundle_id' => $record->bundle_id,
            'option_value_ids' => $record->option_value_ids,
            'bundle_snapshot' => $record->bundle_snapshot,
            'qty' => $record->qty,
        ]);
        if ($record->relationLoaded('product')) {
            $clone->setRelation('product', $record->product);
        }
        if ($record->relationLoaded('bundle')) {
            $clone->setRelation('bundle', $record->bundle);
        }

        return $clone;
    }
}
