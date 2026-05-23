<?php

namespace App\Filament\Admin\Resources\Products\Pages;

use App\Filament\Admin\Concerns\HandlesListingVariantOptions;
use App\Filament\Admin\Concerns\ProtectsListingPhotosOnSave;
use App\Filament\Admin\Resources\Products\ProductResource;
use App\Models\OptionGroup;
use App\Models\OptionValue;
use App\Models\Product;
use App\Support\CatalogCategoryTree;
use App\Support\CatalogProductsTable;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class EditProduct extends EditRecord
{
    use HandlesListingVariantOptions;
    use ProtectsListingPhotosOnSave;

    protected static string $resource = ProductResource::class;

    protected ?string $heading = 'Редагування товару';

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data = $this->normalizeProductRichTextFields($data);

        $categoryGroupId = OptionGroup::systemCategoryGroupIdForCatalog();
        if ($categoryGroupId > 0 && Schema::hasColumn(CatalogProductsTable::name(), 'category_parent_value_id')) {
            $data = CatalogCategoryTree::applyCategoryLevelsToFormData($data, $categoryGroupId);
        }

        if (! Schema::hasColumn(CatalogProductsTable::name(), 'product_type')) {
            unset($data['product_type']);
        } else {
            $data['product_type'] = OptionGroup::CATALOG_PRODUCT_TYPE;
        }

        if (! Schema::hasColumn(CatalogProductsTable::name(), 'category_parent_value_id')) {
            unset($data['category_parent_value_id']);
        }

        if (! Schema::hasColumn(CatalogProductsTable::name(), 'category_value_id')) {
            unset($data['category_value_id']);
        }

        $data = $this->normalizeVariantOptions($data);

        if (array_key_exists('photos', $data)) {
            $data['photos'] = $this->sanitizeListingPhotosForSave(
                $data['photos'],
                $this->getRecord()
            );
        }

        return $data;
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data = $this->coerceProductRichTextFieldsForFill($data);
        $data = $this->sanitizeListingPhotosForForm($data);

        $categoryGroupId = OptionGroup::systemCategoryGroupIdForCatalog();

        if ($categoryGroupId <= 0) {
            return $data;
        }

        $savedListingCategoryValueId = null;
        if (
            Schema::hasColumn(CatalogProductsTable::name(), 'category_value_id')
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
            Schema::hasColumn(CatalogProductsTable::name(), 'category_value_id')
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

        if (Schema::hasColumn(CatalogProductsTable::name(), 'category_parent_value_id') && ! empty($data['category_parent_value_id'])) {
            $data['category_parent_value_id'] = (int) $data['category_parent_value_id'];
        }

        if (Schema::hasColumn(CatalogProductsTable::name(), 'category_value_id') && ! empty($data['category_value_id'])) {
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

        if (Schema::hasColumn(CatalogProductsTable::name(), 'category_value_id')) {
            $data = array_merge($data, CatalogCategoryTree::levelFieldsForFormFill($data, $categoryGroupId));
        }

        return $this->mergeListingPhotosFromDisk($data, $this->getRecord());
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $record = parent::handleRecordUpdate($record, $data);

        if ($record instanceof Product) {
            $this->repairListingPhotosInDatabaseIfNeeded($record);
        }

        return $record;
    }
}
