<?php

namespace App\Filament\Admin\Schemas;

use App\Filament\Support\PromotionTargetLabels;
use App\Models\OptionGroup;
use App\Models\OptionValue;
use App\Support\CatalogCategoryTree;
use Closure;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Support\Facades\Schema as DbSchema;

final class CatalogCategoryCascadeFields
{
    /**
     * Каскад категорій для товару / комплекту (`category_level_*_id`).
     *
     * @return list<Select>
     */
    public static function listingCategoryLevelFields(): array
    {
        if (! DbSchema::hasColumn('option_values', 'parent_id')) {
            return [];
        }

        $categoryGroupId = OptionGroup::systemCategoryGroupIdForCatalog();
        if ($categoryGroupId <= 0) {
            return [];
        }

        return self::buildCascadeSelects(
            'category_level_%d_id',
            fn (): int => $categoryGroupId,
            requireFirstLevel: true,
            appliesCategoryModeCheck: null,
        );
    }

    /**
     * Каскад для області застосування групи опцій (`scope_category_level_*_id`).
     *
     * @return list<Select>
     */
    public static function scopeCategoryLevelFields(): array
    {
        if (! DbSchema::hasColumn('option_values', 'parent_id')) {
            return [];
        }

        $categoryGroupId = OptionGroup::systemCategoryGroupId();
        if ($categoryGroupId <= 0) {
            return [];
        }

        return self::buildCascadeSelects(
            'scope_category_level_%d_id',
            fn (): int => $categoryGroupId,
            requireFirstLevel: false,
            appliesCategoryModeCheck: fn (Get $get): bool => (string) $get('applies_mode') === 'category',
        );
    }

    /**
     * Фільтр категорій у промо-лінії (`catalog_category_level_*_filter_id`).
     *
     * @return list<Select>
     */
    public static function promotionCategoryFilterFields(): array
    {
        if (! DbSchema::hasColumn('option_values', 'parent_id')) {
            return [];
        }

        return self::buildCascadeSelects(
            'catalog_category_level_%d_filter_id',
            fn (): int => PromotionTargetLabels::catalogCategoryOptionGroupId(),
            requireFirstLevel: true,
            appliesCategoryModeCheck: null,
            visibleWhen: fn (Get $get): bool => ($get('line_target') ?? 'product') === 'product',
            alsoClearStatePaths: ['catalog_product_id'],
        );
    }

    /**
     * @return list<Select>
     */
    private static function buildCascadeSelects(
        string $namePattern,
        Closure $resolveGroupId,
        bool $requireFirstLevel,
        ?Closure $appliesCategoryModeCheck,
        ?Closure $visibleWhen = null,
        array $alsoClearStatePaths = [],
    ): array {
        $out = [];
        for ($level = 1; $level <= CatalogCategoryTree::MAX_DEPTH; $level++) {
            $fieldName = sprintf($namePattern, $level);

            $label = match ($level) {
                1 => 'Категорія',
                2 => 'Підкатегорія',
                3 => 'Під-підкатегорія',
                default => 'Рівень '.$level,
            };

            $out[] = Select::make($fieldName)
                ->label($label)
                ->searchable()
                ->preload()
                ->native(false)
                ->visible(function (Get $get) use ($visibleWhen, $appliesCategoryModeCheck, $resolveGroupId, $level, $namePattern): bool {
                    if ($visibleWhen !== null && ! $visibleWhen($get)) {
                        return false;
                    }
                    if ($appliesCategoryModeCheck !== null && ! $appliesCategoryModeCheck($get)) {
                        return false;
                    }

                    $gid = $resolveGroupId();
                    if ($gid <= 0) {
                        return false;
                    }

                    if ($level === 1) {
                        return true;
                    }

                    $parentId = (int) ($get(sprintf($namePattern, $level - 1)) ?? 0);
                    if ($parentId <= 0) {
                        return false;
                    }

                    return self::optionValueHasChildren($gid, $parentId);
                })
                ->options(function (Get $get) use ($level, $namePattern, $resolveGroupId): array {
                    $gid = $resolveGroupId();
                    if ($gid <= 0) {
                        return [];
                    }

                    $parentId = null;
                    if ($level === 1) {
                        $parentId = null;
                    } else {
                        $p = (int) ($get(sprintf($namePattern, $level - 1)) ?? 0);
                        if ($p <= 0) {
                            return [];
                        }
                        $parentId = $p;
                    }

                    return self::optionValueChildrenOptions($gid, $parentId);
                })
                ->live()
                ->afterStateUpdated(function (Set $set) use ($level, $namePattern, $alsoClearStatePaths): void {
                    for ($k = $level + 1; $k <= CatalogCategoryTree::MAX_DEPTH; $k++) {
                        $set(sprintf($namePattern, $k), null);
                    }
                    foreach ($alsoClearStatePaths as $path) {
                        $set($path, null);
                    }
                })
                ->disabled(function (Get $get) use ($level, $namePattern): bool {
                    if ($level === 1) {
                        return false;
                    }

                    return blank($get(sprintf($namePattern, $level - 1)));
                })
                ->required(function (Get $get) use ($level, $requireFirstLevel, $appliesCategoryModeCheck, $visibleWhen): bool {
                    if ($level !== 1) {
                        return false;
                    }
                    if ($visibleWhen !== null && ! $visibleWhen($get)) {
                        return false;
                    }
                    if ($appliesCategoryModeCheck !== null) {
                        return $appliesCategoryModeCheck($get);
                    }

                    return $requireFirstLevel;
                })
                ->helperText($level === 1
                    ? 'Обов’язково. Нижчі рівні — за потреби, якщо є вкладені категорії.'
                    : 'Необов’язково, якщо достатньо батьківської категорії.');
        }

        return $out;
    }

    private static function optionValueHasChildren(int $categoryGroupId, int $parentId): bool
    {
        return OptionValue::query()
            ->where('option_group_id', $categoryGroupId)
            ->where('parent_id', $parentId)
            ->exists();
    }

    /**
     * @return array<int|string, string>
     */
    private static function optionValueChildrenOptions(int $categoryGroupId, ?int $parentId): array
    {
        $q = OptionValue::query()
            ->where('option_group_id', $categoryGroupId)
            ->orderBy('sort_order')
            ->orderBy('name');

        if ($parentId === null) {
            $q->whereNull('parent_id');
        } else {
            $q->where('parent_id', $parentId);
        }

        return $q->get(['id', 'name', 'is_active'])
            ->mapWithKeys(fn (OptionValue $value): array => [
                $value->id => $value->is_active ? $value->name : $value->name.' (неактивна)',
            ])
            ->all();
    }
}
