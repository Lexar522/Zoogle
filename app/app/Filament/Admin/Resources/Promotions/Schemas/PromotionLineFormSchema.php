<?php

namespace App\Filament\Admin\Resources\Promotions\Schemas;

use App\Enums\PromotionDiscountMode;
use App\Filament\Admin\Schemas\CatalogCategoryCascadeFields;
use App\Filament\Support\PromotionTargetLabels;
use App\Models\Bundle;
use App\Models\Product;
use App\Support\CatalogCategoryTree;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Support\Facades\Schema as DbSchema;

final class PromotionLineFormSchema
{
    /**
     * @return list<Component>
     */
    public static function components(): array
    {
        return [
            Select::make('line_target')
                ->label('Тип цілі')
                ->options([
                    'product' => 'Товар у каталозі',
                    'bundle' => 'Комплект',
                ])
                ->default('product')
                ->required()
                ->live()
                ->native(false),
            Select::make('bundle_id')
                ->label('Комплект')
                ->visible(fn (Get $get): bool => ($get('line_target') ?? 'product') === 'bundle')
                ->required(fn (Get $get): bool => ($get('line_target') ?? 'product') === 'bundle')
                ->searchable()
                ->preload()
                ->native(false)
                ->options(fn (): array => Bundle::query()
                    ->orderBy('title')
                    ->limit(100)
                    ->pluck('title', 'id')
                    ->all())
                ->getSearchResultsUsing(fn (string $search): array => Bundle::query()
                    ->where('title', 'like', '%'.$search.'%')
                    ->orderBy('title')
                    ->limit(50)
                    ->pluck('title', 'id')
                    ->all())
                ->getOptionLabelUsing(fn ($value): ?string => $value ? Bundle::query()->whereKey($value)->value('title') : null),
            ...CatalogCategoryCascadeFields::promotionCategoryFilterFields(),
            Select::make('catalog_product_id')
                ->label('Товар')
                ->visible(fn (Get $get): bool => ($get('line_target') ?? 'product') === 'product')
                ->required(fn (Get $get): bool => ($get('line_target') ?? 'product') === 'product')
                ->searchable()
                ->preload()
                ->native(false)
                ->live()
                ->options(function (Get $get): array {
                    $filterLeaf = self::deepestPromotionCategoryFilterId($get);
                    if ($filterLeaf <= 0) {
                        return [];
                    }

                    return PromotionTargetLabels::searchProducts('', $filterLeaf);
                })
                ->getSearchResultsUsing(function (string $search, Get $get): array {
                    $filterLeaf = self::deepestPromotionCategoryFilterId($get);

                    return PromotionTargetLabels::searchProducts(
                        $search,
                        $filterLeaf > 0 ? $filterLeaf : null,
                    );
                })
                ->helperText(function (): ?string {
                    if (! DbSchema::hasColumn('option_values', 'parent_id')) {
                        return null;
                    }

                    return 'Оберіть категорію (до '.CatalogCategoryTree::MAX_DEPTH.' рівнів) — товари з’являться в списку; можна ввести назву або id у пошук.';
                })
                ->getOptionLabelUsing(function ($value): ?string {
                    if (blank($value)) {
                        return null;
                    }

                    return Product::query()->whereKey($value)->value('title');
                }),
            TextInput::make('sale_price')
                ->label('Нова ціна під акцію (₴)')
                ->numeric()
                ->minValue(0)
                ->visible(fn (Get $get): bool => ! (bool) $get('use_advanced_discount'))
                ->required(fn (Get $get): bool => ! (bool) $get('use_advanced_discount'))
                ->helperText(fn (Get $get): string => ($get('line_target') ?? 'product') === 'bundle'
                    ? 'Підсумкова ціна всього комплекту після акції (база — сума позицій за звичайними цінами).'
                    : 'Ціна, яку платитиме покупець на вітрині під час акції.'),
            DateTimePicker::make('line_ends_at')
                ->label('Кінець для цієї позиції')
                ->seconds(false)
                ->helperText('Порожньо — діє до загальної дати завершення кампанії (поле «Кінець» у самій акції).'),
            Toggle::make('use_advanced_discount')
                ->label('Інший тип знижки (відсоток або сума)')
                ->default(false)
                ->live()
                ->helperText('За замовчуванням достатньо «Нової ціни». Увімкніть, якщо потрібен відсоток або фіксована знижка в ₴.'),
            Select::make('discount_mode')
                ->label('Тип знижки')
                ->native(false)
                ->options(collect(PromotionDiscountMode::cases())->mapWithKeys(
                    fn (PromotionDiscountMode $m): array => [$m->value => $m->label()]
                )->all())
                ->visible(fn (Get $get): bool => (bool) $get('use_advanced_discount'))
                ->required(fn (Get $get): bool => (bool) $get('use_advanced_discount')),
            TextInput::make('discount_value')
                ->label('Значення')
                ->numeric()
                ->minValue(0)
                ->visible(fn (Get $get): bool => (bool) $get('use_advanced_discount'))
                ->required(fn (Get $get): bool => (bool) $get('use_advanced_discount'))
                ->helperText('Відсоток 0–100 або сума знижки в ₴ (не «нова ціна»).'),
        ];
    }

    private static function deepestPromotionCategoryFilterId(Get $get): int
    {
        for ($i = CatalogCategoryTree::MAX_DEPTH; $i >= 1; $i--) {
            $v = (int) ($get('catalog_category_level_'.$i.'_filter_id') ?? 0);
            if ($v > 0) {
                return $v;
            }
        }

        return 0;
    }
}
