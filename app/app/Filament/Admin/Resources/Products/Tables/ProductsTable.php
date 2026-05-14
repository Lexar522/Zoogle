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
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class ProductsTable
{
    /**
     * Перший збережений шлях до файлу: спочатку галерея товару, потім фото варіантів (як на вітрині в картках).
     */
    public static function firstStoredPhotoPath(?Product $record): ?string
    {
        if (! $record) {
            return null;
        }

        $lists = [];
        if (is_array($record->photos ?? null)) {
            $lists[] = $record->photos;
        }
        foreach ($record->variants ?? [] as $variant) {
            if (is_array($variant->photos ?? null)) {
                $lists[] = $variant->photos;
            }
        }

        foreach ($lists as $photos) {
            foreach ($photos as $item) {
                if (is_string($item) && $item !== '') {
                    return ltrim($item, '/');
                }
            }
        }

        return null;
    }

    /**
     * @return list<int>
     */
    private static function categorySubtreeIds(int $valueId, int $categoryGroupId): array
    {
        if ($valueId <= 0 || $categoryGroupId <= 0) {
            return [];
        }

        $out = [];
        $queue = [$valueId];
        while ($queue !== []) {
            $id = (int) array_shift($queue);
            if (in_array($id, $out, true)) {
                continue;
            }
            $out[] = $id;

            $childIds = OptionValue::query()
                ->where('option_group_id', $categoryGroupId)
                ->where('parent_id', $id)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();

            foreach ($childIds as $childId) {
                $queue[] = $childId;
            }
        }

        return $out;
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
            ->searchUsing(function (Builder $query, string $search): void {
                $search = Str::trim((string) $search);
                if ($search === '') {
                    return;
                }

                $tableName = $query->getModel()->getTable();
                if (! Schema::hasColumn($tableName, 'title')) {
                    return;
                }

                $escaped = str_replace(
                    ['\\', '%', '_'],
                    ['\\\\', '\\%', '\\_'],
                    $search,
                );
                $term = "{$escaped}%";
                $patternLower = mb_strtolower($term, 'UTF-8');

                $model = $query->getModel();
                $qualified = $model->qualifyColumn('title');
                $usePlainLike = in_array($query->getConnection()->getDriverName(), ['mysql', 'mariadb'], true);

                if ($usePlainLike) {
                    $query->where($qualified, 'like', $term);
                } else {
                    $query->whereRaw('LOWER('.$qualified.') LIKE ?', [$patternLower]);
                }
            })
            ->modifyQueryUsing(fn ($query) => $query->with('variants'))
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
                    ->searchable(),
                TextColumn::make('category_name')
                    ->label('Категорія')
                    ->state(function ($record): string {
                        static $categoryGroupId = null;
                        static $valueNameCache = [];

                        if ($categoryGroupId === null) {
                            $categoryGroupId = OptionGroup::systemCategoryGroupIdForCatalog();
                        }

                        if (! $categoryGroupId) {
                            return '—';
                        }

                        $categoryValueId = null;
                        foreach (($record->variant_options ?? []) as $row) {
                            if ((int) ($row['option_group_id'] ?? 0) !== (int) $categoryGroupId) {
                                continue;
                            }

                            $categoryValueId = (int) collect($row['option_value_ids'] ?? [])
                                ->map(fn ($id) => (int) $id)
                                ->first();
                            break;
                        }

                        if (! $categoryValueId) {
                            return '—';
                        }

                        if (array_key_exists($categoryValueId, $valueNameCache)) {
                            return $valueNameCache[$categoryValueId];
                        }

                        $valueNameCache[$categoryValueId] = (string) (OptionValue::query()
                            ->whereKey($categoryValueId)
                            ->value('name') ?? '—');

                        return $valueNameCache[$categoryValueId];
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
                ToggleColumn::make('is_available')
                    ->label('В наявності'),
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
