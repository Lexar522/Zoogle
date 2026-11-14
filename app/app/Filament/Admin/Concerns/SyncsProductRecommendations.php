<?php

namespace App\Filament\Admin\Concerns;

use App\Models\Product;
use App\Services\ProductRecommendationsService;
use Illuminate\Support\Facades\Schema;

trait SyncsProductRecommendations
{
    /**
     * @var list<array{product_id?: int|string|null}>|null
     */
    protected ?array $pendingRecommendedItems = null;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function extractRecommendedItemsFromFormData(array $data): array
    {
        $rows = is_array($data['recommended_items'] ?? null) ? $data['recommended_items'] : [];
        $this->pendingRecommendedItems = $rows;
        unset($data['recommended_items'], $data['search_tags']);

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function fillRecommendedItemsFormData(array $data, ?Product $record): array
    {
        if (! $record instanceof Product || ! Schema::hasTable('product_recommendations')) {
            $data['recommended_items'] = [];

            return $data;
        }

        $data['recommended_items'] = $record->recommendedProducts()
            ->get()
            ->map(fn (Product $product): array => [
                'product_id' => (string) $product->id,
            ])
            ->values()
            ->all();

        unset($data['search_tags']);

        return $data;
    }

    protected function syncPendingRecommendedItems(Product $product): void
    {
        ProductRecommendationsService::syncForProduct(
            $product,
            $this->pendingRecommendedItems ?? []
        );
        $this->pendingRecommendedItems = null;
    }
}
