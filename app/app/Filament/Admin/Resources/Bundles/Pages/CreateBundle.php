<?php

namespace App\Filament\Admin\Resources\Bundles\Pages;

use App\Filament\Admin\Concerns\HandlesListingVariantOptions;
use App\Filament\Admin\Concerns\PreparesBundleFormCatalogData;
use App\Filament\Admin\Concerns\SyncsBundleItemsFromForm;
use App\Filament\Admin\Resources\Bundles\BundleResource;
use App\Filament\Admin\Support\BundleItemOptionFields;
use App\Models\Bundle;
use App\Models\Product;
use Filament\Resources\Pages\CreateRecord;

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

        $this->pendingBundleItems = BundleItemOptionFields::normalizeAndValidateRows(is_array($rawItems) ? $rawItems : []);

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
