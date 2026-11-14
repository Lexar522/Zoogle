<?php

namespace App\Filament\Admin\Resources\Bundles\Pages;

use App\Filament\Admin\Concerns\HandlesListingVariantOptions;
use App\Filament\Admin\Concerns\PreparesBundleFormCatalogData;
use App\Filament\Admin\Concerns\SyncsBundleItemsFromForm;
use App\Filament\Admin\Resources\Bundles\BundleResource;
use App\Filament\Admin\Support\BundleItemOptionFields;
use App\Models\Bundle;
use App\Models\Product;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditBundle extends EditRecord
{
    use HandlesListingVariantOptions;
    use PreparesBundleFormCatalogData;
    use SyncsBundleItemsFromForm;

    protected static string $resource = BundleResource::class;

    /** @var list<array<string, mixed>>|null */
    protected ?array $pendingBundleItems = null;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function listingForVariantOptionsMerge(): ?Product
    {
        return null;
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data = $this->hydrateBundleFormDataForFill($data);

        $record = $this->getRecord();
        if ($record instanceof Bundle) {
            $data['items'] = $record->items()
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get()
                ->map(fn ($item): array => BundleItemOptionFields::hydrateRowOptionState([
                    'product_id' => $item->product_id,
                    'option_value_ids' => is_array($item->option_value_ids) ? $item->option_value_ids : [],
                    'qty' => $item->qty,
                    'sort_order' => $item->sort_order,
                ]))
                ->all();
        }

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $rawItems = $data['items'] ?? [];
        unset($data['items']);

        $this->pendingBundleItems = BundleItemOptionFields::normalizeAndValidateRows(is_array($rawItems) ? $rawItems : []);

        return $this->prepareBundleFormDataBeforePersist($data);
    }

    protected function afterSave(): void
    {
        $record = $this->getRecord();
        if ($record instanceof Bundle && $this->pendingBundleItems !== null) {
            $this->syncBundleItemsFromForm($record, $this->pendingBundleItems);
            $this->pendingBundleItems = null;
        }
    }
}
