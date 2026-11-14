<?php

namespace App\Filament\Admin\Concerns;

use App\Models\Product;
use App\Services\ProductCatalogCategoriesService;
use Illuminate\Support\Facades\Schema;

trait SyncsProductCatalogCategories
{
    /**
     * @var list<int|string>
     */
    protected array $pendingAdditionalCategoryIds = [];

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function extractAdditionalCategoriesFromFormData(array $data): array
    {
        $raw = $data['additional_category_ids'] ?? [];
        unset($data['additional_category_ids']);

        if (! is_array($raw)) {
            $this->pendingAdditionalCategoryIds = [];

            return $data;
        }

        $this->pendingAdditionalCategoryIds = array_values(array_filter(
            array_map(static fn ($id): int => (int) $id, $raw),
            static fn (int $id): bool => $id > 0
        ));

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function fillAdditionalCategoriesFormData(array $data, ?Product $record): array
    {
        if (! $record instanceof Product || ! Schema::hasTable('product_categories')) {
            $data['additional_category_ids'] = [];

            return $data;
        }

        $data['additional_category_ids'] = ProductCatalogCategoriesService::additionalCategoryIdsForForm($record);

        return $data;
    }

    protected function syncPendingAdditionalCategories(Product $product): void
    {
        ProductCatalogCategoriesService::syncForProduct(
            $product,
            $this->pendingAdditionalCategoryIds
        );
        $this->pendingAdditionalCategoryIds = [];
    }
}
