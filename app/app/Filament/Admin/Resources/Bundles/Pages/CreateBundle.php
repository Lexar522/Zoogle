<?php

namespace App\Filament\Admin\Resources\Bundles\Pages;

use App\Filament\Admin\Concerns\HandlesListingVariantOptions;
use App\Filament\Admin\Concerns\PreparesBundleFormCatalogData;
use App\Filament\Admin\Concerns\SyncsBundleItemsFromForm;
use App\Filament\Admin\Resources\Bundles\BundleResource;
use App\Models\Bundle;
use App\Models\Product;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Validation\ValidationException;

class CreateBundle extends CreateRecord
{
    use HandlesListingVariantOptions;
    use PreparesBundleFormCatalogData;
    use SyncsBundleItemsFromForm;

    protected static string $resource = BundleResource::class;

    /** @var list<array<string, mixed>>|null */
    protected ?array $pendingBundleItems = null;

    protected function listingForVariantOptionsMerge(): ?Product
    {
        return null;
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $rawItems = $data['items'] ?? [];
        unset($data['items']);

        $validItems = [];
        if (is_array($rawItems)) {
            foreach ($rawItems as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $productId = (int) ($row['product_id'] ?? 0);
                if ($productId <= 0) {
                    continue;
                }
                $validItems[] = [
                    'product_id' => $productId,
                    'qty' => max(1, (int) ($row['qty'] ?? 1)),
                    'sort_order' => (int) ($row['sort_order'] ?? 0),
                ];
            }
        }

        if ($validItems === []) {
            throw ValidationException::withMessages([
                'items' => 'Додайте хоча б один товар у комплект (оберіть товар у позиції).',
            ]);
        }

        $this->pendingBundleItems = $validItems;

        return $this->prepareBundleFormDataBeforePersist($data);
    }

    protected function afterCreate(): void
    {
        $record = $this->getRecord();
        if ($record instanceof Bundle && $this->pendingBundleItems !== null) {
            $this->syncBundleItemsFromForm($record, $this->pendingBundleItems);
            $this->pendingBundleItems = null;
        }
    }
}
