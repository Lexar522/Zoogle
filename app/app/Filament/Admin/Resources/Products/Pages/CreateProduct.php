<?php

namespace App\Filament\Admin\Resources\Products\Pages;

use App\Filament\Admin\Concerns\HandlesListingVariantOptions;
use App\Filament\Admin\Concerns\ProtectsListingPhotosOnSave;
use App\Filament\Admin\Concerns\SyncsProductCatalogCategories;
use App\Filament\Admin\Concerns\SyncsProductRecommendations;
use App\Filament\Admin\Resources\Products\ProductResource;
use App\Models\OptionGroup;
use App\Models\Product;
use App\Support\CatalogCategoryTree;
use App\Support\CatalogProductsTable;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class CreateProduct extends CreateRecord
{
    use HandlesListingVariantOptions;
    use ProtectsListingPhotosOnSave;
    use SyncsProductCatalogCategories;
    use SyncsProductRecommendations;

    protected static string $resource = ProductResource::class;

    protected ?string $heading = 'Створення товару';

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data = $this->extractRecommendedItemsFromFormData($data);
        $data = $this->extractAdditionalCategoriesFromFormData($data);
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
            $data['photos'] = $this->sanitizeListingPhotosForSave($data['photos'], null);
        }

        return $data;
    }

    protected function handleRecordCreation(array $data): Model
    {
        $record = parent::handleRecordCreation($data);

        if ($record instanceof Product) {
            $this->syncPendingRecommendedItems($record);
            $this->syncPendingAdditionalCategories($record);
        }

        return $record;
    }
}
