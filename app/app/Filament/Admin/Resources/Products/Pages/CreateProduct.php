<?php

namespace App\Filament\Admin\Resources\Products\Pages;

use App\Filament\Admin\Concerns\HandlesListingVariantOptions;
use App\Filament\Admin\Resources\Products\ProductResource;
use App\Models\OptionGroup;
use App\Support\CatalogCategoryTree;
use App\Support\CatalogProductsTable;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Schema;

class CreateProduct extends CreateRecord
{
    use HandlesListingVariantOptions;

    protected static string $resource = ProductResource::class;

    protected ?string $heading = 'Створення товару';

    protected function mutateFormDataBeforeCreate(array $data): array
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

        return $this->normalizeVariantOptions($data);
    }
}
