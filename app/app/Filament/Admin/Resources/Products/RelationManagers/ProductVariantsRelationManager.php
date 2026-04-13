<?php

namespace App\Filament\Admin\Resources\Products\RelationManagers;

use App\Filament\Admin\Concerns\MapsVariantStockStatusForm;
use App\Filament\Admin\Concerns\NormalizesVariantOptionsRepeaterForm;
use App\Filament\Admin\Concerns\VariantOptionFormFromListing;
use App\Models\OptionGroup;
use App\Models\OptionValue;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Support\VariantOptionsAllowlist;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Validation\ValidationException;

class ProductVariantsRelationManager extends RelationManager
{
    use MapsVariantStockStatusForm;
    use NormalizesVariantOptionsRepeaterForm;
    use VariantOptionFormFromListing;

    protected static string $relationship = 'variants';

    protected static ?string $label = 'Варіанти';

    protected static ?string $title = 'Варіанти товару';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('price')
                ->label('Ціна')
                ->required()
                ->numeric()
                ->minValue(0),

            TextInput::make('compare_at_price')
                ->label('Стара ціна (перекреслена)')
                ->numeric()
                ->minValue(0)
                ->helperText('Опційно, якщо більше за поточну ціну — показується на вітрині як знижка.'),

            TextInput::make('quantity')
                ->label('К-сть')
                ->required()
                ->numeric()
                ->minValue(1)
                ->default(1),

            Select::make('stock_status')
                ->label('Статус наявності')
                ->options($this->stockStatusSelectOptions())
                ->default('in_stock')
                ->required()
                ->helperText('Як показувати цей варіант у каталозі та на сторінці товару.')
                ->native(false),

            FileUpload::make('photos')
                ->label('Фото варіанта')
                ->image()
                ->multiple()
                ->reorderable()
                ->appendFiles()
                ->disk('public')
                ->directory(fn (RelationManager $livewire): string => 'product-variants/'.$livewire->getOwnerRecord()->id.'/photos')
                ->maxFiles(20),

