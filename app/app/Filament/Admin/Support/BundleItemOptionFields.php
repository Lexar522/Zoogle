<?php

namespace App\Filament\Admin\Support;

use App\Models\OptionGroup;
use App\Models\OptionValue;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\ListingOptionSelectionService;
use App\Support\VariantOptionsAllowlist;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Validation\ValidationException;

final class BundleItemOptionFields
{
    /**
     * @return list<Section>
     */
    public static function formComponents(): array
    {
        return [
            Section::make('Опції товару')
                ->description('Необовʼязково. Можна зафіксувати варіант/опції для цієї позиції комплекту; якщо не обрано — у комплекті використовується базовий товар. Покупець на сайті їх не змінює.')
                ->schema([
                    Select::make('selected_variant_id')
                        ->label('Варіант / опції товару')
                        ->helperText('Опційно: конкретна SKU-комбінація. Можна залишити порожнім.')
                        ->options(fn (Get $get): array => self::variantOptionsForProductId((int) ($get('product_id') ?? 0)))
                        ->visible(fn (Get $get): bool => self::productHasVariantChoices((int) ($get('product_id') ?? 0)))
                        ->searchable()
                        ->nullable()
                        ->placeholder('Без фіксації варіанту'),
                    Select::make('manual_option_value_ids')
                        ->label('Опції товару')
                        ->helperText('Опційно: конкретні значення. Можна нічого не обирати.')
                        ->options(fn (Get $get): array => self::manualOptionChoicesForProductId((int) ($get('product_id') ?? 0)))
                        ->visible(fn (Get $get): bool => self::hasManualOptionChoices((int) ($get('product_id') ?? 0)))
                        ->allowHtml()
                        ->multiple()
                        ->searchable()
                        ->nullable()
                        ->default([]),
                ])
                ->visible(fn (Get $get): bool => self::hasOptionBlocks((int) ($get('product_id') ?? 0)))
                ->columnSpanFull()
                ->columns(2),
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    public static function hydrateRowOptionState(array $row): array
    {
        $productId = (int) ($row['product_id'] ?? 0);
        if ($productId <= 0) {
            return $row;
        }

        $product = self::product($productId);
        if (! $product) {
            return $row;
        }

        foreach (self::optionStateForProduct($product, $row['option_value_ids'] ?? []) as $field => $state) {
            $row[$field] = $state;
        }

        return $row;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return list<int>
     */
    public static function selectedOptionValueIdsForRow(Product $product, array $row): array
    {
        $blocks = self::optionBlocks($product);
        if ($blocks === []) {
            return [];
        }

        if (self::hasVariantChoices($product)) {
            if (array_key_exists('selected_variant_id', $row)) {
                $variant = self::variantForProduct($product, (int) ($row['selected_variant_id'] ?? 0));

                return $variant instanceof ProductVariant
                    ? self::optionValueIdsForVariant($product, $variant)
                    : [];
            }
        }

        if (array_key_exists('manual_option_value_ids', $row)) {
            return app(ListingOptionSelectionService::class)->normalizeOptionValueIds($row['manual_option_value_ids']);
        }

        $ids = [];
        $hasDynamicState = false;

        foreach ($blocks as $block) {
            $field = self::fieldName((int) $product->id, (int) ($block['id'] ?? 0));
            if (! array_key_exists($field, $row)) {
                continue;
            }

            $hasDynamicState = true;
            $state = $row[$field];

            if (($block['selection_mode'] ?? 'single') === 'multiple') {
                if (is_array($state)) {
                    foreach ($state as $id) {
                        $ids[] = (int) $id;
                    }
                }

                continue;
            }

            $id = (int) $state;
            if ($id > 0) {
                $ids[] = $id;
            }
        }

        if (! $hasDynamicState) {
            $ids = is_array($row['option_value_ids'] ?? null) ? $row['option_value_ids'] : [];
        }

        return app(ListingOptionSelectionService::class)->normalizeOptionValueIds($ids);
    }

    /**
     * @param  array<int|string, mixed>  $rawRows
     * @return list<array<string, mixed>>
     *
     * @throws ValidationException
     */
    public static function normalizeAndValidateRows(array $rawRows): array
    {
        $validItems = [];

        foreach ($rawRows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $productId = (int) ($row['product_id'] ?? 0);
            if ($productId <= 0) {
                continue;
            }

            $product = self::product($productId);
            if (! $product) {
                throw ValidationException::withMessages([
                    'items' => 'Один з товарів комплекту більше не існує.',
                ]);
            }

            $optionValueIds = self::validateSelectionForProduct($product, $row);

            $validItems[] = [
                'product_id' => $productId,
                'option_value_ids' => $optionValueIds,
                'qty' => max(1, (int) ($row['qty'] ?? 1)),
                'sort_order' => (int) ($row['sort_order'] ?? 0),
            ];
        }

        if ($validItems === []) {
            throw ValidationException::withMessages([
                'items' => 'Додайте хоча б один товар у комплект (оберіть товар у позиції).',
            ]);
        }

        return $validItems;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return list<int>
     *
     * @throws ValidationException
     */
    public static function validateSelectionForProduct(Product $product, array $row): array
    {
        $selection = self::selectedOptionValueIdsForRow($product, $row);
        $blocks = self::optionBlocks($product);
        if ($blocks === []) {
            return [];
        }

        $service = app(ListingOptionSelectionService::class);
        $safe = $service->sanitizeSelectionForProduct($product, $selection);
        if ($safe !== $selection) {
            throw ValidationException::withMessages([
                'items' => 'Обрані опції для товару «'.$product->title.'» недійсні.',
            ]);
        }

        // Опції для позиції комплекту необовʼязкові: порожній вибір = базовий товар.
        if ($safe === []) {
            return [];
        }

        if (self::hasVariantChoices($product)) {
            $variant = self::variantMatchingSelection($product, $safe);
            if (! $variant instanceof ProductVariant || ! $variant->isSellable()) {
                $explicitVariantId = (int) ($row['selected_variant_id'] ?? 0);
                if ($explicitVariantId > 0) {
                    throw ValidationException::withMessages([
                        'items' => 'Обраний варіант товару «'.$product->title.'» недоступний.',
                    ]);
                }

                // Частковий/несумісний набір опцій без явного варіанту — лишаємо як є
                // (ціна піде з бази + надбавки опцій).
                return $safe;
            }

            return $safe;
        }

        foreach ($blocks as $block) {
            $allowed = collect($block['values'] ?? [])
                ->map(fn (array $value): int => (int) ($value['id'] ?? 0))
                ->filter(fn (int $id): bool => $id > 0)
                ->values()
                ->all();

            $selectedInGroup = array_values(array_intersect($safe, $allowed));
            if ($selectedInGroup === []) {
                continue;
            }

            if (($block['selection_mode'] ?? 'single') !== 'multiple' && count($selectedInGroup) !== 1) {
                throw ValidationException::withMessages([
                    'items' => 'Для опції «'.($block['name'] ?? 'Опція').'» товару «'.$product->title.'» можна обрати лише одне значення.',
                ]);
            }
        }

        return $safe;
    }

    private static function hasOptionBlocks(int $productId): bool
    {
        $product = self::product($productId);

        return $product !== null && self::optionBlocks($product) !== [];
    }

    private static function productHasVariantChoices(int $productId): bool
    {
        $product = self::product($productId);

        return $product !== null && self::hasVariantChoices($product);
    }

    private static function hasManualOptionChoices(int $productId): bool
    {
        $product = self::product($productId);

        return $product !== null && ! self::hasVariantChoices($product) && self::optionBlocks($product) !== [];
    }

    private static function product(int $productId): ?Product
    {
        if ($productId <= 0) {
            return null;
        }

        return Product::query()
            ->with('variants')
            ->find($productId);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function optionBlocks(Product $product): array
    {
        return app(ListingOptionSelectionService::class)->storefrontOptionBlocks($product);
    }

    /**
     * @return array<string, mixed>
     */
    private static function optionStateForProduct(Product $product, mixed $optionValueIds): array
    {
        $ids = app(ListingOptionSelectionService::class)->normalizeOptionValueIds($optionValueIds);
        if ($ids === []) {
            return [];
        }

        $state = [];
        if (self::hasVariantChoices($product) && ($variant = self::variantMatchingSelection($product, $ids))) {
            $state['selected_variant_id'] = (int) $variant->id;

            return $state;
        }

        $state['manual_option_value_ids'] = $ids;

        return $state;
    }

    /**
     * @return array<int, string>
     */
    private static function manualOptionChoicesForProductId(int $productId): array
    {
        $product = self::product($productId);
        if (! $product) {
            return [];
        }

        $options = [];
        foreach (self::optionBlocks($product) as $block) {
            $groupName = trim((string) ($block['name'] ?? ''));
            foreach (self::optionsForBlock($block) as $id => $label) {
                $options[$id] = $groupName !== '' ? e($groupName).': '.$label : $label;
            }
        }

        return $options;
    }

    /**
     * @param  array<string, mixed>  $block
     * @return array<int, string>
     */
    private static function optionsForBlock(array $block): array
    {
        $options = [];
        foreach ($block['values'] ?? [] as $value) {
            $id = (int) ($value['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }

            $name = e((string) ($value['name'] ?? ''));
            $colorHex = (string) ($value['color_hex'] ?? '');
            if ($colorHex !== '') {
                $dot = '<span style="display:inline-block;width:12px;height:12px;border-radius:9999px;border:1px solid #d1d5db;background:'.e($colorHex).';margin-right:8px;vertical-align:middle;"></span>';
                $name = $dot.$name;
            }

            $options[$id] = $name;
        }

        return $options;
    }

    private static function fieldName(int $productId, int $groupId): string
    {
        return 'bundle_item_option_'.$productId.'_'.$groupId;
    }

    private static function hasVariantChoices(Product $product): bool
    {
        return self::selectableVariants($product) !== [];
    }

    /**
     * @return list<ProductVariant>
     */
    private static function selectableVariants(Product $product): array
    {
        $product->loadMissing('variants');

        return $product->variants
            ->filter(fn (ProductVariant $variant): bool => $variant->isVisibleOnStorefront())
            ->filter(fn (ProductVariant $variant): bool => self::optionValueIdsForVariant($product, $variant) !== [])
            ->sortBy('sort_order')
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private static function variantOptions(Product $product): array
    {
        $options = [];
        foreach (self::selectableVariants($product) as $variant) {
            $options[(int) $variant->id] = self::variantLabel($product, $variant);
        }

        return $options;
    }

    /**
     * @return array<int, string>
     */
    private static function variantOptionsForProductId(int $productId): array
    {
        $product = self::product($productId);

        return $product ? self::variantOptions($product) : [];
    }

    private static function variantForProduct(Product $product, int $variantId): ?ProductVariant
    {
        if ($variantId <= 0) {
            return null;
        }

        foreach (self::selectableVariants($product) as $variant) {
            if ((int) $variant->id === $variantId) {
                return $variant;
            }
        }

        return null;
    }

    private static function variantMatchingSelection(Product $product, array $optionValueIds): ?ProductVariant
    {
        $ids = app(ListingOptionSelectionService::class)->normalizeOptionValueIds($optionValueIds);
        foreach (self::selectableVariants($product) as $variant) {
            if (self::optionValueIdsForVariant($product, $variant) === $ids) {
                return $variant;
            }
        }

        return null;
    }

    /**
     * @return list<int>
     */
    private static function optionValueIdsForVariant(Product $product, ProductVariant $variant): array
    {
        $pairs = VariantOptionsAllowlist::filterPairs(
            is_array($variant->options) ? $variant->options : [],
            $product,
            OptionGroup::systemCategoryGroupIdForCatalog()
        );

        $ids = collect($pairs)
            ->map(fn (array $pair): int => (int) ($pair['option_value_id'] ?? 0))
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->sort()
            ->values()
            ->all();

        return $ids;
    }

    private static function variantLabel(Product $product, ProductVariant $variant): string
    {
        $ids = self::optionValueIdsForVariant($product, $variant);
        $values = OptionValue::query()
            ->whereIn('id', $ids)
            ->with('group')
            ->get()
            ->keyBy('id');

        $parts = [];
        foreach ($ids as $id) {
            $value = $values->get($id);
            if (! $value) {
                continue;
            }

            $groupName = trim((string) ($value->group?->name ?? ''));
            $valueName = trim((string) $value->name);
            if ($valueName === '') {
                continue;
            }

            $parts[] = $groupName !== '' ? $groupName.': '.$valueName : $valueName;
        }

        $label = implode(', ', $parts);
        if ($label === '') {
            $label = 'Варіант #'.(int) $variant->id;
        }

        if (! $variant->isSellable()) {
            $label .= ' (недоступний)';
        }

        return $label;
    }
}
