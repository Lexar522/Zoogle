<?php

namespace App\Filament\Admin\Resources\Products\Tables;

use App\Models\OptionGroup;
use App\Models\OptionValue;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Support\PublicStorageUrl;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class ProductsTable
{
    /**
     * Перший збережений шлях до файлу: спочатку галерея товару (БД або диск), потім фото варіантів.
     */
    public static function firstStoredPhotoPath(?Product $record): ?string
    {
        return $record?->firstListedPhotoPath();
    }

    /**
     * @return list<int>
     */
    private static function categorySubtreeIds(int $valueId, int $categoryGroupId): array
    {
        if ($valueId <= 0 || $categoryGroupId <= 0) {
            return [];
        }

        $childrenByParent = [];
        $rows = OptionValue::query()
            ->where('option_group_id', $categoryGroupId)
            ->get(['id', 'parent_id']);
        foreach ($rows as $row) {
            $childrenByParent[(int) ($row->parent_id ?? 0)][] = (int) $row->id;
        }

        $out = [];
        $queue = [$valueId];
        while ($queue !== []) {
            $id = (int) array_shift($queue);
            if (in_array($id, $out, true)) {
                continue;
            }
            $out[] = $id;

            foreach ($childrenByParent[$id] ?? [] as $childId) {
                $queue[] = $childId;
            }
        }

        return $out;
    }

    /**
     * @return array<int, string>
     */
    private static function categoryNameMap(): array
    {
        /** @var array<int, string> $map */
        $map = once(function (): array {
            $groupId = OptionGroup::systemCategoryGroupIdForCatalog();
            if ($groupId <= 0) {
                return [];
            }

            $names = [];
            $rows = OptionValue::query()
                ->where('option_group_id', $groupId)
                ->get(['id', 'name']);
            foreach ($rows as $row) {
                $names[(int) $row->id] = (string) $row->name;
            }

            return $names;
        });

        return $map;
    }

    private static function constrainListQuery(Builder $query): void
    {
        $model = $query->getModel();
        $table = $model->getTable();
        $wanted = [
            $model->getKeyName() ?: 'id',
            'title',
            'sku',
            'price',
            'is_available',
            'stock_status',
            'photos',
            'variant_options',
            'category_value_id',
            'category_parent_value_id',
            'published_at',
            'created_at',
            'updated_at',
        ];
        $selected = [];
        foreach ($wanted as $column) {
            if ($column === ($model->getKeyName() ?: 'id') || Schema::hasColumn($table, $column)) {
                $selected[] = $table.'.'.$column;
            }
        }
        $query->select(array_values(array_unique($selected)));

        $query->with([
            'variants' => function ($variantQuery): void {
                $variant = $variantQuery->getModel();
                $fk = $variant->product()->getForeignKeyName();
                $columns = [$variant->getKeyName() ?: 'id', $fk];
                foreach (['price', 'is_visible', 'is_sold', 'photos'] as $column) {
                    if (Schema::hasColumn($variant->getTable(), $column)) {
                        $columns[] = $column;
                    }
                }
                $variantQuery->select(array_values(array_unique($columns)));
            },
            'categories' => function ($categoryQuery): void {
                $categoryQuery->select(['option_values.id', 'option_values.name']);
            },
        ]);
    }

    /**
     * @return array<string, string> id => label
     */
    public static function categoryOptionsForTableFilter(): array
    {
        $groupId = OptionGroup::systemCategoryGroupIdForCatalog();
        if ($groupId <= 0) {
            return [];
        }

        $rows = OptionValue::query()
            ->where('option_group_id', $groupId)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get(['id', 'name', 'parent_id']);

        /** @var Collection<int, Collection<int, OptionValue>> $byParent */
        $byParent = $rows->groupBy(fn (OptionValue $r): int => (int) ($r->parent_id ?? 0));

        $out = [];
        $walk = function (int $parent, int $depth) use (&$out, &$walk, $byParent): void {
            foreach ($byParent->get($parent, collect()) as $r) {
                $prefix = $depth > 0 ? str_repeat('· ', $depth) : '';
                $out[(string) $r->id] = $prefix.$r->name;
                $walk((int) $r->id, $depth + 1);
            }
        };
        $walk(0, 0);

        return $out;
    }

    private static function storefrontListPriceExpression(Builder $query): string
    {
        $model = $query->getModel();
        $pTable = $model->getTable();
        $pKey = (string) $model->getKeyName();
        if ($pKey === '') {
            $pKey = 'id';
        }

        $variant = new ProductVariant;
        $vTable = $variant->getTable();
        $vFk = Schema::hasColumn($vTable, 'product_id') ? 'product_id' : 'animal_listing_id';
        if (! Schema::hasColumn($vTable, 'is_visible')) {
            $minPriceSub = "(
                SELECT MIN({$vTable}.price) FROM {$vTable}
                WHERE {$vTable}.{$vFk} = {$pTable}.{$pKey}
            )";
        } else {
            $minPriceSub = "(
                SELECT MIN({$vTable}.price) FROM {$vTable}
                WHERE {$vTable}.{$vFk} = {$pTable}.{$pKey}
                AND {$vTable}.is_visible = 1
            )";
        }

        if (! Schema::hasColumn($pTable, 'price')) {
            return "COALESCE({$minPriceSub}, 0)";
        }

        return "COALESCE({$minPriceSub}, {$pTable}.price, 0)";
    }

    public static function configure(Table $table): Table
    {
        return $table
            ->extraAttributes([
                'class' => 'fi-ta--products-list',
            ], merge: true)
            ->searchable()
            ->searchPlaceholder('Назва або артикул')
            ->searchUsing(function (Builder $query, string $search): void {
                $search = Str::trim((string) $search);
                if ($search === '') {
                    return;
                }

                $tableName = $query->getModel()->getTable();
                $hasTitle = Schema::hasColumn($tableName, 'title');
                $hasSku = Schema::hasColumn($tableName, 'sku');
                if (! $hasTitle && ! $hasSku) {
                    return;
                }

                $escaped = str_replace(
                    ['\\', '%', '_'],
                    ['\\\\', '\\%', '\\_'],
                    $search,
                );
                // Назва й артикул — входження в будь-якому місці (не лише з початку).
                $titleTerm = "%{$escaped}%";
                $skuTerm = "%{$escaped}%";
                $titlePatternLower = mb_strtolower($titleTerm, 'UTF-8');
                $skuPatternLower = mb_strtolower($skuTerm, 'UTF-8');

                $model = $query->getModel();
                $usePlainLike = in_array($query->getConnection()->getDriverName(), ['mysql', 'mariadb'], true);

                $query->where(function (Builder $q) use (
                    $model,
                    $hasTitle,
                    $hasSku,
                    $usePlainLike,
                    $titleTerm,
                    $skuTerm,
                    $titlePatternLower,
                    $skuPatternLower,
                    $search,
                ): void {
                    if ($hasTitle) {
                        $titleCol = $model->qualifyColumn('title');
                        if ($usePlainLike) {
                            $q->where($titleCol, 'like', $titleTerm);
                        } else {
                            $q->whereRaw('LOWER('.$titleCol.') LIKE ?', [$titlePatternLower]);
                        }
                    }

                    if ($hasSku) {
                        $skuCol = $model->qualifyColumn('sku');
                        $method = $hasTitle ? 'orWhere' : 'where';
                        if ($usePlainLike) {
                            $q->{$method}(function (Builder $skuQuery) use ($skuCol, $skuTerm, $search): void {
                                $skuQuery->where($skuCol, 'like', $skuTerm)
                                    ->orWhere($skuCol, $search);
                            });
                        } else {
                            $q->{$method}(function (Builder $skuQuery) use ($skuCol, $skuPatternLower, $search): void {
                                $skuQuery->whereRaw('LOWER('.$skuCol.') LIKE ?', [$skuPatternLower])
                                    ->orWhere($skuCol, $search);
                            });
                        }
                    }
                });
            })
            ->modifyQueryUsing(fn ($query) => self::constrainListQuery($query))
            ->columns([
                ImageColumn::make('photo_preview')
                    ->label('Фото')
                    ->checkFileExistence(false)
                    ->imageSize(40)
                    ->square()
                    ->state(fn (Product $record): ?string => PublicStorageUrl::forPath(self::firstStoredPhotoPath($record)))
                    ->sortable(false),
                TextColumn::make('title')
                    ->label('Назва')
                    ->searchable(false),
                TextColumn::make('category_name')
                    ->label('Категорія')
                    ->state(function ($record): string {
                        $categoryGroupId = once(fn (): int => OptionGroup::systemCategoryGroupIdForCatalog());

                        if (! $categoryGroupId) {
                            return '—';
                        }

                        $categoryIds = [];
                        foreach (($record->variant_options ?? []) as $row) {
                            if ((int) ($row['option_group_id'] ?? 0) !== (int) $categoryGroupId) {
                                continue;
                            }

                            foreach ($row['option_value_ids'] ?? [] as $id) {
                                $n = (int) $id;
                                if ($n > 0) {
                                    $categoryIds[$n] = true;
                                }
                            }
                        }

                        foreach ($record->categories ?? [] as $category) {
                            $n = (int) $category->id;
                            if ($n > 0) {
                                $categoryIds[$n] = true;
                            }
                        }

                        $ids = array_keys($categoryIds);
                        if ($ids === []) {
                            $fallback = (int) ($record->category_value_id ?? 0);
                            if ($fallback > 0) {
                                $ids = [$fallback];
                            }
                        }
                        if ($ids === []) {
                            return '—';
                        }

                        $nameMap = self::categoryNameMap();
                        $names = [];
                        foreach ($ids as $categoryValueId) {
                            $name = $nameMap[(int) $categoryValueId] ?? '';
                            if ($name !== '' && $name !== '—') {
                                $names[] = $name;
                            }
                        }

                        if ($names === []) {
                            return '—';
                        }

                        $first = $names[0];
                        $extra = count($names) - 1;

                        return $extra > 0 ? $first.' +'.$extra : $first;
                    })
                    ->badge(),
                TextColumn::make('variant_price')
                    ->label('Ціна (від)')
                    ->state(function (Product $record): ?float {
                        $variants = $record->variants ?? collect();

                        $visible = $variants->filter(fn (ProductVariant $v): bool => $v->isVisibleOnStorefront());
                        $minFrom = static function ($coll): ?float {
                            if ($coll->isEmpty()) {
                                return null;
                            }

                            return (float) $coll->min(fn (ProductVariant $v): float => (float) $v->price);
                        };

                        $min = $minFrom($visible);
                        if ($min !== null) {
                            return $min;
                        }

                        if ($record->price !== null && $record->price !== '') {
                            return (float) $record->price;
                        }

                        return $minFrom($variants);
                    })
                    ->money('UAH', locale: 'uk')
                    ->sortable(
                        true,
                        function (Builder $query, string $direction): void {
                            $dir = strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC';
                            $expr = self::storefrontListPriceExpression($query);
                            $query->orderByRaw("({$expr}) {$dir}");
                        }
                    )
                    ->placeholder('—'),
                TextColumn::make('stock_status')
                    ->label('Наявність')
                    ->state(fn (Product $record): string => Product::stockStatusOptions()[$record->stockStatus()] ?? 'В наявності')
                    ->badge()
                    ->color(fn (Product $record): string => match ($record->stockStatus()) {
                        Product::STOCK_PREORDER => 'warning',
                        Product::STOCK_OUT_OF_STOCK => 'danger',
                        default => 'success',
                    }),
                ToggleColumn::make('is_available')
                    ->label('На вітрині'),
                TextColumn::make('published_at')
                    ->label('Опубліковано')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Створено')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label('Оновлено')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters(
                [
                    SelectFilter::make('catalog_category_id')
                        ->label('Категорія')
                        ->options(fn (): array => self::categoryOptionsForTableFilter())
                        ->searchable()
                        ->preload()
                        ->placeholder('Усі категорії')
                        ->native(false)
                        ->query(function (Builder $query, array $data): void {
                            if (Schema::hasColumn($query->getModel()->getTable(), 'category_value_id') === false) {
                                return;
                            }

                            $id = (int) ($data['value'] ?? 0);
                            if ($id <= 0) {
                                return;
                            }

                            $groupId = OptionGroup::systemCategoryGroupIdForCatalog();
                            if ($groupId <= 0) {
                                return;
                            }

                            $ids = self::categorySubtreeIds($id, $groupId);
                            if ($ids === []) {
                                return;
                            }

                            $query->where(function (Builder $q) use ($ids): void {
                                $q->whereIn('category_value_id', $ids)
                                    ->orWhereIn('category_parent_value_id', $ids);
                            });
                        }),
                    SelectFilter::make('stock_status')
                        ->label('Наявність')
                        ->options(fn (): array => Product::stockStatusOptions())
                        ->placeholder('Усі статуси')
                        ->native(false)
                        ->query(function (Builder $query, array $data): void {
                            $value = (string) ($data['value'] ?? '');
                            if ($value === '') {
                                return;
                            }
                            if ($value === Product::STOCK_IN_STOCK) {
                                $query->where(function (Builder $q): void {
                                    $q->where('stock_status', Product::STOCK_IN_STOCK)
                                        ->orWhereNull('stock_status');
                                });

                                return;
                            }
                            $query->where('stock_status', $value);
                        }),
                    TernaryFilter::make('is_available')
                        ->label('На вітрині')
                        ->placeholder('Усі')
                        ->trueLabel('На вітрині')
                        ->falseLabel('Знято з вітрини')
                        ->native(false),
                ],
                layout: FiltersLayout::Hidden,
            )
            ->filtersFormColumns(1)
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