            Repeater::make('options')
                ->label('Опції (група -> значення)')
                ->helperText('Спочатку задайте групи в «Опції товару» на картці. Тип «колір» vs «текст» задається в довіднику «Групи опцій», не тут — у варіанті лише обираєте групу та значення.')
                ->required()
                ->minItems(1)
                ->schema([
                    Select::make('option_group_id')
                        ->label('Група опції')
                        ->required()
                        // Інакше Filament вважає options «динамічними» і при відкритті знову викликає
                        // getOptionsForJs через Livewire — у модалці/repeater часто приходить [].
                        ->dynamicOptions(false)
                        ->options(fn (): array => $this->getGroupOptions())
                        ->getOptionLabelUsing(function ($value): ?string {
                            if (blank($value)) {
                                return null;
                            }

                            return OptionGroup::query()
                                ->whereKey((int) $value)
                                ->value('name');
                        })
                        ->live()
                        ->afterStateUpdated(function (callable $set): void {
                            $set('option_value_id', null);
                            $set('option_value_ids', []);
                        })
                        ->searchable()
                        ->searchPrompt('Введіть назву групи опцій')
                        ->searchingMessage('Пошук...')
                        ->noSearchResultsMessage('Нічого не знайдено')
                        ->noOptionsMessage('Немає доступних груп опцій для цього товару.'),

                    Select::make('option_value_id')
                        ->label('Значення')
                        ->visible(fn (Get $get): bool => ! $this->isVariantOptionGroupMultiple((int) ($get('option_group_id') ?? 0)))
                        ->required(fn (Get $get): bool => filled($get('option_group_id'))
                            && ! $this->isVariantOptionGroupMultiple((int) ($get('option_group_id') ?? 0)))
                        ->dynamicOptions(false)
                        ->options(function (Get $get): array {
                            $groupId = $get('option_group_id');

                            if (blank($groupId)) {
                                return [];
                            }

                            return $this->getValueOptionsByGroup((int) $groupId);
                        })
                        ->disabled(fn (Get $get): bool => blank($get('option_group_id')))
                        ->getOptionLabelUsing(function ($value): ?string {
                            if (blank($value)) {
                                return null;
                            }

                            return OptionValue::query()
                                ->whereKey((int) $value)
                                ->value('name');
                        })
                        ->searchable()
                        ->searchPrompt('Введіть назву значення')
                        ->searchingMessage('Пошук...')
                        ->noSearchResultsMessage('Нічого не знайдено')
                        ->noOptionsMessage('Для цієї групи немає доступних значень.'),
                    CheckboxList::make('option_value_ids')
                        ->label('Значення (кілька)')
                        ->helperText('Група з режимом «кілька» — відмітьте всі значення, які характеризують цей варіант.')
                        ->visible(fn (Get $get): bool => $this->isVariantOptionGroupMultiple((int) ($get('option_group_id') ?? 0)))
                        ->required(fn (Get $get): bool => $this->isVariantOptionGroupMultiple((int) ($get('option_group_id') ?? 0)))
                        ->live()
                        ->columns(2)
                        ->default([])
                        ->options(function (Get $get): array {
                            $groupId = $get('option_group_id');
                            if (blank($groupId)) {
                                return [];
                            }

                            return $this->getValueOptionsByGroup((int) $groupId);
                        })
                        ->disabled(fn (Get $get): bool => blank($get('option_group_id'))),
                ]),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('sort_order')
                    ->label('#')
                    ->sortable(),
                TextColumn::make('price')
                    ->label('Ціна')
                    ->money('UAH')
                    ->sortable(),
                TextColumn::make('quantity')
                    ->label('К-сть')
                    ->sortable(),
                TextColumn::make('stock_status')
                    ->label('Статус')
                    ->getStateUsing(function (ProductVariant $record): string {
                        if ($record->allows_preorder) {
                            return 'Передзамовлення';
                        }

                        if ($record->is_low_stock && $record->is_available) {
                            return 'Закінчується';
                        }

                        if ($record->is_available) {
                            return 'В наявності';
                        }

                        return 'Немає в наявності';
                    }),
                TextColumn::make('options')
                    ->label('Опції')
                    ->formatStateUsing(function (?array $state): string {
                        if (! is_array($state)) {
                            return '';
                        }

                        $parts = [];
                        foreach ($state as $pair) {
                            $groupId = $pair['option_group_id'] ?? null;
                            $valueId = $pair['option_value_id'] ?? null;

                            if (! $groupId || ! $valueId) {
                                continue;
                            }

                            $groupName = OptionGroup::query()->whereKey($groupId)->value('name');
                            $groupSlug = OptionGroup::query()->whereKey($groupId)->value('slug');
                            $valueName = OptionValue::query()->whereKey($valueId)->value('name');

                            if (! $groupName || ! $valueName || $groupSlug === 'category') {
                                continue;
                            }

                            $parts[] = $groupName.': '.$valueName;
                        }

                        return implode(', ', $parts);
                    }),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->headerActions([
                CreateAction::make()
                    ->label('Додати варіант')
                    ->mutateDataUsing(function (array $data): array {
                        return $this->prepareVariantDataForSave($data);
                    })
                    ->after(function (): void {
                        $this->syncListingVariantOptionsFromVariants();
                    }),
            ])
            ->recordActions([
                EditAction::make()
                    ->label('Редагувати')
                    ->mutateRecordDataUsing(function (array $data): array {
                        $data = $this->hydrateVariantOptionsForForm($data);
                        $data['stock_status'] = $this->resolveStockStatusFromRecordData($data);

                        return $data;
                    })
                    ->mutateDataUsing(function (array $data): array {
                        return $this->prepareVariantDataForSave($data, $this->getMountedTableActionRecord());
                    })
                    ->after(function (): void {
                        $this->syncListingVariantOptionsFromVariants();
                    }),
                DeleteAction::make()->label('Видалити'),
            ]);
    }

    protected function productTypeScopeKeysForListing(): array
    {
        return OptionGroup::productTypeScopeKeysForProduct($this->getOwnerRecord());
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function prepareVariantDataForSave(array $data, ?ProductVariant $ignoreRecord = null): array
    {
        $rawOptions = $this->normalizeVariantOptionsRepeaterForSave($data['options'] ?? []);
        $data = $this->mapStockStatusToVariantData($data);
        if (! array_key_exists('is_visible', $data)) {
            $data['is_visible'] = true;
        }
        if (! array_key_exists('is_sold', $data)) {
            $data['is_sold'] = false;
        }
        $data = $this->dehydrateVariantOptionsForSave($data);

        if ($rawOptions !== [] && ($data['options'] ?? []) === []) {
            throw ValidationException::withMessages([
                'options' => 'Обрані опції варіанта не збігаються з «Опціями товару». Спочатку приведіть значення у варіанті до тих груп і значень, які дозволені в картці товару.',
            ]);
        }

        $owner = $this->getOwnerRecord();
        if ($owner instanceof Product && $this->variantSignatureExistsOnAnotherRecord($owner, $data['options'], $ignoreRecord)) {
            throw ValidationException::withMessages([
                'options' => 'Вже існує інший варіант з такою ж комбінацією опцій для вітрини. Змініть значення опцій, щоб уникнути дублювання.',
            ]);
        }

        return $data;
    }

    /**
     * @param  list<array<string, mixed>>  $flatOptions
     */
    private function variantSignatureExistsOnAnotherRecord(Product $listing, array $flatOptions, ?ProductVariant $ignoreRecord = null): bool
    {
        $normalized = [];
        foreach ($flatOptions as $pair) {
            $gid = (int) ($pair['option_group_id'] ?? 0);
            $vid = (int) ($pair['option_value_id'] ?? 0);
            if ($gid <= 0 || $vid <= 0) {
                continue;
            }
            $normalized[$gid][] = $vid;
        }

        if ($normalized === []) {
            return false;
        }

        $modes = OptionGroup::query()
            ->whereIn('id', array_keys($normalized))
            ->pluck('selection_mode', 'id')
            ->all();

        $parts = [];
        foreach ($normalized as $gid => $vids) {
            $vids = array_values(array_unique(array_map('intval', $vids)));
            sort($vids);
            if (($modes[$gid] ?? 'single') === 'multiple') {
                $parts[] = $gid.':'.implode(',', $vids);
            } else {
                $parts[] = $gid.':'.(int) ($vids[0] ?? 0);
            }
        }
        sort($parts);
        $candidateSignature = implode('|', $parts);

        foreach ($listing->variants as $variant) {
            if (! $variant instanceof ProductVariant) {
                continue;
            }
            if ($ignoreRecord && (int) $variant->id === (int) $ignoreRecord->id) {
                continue;
            }

            $flat = is_array($variant->options) ? $variant->options : [];
            $flat = VariantOptionsAllowlist::filterPairs($flat, $listing, OptionGroup::systemCategoryGroupIdForCatalog());
            $existing = [];
            foreach ($flat as $pair) {
                $gid = (int) ($pair['option_group_id'] ?? 0);
                $vid = (int) ($pair['option_value_id'] ?? 0);
                if ($gid <= 0 || $vid <= 0) {
                    continue;
                }
                $existing[$gid][] = $vid;
            }
            if ($existing === []) {
                continue;
            }

            $existingParts = [];
            foreach ($existing as $gid => $vids) {
                $vids = array_values(array_unique(array_map('intval', $vids)));
                sort($vids);
                if (($modes[$gid] ?? OptionGroup::query()->whereKey($gid)->value('selection_mode') ?? 'single') === 'multiple') {
                    $existingParts[] = $gid.':'.implode(',', $vids);
                } else {
                    $existingParts[] = $gid.':'.(int) ($vids[0] ?? 0);
                }
            }
            sort($existingParts);
            if (implode('|', $existingParts) === $candidateSignature) {
                return true;
            }
        }

        return false;
    }

    private function syncListingVariantOptionsFromVariants(): void
    {
        $listing = $this->getOwnerRecord();
        if (! $listing instanceof Product) {
            return;
        }

        $categoryGroupId = OptionGroup::systemCategoryGroupIdForCatalog();
        $existingRows = is_array($listing->variant_options ?? null) ? $listing->variant_options : [];

        /** @var array<int, list<int>> $merged */
        $merged = [];
        $order = [];

        foreach ($existingRows as $row) {
            $gid = (int) ($row['option_group_id'] ?? 0);
            if ($gid <= 0) {
                continue;
            }
            if (! isset($order[$gid])) {
                $order[$gid] = count($order);
            }
            $ids = collect($row['option_value_ids'] ?? [])
                ->map(fn ($id) => (int) $id)
                ->filter(fn (int $id) => $id > 0)
                ->values()
                ->all();
            $merged[$gid] = array_values(array_unique(array_merge($merged[$gid] ?? [], $ids)));
        }

        foreach ($listing->variants()->get(['options']) as $variant) {
            foreach ($variant->options ?? [] as $pair) {
                $gid = (int) ($pair['option_group_id'] ?? 0);
                $vid = (int) ($pair['option_value_id'] ?? 0);
                if ($gid <= 0 || $vid <= 0 || ($categoryGroupId > 0 && $gid === $categoryGroupId)) {
                    continue;
                }
                if (! isset($order[$gid])) {
                    $order[$gid] = count($order);
                }
                $merged[$gid] = array_values(array_unique(array_merge($merged[$gid] ?? [], [$vid])));
            }
        }

        if ($merged === []) {
            return;
        }

        asort($order);
        $rows = [];
        foreach (array_keys($order) as $gid) {
            $ids = $merged[$gid] ?? [];
            sort($ids);
            if ($gid !== $categoryGroupId && $ids === []) {
                continue;
            }
            $rows[] = [
                'option_group_id' => $gid,
                'option_value_ids' => $ids,
            ];
        }

        $listing->update([
            'variant_options' => $rows,
        ]);
    }
}
