<?php

namespace App\Filament\Admin\Concerns;

use App\Models\OptionGroup;
use App\Models\OptionValue;
use App\Support\CatalogCategoryTree;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Facades\Schema;

/**
 * Категорія + variant_options для форми комплекту (аналог Create/Edit товару, без product_type).
 *
 * @phpstan-require-extends Page
 */
trait PreparesBundleFormCatalogData
{
    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function prepareBundleFormDataBeforePersist(array $data): array
    {
        $categoryGroupId = OptionGroup::systemCategoryGroupIdForCatalog();
        if ($categoryGroupId > 0 && Schema::hasColumn('bundles', 'category_parent_value_id')) {
            $data = CatalogCategoryTree::applyCategoryLevelsToFormData($data, $categoryGroupId);
        }

        if (! Schema::hasColumn('bundles', 'category_parent_value_id')) {
            unset($data['category_parent_value_id']);
        }

        if (! Schema::hasColumn('bundles', 'category_value_id')) {
            unset($data['category_value_id']);
        }

        return $this->normalizeVariantOptions($data);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function hydrateBundleFormDataForFill(array $data): array
    {
        $categoryGroupId = OptionGroup::systemCategoryGroupIdForCatalog();

        if ($categoryGroupId <= 0) {
            return $data;
        }

        $savedListingCategoryValueId = null;
        if (
            Schema::hasColumn('bundles', 'category_value_id')
            && array_key_exists('category_value_id', $data)
            && $data['category_value_id'] !== null
            && $data['category_value_id'] !== ''
        ) {
            $savedListingCategoryValueId = (int) $data['category_value_id'];
        }

        $rows = is_array($data['variant_options'] ?? null) ? $data['variant_options'] : [];
        $cleanRows = [];
        foreach ($rows as $row) {
            if ((int) ($row['option_group_id'] ?? 0) === $categoryGroupId) {
                $first = collect($row['option_value_ids'] ?? [])
                    ->map(fn ($id) => (int) $id)
                    ->filter(fn (int $id) => $id > 0)
                    ->first();

                if ($first) {
                    $data['category_value_id'] = $first;
                }

                continue;
            }

            $cleanRows[] = $row;
        }

        $data['variant_options'] = $this->enrichVariantOptionsRowsForForm($cleanRows);

        if (
            Schema::hasColumn('bundles', 'category_value_id')
            && Schema::hasColumn('option_values', 'parent_id')
            && $savedListingCategoryValueId
            && ! empty($data['category_value_id'] ?? null)
        ) {
            $fromJson = (int) $data['category_value_id'];
            if ($fromJson !== $savedListingCategoryValueId) {
                $savedIsChild = OptionValue::query()
                    ->whereKey($savedListingCategoryValueId)
                    ->whereNotNull('parent_id')
                    ->exists();
                $jsonIsRoot = OptionValue::query()
                    ->whereKey($fromJson)
                    ->whereNull('parent_id')
                    ->exists();
                if ($savedIsChild && $jsonIsRoot) {
                    $data['category_value_id'] = $savedListingCategoryValueId;
                }
            }
        }

        if (Schema::hasColumn('bundles', 'category_parent_value_id') && ! empty($data['category_parent_value_id'])) {
            $data['category_parent_value_id'] = (int) $data['category_parent_value_id'];
        }

        if (Schema::hasColumn('bundles', 'category_value_id') && ! empty($data['category_value_id'])) {
            $data['category_value_id'] = (int) $data['category_value_id'];
        }

        if (Schema::hasColumn('option_values', 'parent_id') && ! empty($data['category_value_id'])) {
            $parentMissing = ! array_key_exists('category_parent_value_id', $data)
                || $data['category_parent_value_id'] === null
                || $data['category_parent_value_id'] === '';

            if ($parentMissing) {
                $rootParentId = (int) (OptionValue::query()
                    ->whereKey((int) $data['category_value_id'])
                    ->value('parent_id') ?? 0);

                if ($rootParentId > 0) {
                    $guard = 0;
                    while ($guard < 10) {
                        $nextParent = (int) (OptionValue::query()->whereKey($rootParentId)->value('parent_id') ?? 0);
                        if ($nextParent <= 0) {
                            break;
                        }
                        $rootParentId = $nextParent;
                        $guard++;
                    }

                    $data['category_parent_value_id'] = $rootParentId;
                }
            }
        }

        if (Schema::hasColumn('bundles', 'category_value_id') && $categoryGroupId > 0) {
            $data = array_merge($data, CatalogCategoryTree::levelFieldsForFormFill($data, $categoryGroupId));
        }

        return $data;
    }
}
